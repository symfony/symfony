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

use Symfony\Component\PropertyInfo\Extractor\ConstructorArgumentTypeExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyReadInfo;
use Symfony\Component\PropertyInfo\PropertyReadInfoExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyWriteInfoExtractorInterface;
use Symfony\Component\PropertyInfo\Type;

/**
 * Take in a class and extract the definition.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * @experimental in 7.1
 */
class DefinitionExtractor
{
    public function __construct(
        private PropertyInfoExtractorInterface $propertyInfo,
        private PropertyReadInfoExtractorInterface $propertyReadInfoExtractor,
        private PropertyWriteInfoExtractorInterface $propertyWriteInfoExtractor,
        private ConstructorArgumentTypeExtractorInterface $constructorArgumentTypeExtractor,
    ) {
    }

    public function getDefinition(string $classNs): ClassDefinition
    {
        $className = str_replace('\\', '_', ltrim($classNs, '\\'));
        $definition = new ClassDefinition($classNs, $className, 'Symfony\\Serializer\\Normalizer');
        $this->extractProperties($definition);

        return $definition;
    }

    private function extractProperties(ClassDefinition $classDefinition): void
    {
        $classNs = $classDefinition->getNamespaceAndClass();

        /*
         * Extract constructor.
         */
        $reflectionClass = new \ReflectionClass($classNs);
        $constructor = $reflectionClass->getConstructor();
        if (null === $constructor) {
            $classDefinition->setConstructorType(ClassDefinition::CONSTRUCTOR_NONE);
        } elseif (!$constructor->isPublic()) {
            $classDefinition->setConstructorType(ClassDefinition::CONSTRUCTOR_NON_PUBLIC);
        } else {
            $classDefinition->setConstructorType(ClassDefinition::CONSTRUCTOR_PUBLIC);

            foreach ($constructor->getParameters() as $i => $parameter) {
                // We assume the constructor parameter name is the same as the property
                $definition = $this->createOrGetDefinition($classDefinition, $parameter->getName());
                $definition->setConstructorArgumentOrder($i);
                if ($parameter->isDefaultValueAvailable()) {
                    $definition->setConstructorDefaultValue($parameter->getDefaultValue());
                }
                $types = $this->constructorArgumentTypeExtractor->getTypesFromConstructor($classNs, $parameter->getName());
                $this->parseTypes($definition, $types);
            }
        }

        /*
         * Extract properties
         */
        foreach ($this->propertyInfo->getProperties($classNs) ?? [] as $property) {
            $definition = $this->createOrGetDefinition($classDefinition, $property);
            $definition->setIsReadable($this->propertyInfo->isReadable($classNs, $property));
            $definition->setIsWriteable($this->propertyInfo->isWritable($classNs, $property));

            $types = $this->propertyInfo->getTypes($classNs, $property);
            $this->parseTypes($definition, $types);

            $info = $this->propertyReadInfoExtractor->getReadInfo($classNs, $property);
            if (null !== $info && PropertyReadInfo::TYPE_METHOD === $info->getType()) {
                $definition->setGetterName($info->getName());
            }

            $info = $this->propertyWriteInfoExtractor->getWriteInfo($classNs, $property);
            if (null !== $info && PropertyReadInfo::TYPE_METHOD === $info->getType()) {
                $definition->setSetterName($info->getName());
            }
        }
    }

    private function createOrGetDefinition(ClassDefinition $classDefinition, string $property): PropertyDefinition
    {
        $definition = $classDefinition->getDefinition($property);
        if (null === $definition) {
            $definition = new PropertyDefinition($property);
            $classDefinition->addDefinition($definition);
        }

        return $definition;
    }

    /**
     * @param Type[]|null $types
     */
    private function parseTypes(PropertyDefinition $definition, ?array $types): void
    {
        $isCollection = false;
        $targetClasses = [];
        $builtInTypes = [];

        if (null !== $types) {
            foreach ($types as $type) {
                $this->parseType($type, $builtInTypes, $isCollection, $targetClasses);
            }
        }

        // Flip and remove empty values
        $targetClasses = array_keys($targetClasses);
        $targetClasses = array_filter($targetClasses);
        $definition->setNonPrimitiveTypes($targetClasses);
        $definition->setScalarTypes($builtInTypes);
        $definition->setIsCollection($isCollection);
    }

    private function parseType(Type $type, array &$builtInTypes, bool &$isCollection, array &$targetClasses): void
    {
        $builtinType = $type->getBuiltinType();

        if (\in_array($builtinType, ['bool', 'int', 'float', 'string'])) {
            $builtInTypes[] = $builtinType;
        }
        $isCollection = $isCollection || $type->isCollection();
        if (Type::BUILTIN_TYPE_OBJECT === $builtinType) {
            $targetClasses[$type->getClassName()] = true;
        } elseif ($type->isCollection()) {
            foreach ($type->getCollectionValueTypes() as $collectionType) {
                $this->parseType($collectionType, $builtInTypes, $isCollection, $targetClasses);
            }
        }
    }
}
