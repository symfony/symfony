<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper;

use Psr\Container\ContainerInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\ObjectMapper\Exception\MappingException;
use Symfony\Component\ObjectMapper\Metadata\ObjectMapperMetadataFactoryInterface;
use Symfony\Component\ObjectMapper\Metadata\ReflectionObjectMapperMetadataFactory;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\VarExporter\Exception\NotInstantiableTypeException;
use Symfony\Component\VarExporter\LazyObjectInterface;
use Symfony\Component\VarExporter\VarExporter;

/**
 * Cached Object to object mapper that generates and caches compiled mapping functions.
 *
 * @author Antoine Bluchet <soyuka@gmail.com>
 */
final class CachedObjectMapper implements ObjectMapperInterface, ObjectMapperAwareInterface
{
    use ObjectMapperAwareTrait;
    use ObjectMapperTrait;

    private array $cachedMappings = [];
    private ?Filesystem $fs = null;

    /**
     * Tracks recursive references.
     */
    private ?\SplObjectStorage $objectMap = null;

    public function __construct(
        private readonly string $cacheDir,
        ObjectMapperMetadataFactoryInterface $metadataFactory = new ReflectionObjectMapperMetadataFactory(),
        ?PropertyAccessorInterface $propertyAccessor = null,
        ?ContainerInterface $transformCallableLocator = null,
        ?ContainerInterface $conditionCallableLocator = null,
        ?ObjectMapperInterface $objectMapper = null,
        private readonly bool $debug = false,
    ) {
        $this->metadataFactory = $metadataFactory;
        $this->propertyAccessor = $propertyAccessor;
        $this->transformCallableLocator = $transformCallableLocator;
        $this->conditionCallableLocator = $conditionCallableLocator;
        $this->objectMapper = $objectMapper;
    }

    public function map(object $source, object|string|null $target = null): object
    {
        $objectMapInitialized = false;
        if (null === $this->objectMap) {
            $this->objectMap = new \SplObjectStorage();
            $objectMapInitialized = true;
        }

        $sourceClass = $source::class;
        $targetClass = $this->resolveTargetClass($source, $target);

        if (!$targetClass) {
            throw new MappingException(\sprintf('Mapping target not found for source "%s".', get_debug_type($source)));
        }

        if (\is_string($target) && !class_exists($target)) {
            throw new MappingException(\sprintf('Mapping target class "%s" does not exist for source "%s".', $target, get_debug_type($source)));
        }

        $cacheKey = str_replace(['\\', '/'], '_', $sourceClass.'-to-'.$targetClass);

        if (!isset($this->cachedMappings[$cacheKey])) {
            $cacheFile = $this->cacheDir.'/'.$cacheKey.'.php';

            if (!file_exists($cacheFile) || $this->debug) {
                $this->fs ??= new Filesystem();
                $mappingData = $this->getMappingMetadata($source, $targetClass);
                $code = $this->generateMappingCode($mappingData, $sourceClass, $targetClass);

                $tmpFile = $this->fs->tempnam(\dirname($cacheFile), basename($cacheFile));

                try {
                    $this->fs->dumpFile($tmpFile, $code);
                    $this->fs->rename($tmpFile, $cacheFile, true);
                    $this->fs->chmod($cacheFile, 0o666 & ~umask());
                } catch (IOException $e) {
                    throw new MappingException(\sprintf('Failed to write "%s" mapping file.', $cacheFile), previous: $e);
                }
            }

            $this->cachedMappings[$cacheKey] = require $cacheFile;
        }

        $mappingFunction = $this->cachedMappings[$cacheKey];
        $mappingToObject = \is_object($target);
        $targetObject = \is_object($target) ? $target : (new \ReflectionClass($targetClass))->newInstanceWithoutConstructor();

        if ($source instanceof LazyObjectInterface) {
            $source->initializeLazyObject();
        } elseif (\PHP_VERSION_ID >= 80400) {
            $refl = new \ReflectionClass($source);
            if (!$refl->isUninitializedLazyObject($source)) {
                $refl->initializeLazyObject($source);
            }
        }

        $mappedTarget = $mappingFunction(
            $source,
            $targetObject,
            $this->objectMapper ?? $this,
            $this->metadataFactory,
            $this->objectMap,
            $this->propertyAccessor,
            $this->transformCallableLocator,
            $this->conditionCallableLocator,
            $mappingToObject
        );

        if ($objectMapInitialized) {
            $this->objectMap = null;
        }

        return $mappedTarget;
    }

    private function resolveTargetClass(object $source, object|string|null $target): ?string
    {
        if (\is_string($target)) {
            return $target;
        }

        if (\is_object($target)) {
            return $target::class;
        }

        // Try to find target class from metadata
        $metadata = $this->metadataFactory->create($source);
        if ($map = $this->getMapTarget($metadata, null, $source, null)) {
            if ($map->target && \is_string($map->target) && class_exists($map->target)) {
                return $map->target;
            }
        }

        return null;
    }

    /**
     * @return array{constructorParams: array<non-empty-string, array{defaultValue: mixed|null, hasDefault: bool, name: non-empty-string, propertyIsMappable: bool}>, hasConstructor: bool, properties: list<array{isConstructorParam: bool, mapping: Symfony\Component\ObjectMapper\Metadata\Mapping|null, source: non-empty-string, target: string}>, targetTransform: array<array-key, callable(mixed, object):mixed|string>|callable(mixed, object):mixed|string|null}
     */
    private function getMappingMetadata(object $source, string $targetClass): array
    {
        try {
            $sourceRefl = $this->getSourceReflectionClass($source);
            $targetRefl = new \ReflectionClass($targetClass);
        } catch (\ReflectionException $e) {
            throw new MappingException($e->getMessage(), $e->getCode(), $e);
        }

        $constructorParams = [];

        $readMetadataFrom = $source;
        $refl = $sourceRefl ?? $targetRefl;

        if ($constructor = $targetRefl->getConstructor()) {
            foreach ($constructor->getParameters() as $parameter) {
                if ($parameter->isPromoted()) {
                    $constructorParams[$parameter->getName()] = [
                        'name' => $parameter->getName(),
                        'hasDefault' => $parameter->isDefaultValueAvailable(),
                        'defaultValue' => $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null,
                        'propertyIsMappable' => $this->propertyIsMappable($refl, $parameter->getName()) && $this->propertyIsMappable($targetRefl, $parameter->getName()),
                    ];
                }
            }
        }

        if (!$this->metadataFactory->create($source)) {
            $targetInstance = $targetRefl->newInstanceWithoutConstructor();
            if ($this->metadataFactory->create($targetInstance)) {
                $readMetadataFrom = $targetInstance;
            }
        }

        // When source contains no metadata, we read metadata on the target instead
        if ($refl === $targetRefl) {
            $readMetadataFrom = $targetRefl->newInstanceWithoutConstructor();
        }

        $metadata = $this->metadataFactory->create($readMetadataFrom);
        $map = $this->getMapTarget($metadata, null, $source, null);

        return [
            'properties' => $this->analyzeProperties($refl, $readMetadataFrom, $sourceRefl ?? $refl, $targetRefl, $source, $constructorParams),
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
            $lines[] = "{$indentation}    if (\$value === \$source) {";
            $lines[] = "{$indentation}        \$value = \$target;";
            $lines[] = "{$indentation}    } elseif (\$objectMap->offsetExists(\$value)) {";
            $lines[] = "{$indentation}        \$value = \$objectMap[\$value];";
            $lines[] = "{$indentation}    } else {";
            $lines[] = "{$indentation}        \$value = \$objectMapper->map(\$value);";
            $lines[] = "{$indentation}    }";
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

        $lines[] = '    if ($mappingToObject && $ctorArguments) {';
        foreach ($mappingData['constructorParams'] as $property => $param) {
            if ($param['propertyIsMappable']) {
                $lines[] = "        if (isset(\$ctorArguments['$property'])) {";
                $lines[] = "            \$mapToProperties['$property'] = \$ctorArguments['$property'];";
                $lines[] = '        }';
            }
        }
        $lines[] = '    }';

        $lines[] = '    foreach ($mapToProperties as $prop => $v) {';
        $lines[] = '        MappingHelper::setValue($target, $prop, $v, $propertyAccessor);';
        $lines[] = '    }';

        $lines[] = '    return $target;';
        $lines[] = '};';

        return implode("\n", $lines);
    }
}
