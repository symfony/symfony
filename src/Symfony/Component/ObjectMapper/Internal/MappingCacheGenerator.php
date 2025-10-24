<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Internal;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\ObjectMapper\Exception\MappingException;
use Symfony\Component\ObjectMapper\Metadata\ObjectMapperMetadataFactoryInterface;
use Symfony\Component\ObjectMapper\Metadata\ReflectionObjectMapperMetadataFactory;
use Symfony\Component\VarExporter\Exception\NotInstantiableTypeException;
use Symfony\Component\VarExporter\VarExporter;

/**
 * @internal
 */
final class MappingCacheGenerator implements MappingCacheGeneratorInterface
{
    use ObjectMapperTrait;

    private ?Filesystem $fs = null;

    public function __construct(
        ObjectMapperMetadataFactoryInterface $metadataFactory = new ReflectionObjectMapperMetadataFactory(),
    ) {
        $this->metadataFactory = $metadataFactory;
    }

    public function generate(string $sourceClass, string $targetClass): string
    {
        $refl = new \ReflectionClass($sourceClass);
        if ($refl->isAbstract()) {
            throw new MappingException(\sprintf('Can not generate mapping metadata from an abstract class "%s".', $sourceClass));
        }

        $sourceObject = $refl->newInstanceWithoutConstructor();
        $mappingData = $this->getMappingMetadata($sourceObject, $targetClass);

        return $this->generateMappingCode($mappingData, $sourceClass, $targetClass);
    }

    public function write(string $cacheFile, string $code): void
    {
        $this->fs ??= new Filesystem();

        if (!$this->fs->exists(\dirname($cacheFile))) {
            $this->fs->mkdir(\dirname($cacheFile));
        }

        $this->fs->dumpFile($cacheFile, $code);
    }

    private function getMappingMetadata(object $source, string $targetClass): array
    {
        try {
            $sourceRefl = $this->getSourceReflectionClass($source);
            $targetRefl = new \ReflectionClass($targetClass);
        } catch (\ReflectionException $e) {
            throw new MappingException($e->getMessage(), $e->getCode(), $e);
        }

        $refl = $sourceRefl ?? $targetRefl;
        $readMetadataFrom = $source;
        if (!$this->metadataFactory->create($source)) {
            $targetInstance = $targetRefl->newInstanceWithoutConstructor();
            if ($this->metadataFactory->create($targetInstance)) {
                $readMetadataFrom = $targetInstance;
            }
        }

        if ($refl === $targetRefl) {
            $readMetadataFrom = $targetRefl->newInstanceWithoutConstructor();
        }

        $metadata = $this->metadataFactory->create($readMetadataFrom);
        $map = $this->getMapTarget($metadata, null, $source, null);

        $constructorParams = [];
        if ($constructor = $targetRefl->getConstructor()) {
            foreach ($constructor->getParameters() as $parameter) {
                $paramName = $parameter->getName();
                // If $sourceRefl is null (stdClass), assume it is mappable, runtime will fail if it needs to
                $sourceIsMappable = !$sourceRefl || $sourceRefl->hasProperty($paramName);

                $constructorParams[$paramName] = [
                    'name' => $paramName,
                    'hasDefault' => $parameter->isDefaultValueAvailable(),
                    'defaultValue' => $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null,
                    'propertyIsMappable' => $this->propertyIsMappable($targetRefl, $paramName),
                    'sourceIsMappable' => $sourceIsMappable,
                ];
            }
        }

        return [
            'properties' => $this->analyzeProperties($refl, $readMetadataFrom, $sourceRefl, $targetRefl, $source, $constructorParams),
            'hasConstructor' => null !== $targetRefl->getConstructor(),
            'constructorParams' => $constructorParams,
            'targetTransform' => $map?->transform,
        ];
    }

    /**
     * @param array<string, mixed> $mappingData
     */
    public function generateMappingCode(array $mappingData, string $sourceClass, string $targetClass): string
    {
        $lines = [
            '<?php',
            '',
            'use Psr\\Container\\ContainerInterface;',
            'use Symfony\\Component\\ObjectMapper\\Exception\\MappingTransformException;',
            'use Symfony\\Component\\ObjectMapper\\Exception\\MappingException;',
            'use Symfony\\Component\\ObjectMapper\\MappingHelper;',
            'use Symfony\\Component\\ObjectMapper\\ObjectMapperInterface;',
            'use Symfony\\Component\\ObjectMapper\\Metadata\\ObjectMapperMetadataFactoryInterface;',
            'use Symfony\\Component\\PropertyAccess\\PropertyAccessorInterface;',
            '',
            'return function(',
            "    {$sourceClass} \$source,",
            "    {$targetClass} \$target,",
            '    ObjectMapperInterface $objectMapper,',
            '    ObjectMapperMetadataFactoryInterface $metadataFactory,',
            '    \SplObjectStorage $objectMap,',
            '    ?PropertyAccessorInterface $propertyAccessor = null,',
            '    ?ContainerInterface $transformCallableLocator = null,',
            '    ?ContainerInterface $conditionCallableLocator = null,',
            '    bool $mappingToObject = false',
            '): '.$targetClass.' {',
        ];

        if ($mappingData['targetTransform']) {
            try {
                $transformVar = VarExporter::export($mappingData['targetTransform']);
            } catch (NotInstantiableTypeException $e) {
                throw new MappingException(\sprintf('The transform on "%s" can not be exported.', $sourceClass), $e->getCode(), $e);
            }

            $lines[] = "    if ((\$transform = MappingHelper::getCallable({$transformVar}, \$transformCallableLocator))) {";
            $lines[] = '        $newTarget = MappingHelper::call($transform, $target, $source, null);';
            $lines[] = '        if (!is_object($newTarget)) {';
            $lines[] = "            throw new MappingTransformException(\\sprintf('Cannot map \"%s\" to a non-object target of type \"%s\".', get_debug_type(\$source), get_debug_type(\$newTarget)));";
            $lines[] = '        }';
            $lines[] = '        if (!is_a($newTarget, $target::class, true)) {';
            $lines[] = "            throw new MappingException(\\sprintf('Expected the mapped object to be an instance of \"%s\" but got \"%s\".', get_debug_type(\$target), get_debug_type(\$newTarget)));";
            $lines[] = '        }';
            $lines[] = '        $target = $newTarget;';
            $lines[] = '    }';
            $lines[] = '';
        }

        $lines[] = '    $ctorArguments = [];';
        if ($mappingData['hasConstructor']) {
            foreach ($mappingData['constructorParams'] as ['hasDefault' => $hasDefault, 'defaultValue' => $defaultValue, 'name' => $name]) {
                if ($hasDefault) {
                    $defaultValue = VarExporter::export($defaultValue);
                    $lines[] = "    \$ctorArguments['{$name}'] = {$defaultValue};";
                    continue;
                }
            }
            $lines[] = '';
        }

        $lines[] = '    $mapToProperties = [];';
        $lines[] = '    $objectMap[$source] = $target;';
        $lines[] = '';

        foreach ($mappingData['properties'] as ['source' => $sourceProperty, 'target' => $targetProperty, 'mapping' => $mapping, 'isConstructorParam' => $isConstructorParam]) {
            $condition = $mapping?->if;
            $transform = $mapping?->transform;

            $lines[] = "    if (!property_exists(\$source, '{$sourceProperty}') || (new \\ReflectionProperty(\$source, '{$sourceProperty}'))->isInitialized(\$source)) {";
            $lines[] = "        \$value = MappingHelper::getValue(\$source, '{$sourceProperty}', \$propertyAccessor);";

            $indentation = '        ';
            if ($condition) {
                try {
                    $conditionVar = VarExporter::export($condition);
                } catch (NotInstantiableTypeException $e) {
                    throw new MappingException(\sprintf('The mapping condition from "%s" to "%s" can not be exported.', $sourceProperty, $targetProperty), $e->getCode(), $e);
                }
                $lines[] = "        \$condition = MappingHelper::getCallable({$conditionVar}, \$conditionCallableLocator);";
                $lines[] = '        if ($condition && MappingHelper::call($condition, $value, $source, $target)) {';
                $indentation = '            ';
            }

            if ($transform) {
                try {
                    $transformVar = VarExporter::export($transform);
                } catch (NotInstantiableTypeException $e) {
                    throw new MappingException(\sprintf('The mapping transform from "%s" to "%s" can not be exported.', $sourceProperty, $targetProperty), $e->getCode(), $e);
                }
                $lines[] = "{$indentation}\$transform = MappingHelper::getCallable({$transformVar}, \$transformCallableLocator);";
                $lines[] = "{$indentation}if (\$transform) {";
                $lines[] = "{$indentation}    \$value = MappingHelper::call(\$transform, \$value, \$source, \$target);";
                $lines[] = "{$indentation}}";
            }

            $lines[] = "{$indentation}if (is_object(\$value) && MappingHelper::hasMappingTarget(\$value, \$metadataFactory)) {";
            $lines[] = "{$indentation}    \$value = match (true) {";
            $lines[] = "{$indentation}        \$value === \$source => \$target,";
            $lines[] = "{$indentation}        \$objectMap->offsetExists(\$value) => \$objectMap[\$value],";
            $lines[] = "{$indentation}        default => \$objectMapper->map(\$value),";
            $lines[] = "{$indentation}    };";
            $lines[] = "{$indentation}}";

            if ($isConstructorParam) {
                $lines[] = "{$indentation}\$ctorArguments['{$targetProperty}'] = \$value;";
            } else {
                $lines[] = "{$indentation}\$mapToProperties['{$targetProperty}'] = \$value;";
            }

            if ($condition && true !== $condition) {
                $lines[] = '        }';
            }

            $lines[] = '    }';
            $lines[] = '';
        }

        if ($mappingData['hasConstructor'] && !$mappingData['targetTransform']) {
            $lines[] = '    if (!$mappingToObject) {';
            $lines[] = '        $target->__construct(...$ctorArguments);';
            $lines[] = '    }';
        }

        // Do not output the ctor `if` if we do not need to
        if (array_filter($mappingData['constructorParams'], fn($v) => $v['propertyIsMappable'])) {
            $lines[] = '    if ($mappingToObject && $ctorArguments) {';
            foreach ($mappingData['constructorParams'] as $property => $param) {
                if ($param['propertyIsMappable'] && $param['sourceIsMappable']) {
                    $lines[] = "        if (isset(\$ctorArguments['$property'])) {";
                    $lines[] = "            \$mapToProperties['$property'] = \$ctorArguments['$property'];";
                    $lines[] = '        }';
                }
            }
            $lines[] = '    }';
        }

        $lines[] = '    foreach ($mapToProperties as $prop => $v) {';
        $lines[] = '        MappingHelper::setValue($target, $prop, $v, $propertyAccessor);';
        $lines[] = '    }';

        $lines[] = '    return $target;';
        $lines[] = '};';
        $lines[] = "\n";

        return implode("\n", $lines);
    }
}

