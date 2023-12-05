<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Builder;

use Symfony\Component\Serializer\Builder\CodeGenerator\ClassGenerator;
use Symfony\Component\Serializer\Builder\CodeGenerator\Method;
use Symfony\Component\Serializer\Builder\CodeGenerator\Property;
use Symfony\Component\Serializer\Exception\DenormalizingUnionFailedException;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * The main class to create a new Normalizer from a ClassDefinition.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * @experimental in 7.1
 */
class NormalizerBuilder
{
    public function build(ClassDefinition $definition, string $outputDir): BuildResult
    {
        @mkdir($outputDir, 0777, true);
        $generator = new ClassGenerator($definition->getNewClassName(), $definition->getNewNamespace());

        $generator->addImport($definition->getNamespaceAndClass());
        $this->addRequiredMethods($generator, $definition);
        $this->addNormailizeMethod($generator, $definition);
        $this->addDenormailizeMethod($generator, $definition);

        $outputFile = $outputDir.'/'.$definition->getNewClassName().'.php';
        file_put_contents($outputFile, $generator->toString());

        return new BuildResult(
            $outputFile,
            $definition->getNewClassName(),
            sprintf('%s\\%s', $definition->getNewNamespace(), $definition->getNewClassName())
        );
    }

    /**
     * Generate a private helper class to normalize subtypes.
     */
    private function generateNormalizeChildMethod(ClassGenerator $generator): void
    {
        $generator->addImport(NormalizerAwareInterface::class);
        $generator->addImplements('NormalizerAwareInterface');

        $generator->addProperty(Property::create('normalizer')->setType('null|NormalizerInterface')->setDefaultValue(null)->setVisibility('private'));

        // public function setNormalizer(NormalizerInterface $normalizer): void;
        $generator->addMethod(Method::create('setNormalizer')
            ->addArgument('normalizer', 'NormalizerInterface')
            ->setReturnType('void')
            ->setBody('$this->normalizer = $normalizer;')
        );

        $generator->addMethod(Method::create('normalizeChild')
            ->setVisibility('private')
            ->addArgument('object', 'mixed')
            ->addArgument('format', '?string')
            ->addArgument('context', 'array')
            ->addArgument('canBeIterable', 'bool')
            ->setReturnType('mixed')
            ->setBody(<<<PHP
if (is_scalar(\$object) || null === \$object) {
    return \$object;
}

if (\$canBeIterable === true && is_iterable(\$object)) {
    return array_map(fn(\$item) => \$this->normalizeChild(\$item, \$format, \$context, true), \$object);
}

return \$this->normalizer->normalize(\$object, \$format, \$context);

PHP
            )
        );
    }

    /**
     * Generate a private helper class to de-normalize subtypes.
     */
    private function generateDenormalizeChildMethod(ClassGenerator $generator): void
    {
        $generator->addImport(DenormalizingUnionFailedException::class);
        $generator->addImport(DenormalizerAwareInterface::class);
        $generator->addImplements('DenormalizerAwareInterface');

        $generator->addProperty(Property::create('denormalizer')->setType('null|DenormalizerInterface')->setDefaultValue(null)->setVisibility('private'));

        // public function setNormalizer(NormalizerInterface $normalizer): void;
        $generator->addMethod(Method::create('setDenormalizer')
            ->addArgument('denormalizer', 'DenormalizerInterface')
            ->setReturnType('void')
            ->setBody('$this->denormalizer = $denormalizer;')
        );

        $generator->addMethod(Method::create('denormalizeChild')
            ->setVisibility('private')
            ->addArgument('data', 'mixed')
            ->addArgument('type', 'string')
            ->addArgument('format', '?string')
            ->addArgument('context', 'array')
            ->addArgument('canBeIterable', 'bool')
            ->setReturnType('mixed')
            ->setBody(<<<PHP
if (is_scalar(\$data) || null === \$data) {
    return \$data;
}

if (\$canBeIterable === true && is_iterable(\$data)) {
    return array_map(fn(\$item) => \$this->denormalizeChild(\$item, \$type, \$format, \$context, true), \$data);
}

return \$this->denormalizer->denormalize(\$data, \$type, \$format, \$context);

PHP
            )
        );
    }

    /**
     * Add methods required by NormalizerInterface and DenormalizerInterface.
     */
    private function addRequiredMethods(ClassGenerator $generator, ClassDefinition $definition): void
    {
        $generator->addImport(NormalizerInterface::class);
        $generator->addImport(DenormalizerInterface::class);
        $generator->addImplements('NormalizerInterface');
        $generator->addImplements('DenormalizerInterface');

        // public function getSupportedTypes(?string $format): array;
        $generator->addMethod(Method::create('getSupportedTypes')
            ->addArgument('format', '?string')
            ->setReturnType('array')
            ->setBody(sprintf('return [%s::class => true];', $definition->getSourceClassName()))
        );

        // public function supportsNormalization(mixed $data, string $format = null, array $context = []): bool;
        $generator->addMethod(Method::create('supportsNormalization')
            ->addArgument('data', 'mixed')
            ->addArgument('format', '?string', null)
            ->addArgument('context', 'array', [])
            ->setReturnType('bool')
            ->setBody(sprintf('return $data instanceof %s;', $definition->getSourceClassName()))
        );

        // public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool;
        $generator->addMethod(Method::create('supportsDenormalization')
            ->addArgument('data', 'mixed')
            ->addArgument('type', 'string')
            ->addArgument('format', '?string', null)
            ->addArgument('context', 'array', [])
            ->setReturnType('bool')
            ->setBody(sprintf('return $type === %s::class;', $definition->getSourceClassName()))
        );
    }

    private function addDenormailizeMethod(ClassGenerator $generator, ClassDefinition $definition): void
    {
        $needsChildDenormalizer = false;
        $preCreateObject = '';

        if (ClassDefinition::CONSTRUCTOR_NONE === $definition->getConstructorType()) {
            $body = sprintf('$output = new %s();', $definition->getSourceClassName()).\PHP_EOL;
        } elseif (ClassDefinition::CONSTRUCTOR_PUBLIC !== $definition->getConstructorType()) {
            $body = sprintf('$output = (new \\ReflectionClass(%s::class))->newInstanceWithoutConstructor();', $definition->getSourceClassName()).\PHP_EOL;
        } else {
            $body = sprintf('$output = new %s(', $definition->getSourceClassName()).\PHP_EOL;

            foreach ($definition->getConstructorArguments() as $i => $propertyDefinition) {
                $body .= '    ';
                $variable = sprintf('$data[\'%s\']', $propertyDefinition->getNormalizedName());
                $targetClasses = $propertyDefinition->getNonPrimitiveTypes();
                $canBeIterable = $propertyDefinition->isCollection();

                if ([] === $targetClasses && $propertyDefinition->hasConstructorDefaultValue()) {
                    $variable .= ' ?? '.var_export($propertyDefinition->getConstructorDefaultValue(), true);
                } elseif ([] !== $targetClasses) {
                    $needsChildDenormalizer = true;
                    $printedCanBeIterable = $canBeIterable ? 'true' : 'false';
                    $tempVariableName = '$argument'.$i;

                    if (\count($targetClasses) > 1) {
                        $variableOutput = $this->generateCodeToDeserializeMultiplePossibleClasses($targetClasses, $printedCanBeIterable, $tempVariableName, $variable, $propertyDefinition->getNormalizedName(), $definition->getNamespaceAndClass());
                    } else {
                        $variableOutput = <<<PHP
{$tempVariableName} = \$this->denormalizeChild($variable, \\{$targetClasses[0]}::class, \$format, \$context, $printedCanBeIterable);

PHP;
                    }

                    if ($propertyDefinition->hasConstructorDefaultValue()) {
                        $export = var_export($propertyDefinition->getConstructorDefaultValue(), true);
                        $variableOutput = <<<PHP
if (!array_key_exists('{$propertyDefinition->getNormalizedName()}', \$data)) {
    {$tempVariableName} = {$export};
} else {
    {$variableOutput}
}
PHP;
                    }

                    // Make sure we continue to reference the temp var
                    $variable = $tempVariableName;
                    $preCreateObject .= $variableOutput;
                }

                $body .= $variable.','.\PHP_EOL;
            }

            $body .= ');'.\PHP_EOL;
        }

        $i = 0;
        foreach ($definition->getDefinitions() as $propertyDefinition) {
            if (!$propertyDefinition->isWriteable() || $propertyDefinition->isConstructorArgument()) {
                continue;
            }

            $variable = sprintf('$data[\'%s\']', $propertyDefinition->getNormalizedName());
            $accessor = '';
            $targetClasses = $propertyDefinition->getNonPrimitiveTypes();

            if ([] !== $targetClasses) {
                $needsChildDenormalizer = true;
                $printedCanBeIterable = $propertyDefinition->isCollection() ? 'true' : 'false';
                $tempVariableName = '$setter'.$i++;

                if (\count($targetClasses) > 1) {
                    $accessor .= $this->generateCodeToDeserializeMultiplePossibleClasses($targetClasses, $printedCanBeIterable, $tempVariableName, $variable, $propertyDefinition->getNormalizedName(), $definition->getNamespaceAndClass());
                } else {
                    $accessor .= <<<PHP
{$tempVariableName} = \$this->denormalizeChild($variable, \\{$targetClasses[0]}::class, \$format, \$context, $printedCanBeIterable);

PHP;
                }
                $accessor .= '    ';

                // Make sure we continue to reference the temp var
                $variable = $tempVariableName;
            }

            if (null !== $method = $propertyDefinition->getSetterName()) {
                $accessor .= sprintf('$output->%s(%s);', $method, $variable);
            } else {
                $accessor .= sprintf('$output->%s = %s;', $propertyDefinition->getPropertyName(), $variable);
            }

            $body .= <<<PHP
if (array_key_exists('{$propertyDefinition->getNormalizedName()}', \$data)) {
    $accessor
}

PHP;
        }

        $body .= \PHP_EOL.'return $output;';

        $generator->addMethod(Method::create('denormalize')
            ->addArgument('data', 'mixed')
            ->addArgument('type', 'string')
            ->addArgument('format', '?string', null)
            ->addArgument('context', 'array', [])
            ->setReturnType('mixed')
            ->setBody($preCreateObject.\PHP_EOL.$body));

        if ($needsChildDenormalizer) {
            $this->generateDenormalizeChildMethod($generator);
        }
    }

    private function addNormailizeMethod(ClassGenerator $generator, ClassDefinition $definition): void
    {
        $body = '';
        $needsChildNormalizer = false;
        foreach ($definition->getDefinitions() as $propertyDefinition) {
            if (!$propertyDefinition->isReadable()) {
                continue;
            }

            if (null !== $method = $propertyDefinition->getGetterName()) {
                $accessor = sprintf('$object->%s()', $method);
            } else {
                $accessor = sprintf('$object->%s', $propertyDefinition->getPropertyName());
            }

            if ($propertyDefinition->hasNoTypeDefinition() || [] !== $propertyDefinition->getNonPrimitiveTypes()) {
                $needsChildNormalizer = true;
                $accessor = sprintf('$this->normalizeChild(%s, $format, $context, %s)', $accessor, $propertyDefinition->isCollection() || $propertyDefinition->hasNoTypeDefinition() ? 'true' : 'false');
            }

            $body .= <<<PHP
    '{$propertyDefinition->getNormalizedName()}' => $accessor,
PHP.\PHP_EOL;
        }

        // public function normalize(mixed $object, string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null;
        $generator->addMethod(Method::create('normalize')
            ->addArgument('object', 'mixed')
            ->addArgument('format', '?string', null)
            ->addArgument('context', 'array', [])
            ->setReturnType('array|string|int|float|bool|\ArrayObject|null')
            ->setComment(sprintf('@param %s $object', $definition->getSourceClassName()))
            ->setBody('return ['.\PHP_EOL.$body.'];')
        );

        if ($needsChildNormalizer) {
            $this->generateNormalizeChildMethod($generator);
        }
    }

    /**
     * When the type-hint has many different classes, then we need to try to denormalize them
     * one by one. We are happy when we dont get any exceptions thrown.
     */
    private function generateCodeToDeserializeMultiplePossibleClasses(array $targetClasses, string $printedCanBeIterable, string $tempVariableName, string $variable, string $keyName, string $classNs): string
    {
        $printedArray = str_replace(\PHP_EOL, '', var_export($targetClasses, true));

        return <<<PHP
\$exceptions = [];
{$tempVariableName}HasValue = false;
foreach ($printedArray as \$class) {
    try {
        {$tempVariableName} = \$this->denormalizeChild($variable, \$class, \$format, \$context, $printedCanBeIterable);
        {$tempVariableName}HasValue = true;
        break;
    } catch (\Throwable \$e) {
        \$exceptions[] = \$e;
    }
}
if (!{$tempVariableName}HasValue) {
    throw new DenormalizingUnionFailedException('Failed to denormalize key "$keyName" of class "$classNs".', \$exceptions);
}


PHP;
    }
}
