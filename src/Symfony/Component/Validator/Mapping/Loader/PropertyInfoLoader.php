<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Mapping\Loader;

use Symfony\Component\PropertyInfo\PropertyAccessExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\PropertyInfo\Type as PropertyInfoType;
use Symfony\Component\TypeInfo\Type as TypeInfoType;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\CompositeTypeInterface;
use Symfony\Component\TypeInfo\Type\IntersectionType;
use Symfony\Component\TypeInfo\Type\NullableType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\UnionType;
use Symfony\Component\TypeInfo\Type\WrappingTypeInterface;
use Symfony\Component\TypeInfo\TypeIdentifier;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Mapping\AutoMappingStrategy;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * Guesses and loads the appropriate constraints using PropertyInfo.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
final class PropertyInfoLoader implements LoaderInterface
{
    use AutoMappingTrait;

    public function __construct(
        private PropertyListExtractorInterface $listExtractor,
        private PropertyTypeExtractorInterface $typeExtractor,
        private PropertyAccessExtractorInterface $accessExtractor,
        private ?string $classValidatorRegexp = null,
    ) {
    }

    public function loadClassMetadata(ClassMetadata $metadata): bool
    {
        $className = $metadata->getClassName();
        if (!$properties = $this->listExtractor->getProperties($className)) {
            return false;
        }

        $loaded = false;
        $enabledForClass = $this->isAutoMappingEnabledForClass($metadata, $this->classValidatorRegexp);
        foreach ($properties as $property) {
            if (false === $this->accessExtractor->isWritable($className, $property)) {
                continue;
            }

            if (!property_exists($className, $property)) {
                continue;
            }

            $types = $this->getPropertyTypes($className, $property);
            if (null === $types) {
                continue;
            }

            $enabledForProperty = $enabledForClass;
            $hasTypeConstraint = false;
            $hasNotNullConstraint = false;
            $hasNotBlankConstraint = false;
            $allConstraint = null;
            foreach ($metadata->getPropertyMetadata($property) as $propertyMetadata) {
                // Enabling or disabling auto-mapping explicitly always takes precedence
                if (AutoMappingStrategy::DISABLED === $propertyMetadata->getAutoMappingStrategy()) {
                    continue 2;
                }

                if (AutoMappingStrategy::ENABLED === $propertyMetadata->getAutoMappingStrategy()) {
                    $enabledForProperty = true;
                }

                foreach ($propertyMetadata->getConstraints() as $constraint) {
                    if ($constraint instanceof Type) {
                        $hasTypeConstraint = true;
                    } elseif ($constraint instanceof NotNull) {
                        $hasNotNullConstraint = true;
                    } elseif ($constraint instanceof NotBlank) {
                        $hasNotBlankConstraint = true;
                    } elseif ($constraint instanceof All) {
                        $allConstraint = $constraint;
                    }
                }
            }

            if (!$enabledForProperty) {
                continue;
            }

            $loaded = true;

            // BC layer for PropertyTypeExtractorInterface::getTypes().
            // Can be removed as soon as PropertyTypeExtractorInterface::getTypes() is removed (8.0).
            if (\is_array($types)) {
                $builtinTypes = [];
                $nullable = false;
                $scalar = true;

                foreach ($types as $type) {
                    $builtinTypes[] = $type->getBuiltinType();

                    if ($scalar && !\in_array($type->getBuiltinType(), ['int', 'float', 'string', 'bool'], true)) {
                        $scalar = false;
                    }

                    if (!$nullable && $type->isNullable()) {
                        $nullable = true;
                    }
                }

                if (!$hasTypeConstraint) {
                    if (1 === \count($builtinTypes)) {
                        if ($types[0]->isCollection() && \count($collectionValueType = $types[0]->getCollectionValueTypes()) > 0) {
                            [$collectionValueType] = $collectionValueType;
                            $this->handleAllConstraintLegacy($property, $allConstraint, $collectionValueType, $metadata);
                        }

                        $metadata->addPropertyConstraint($property, $this->getTypeConstraintLegacy($builtinTypes[0], $types[0]));
                    } elseif ($scalar) {
                        $metadata->addPropertyConstraint($property, new Type(type: 'scalar'));
                    }
                }
            } else {
                if ($hasTypeConstraint) {
                    continue;
                }

                $type = $types;

                // BC layer for type-info < 7.2
                if (!class_exists(NullableType::class)) {
                    $nullable = false;

                    if ($type instanceof UnionType && $type->isNullable()) {
                        $nullable = true;
                        $type = $type->asNonNullable();
                    }
                } else {
                    $nullable = $type->isNullable();

                    if ($type instanceof NullableType) {
                        $type = $type->getWrappedType();
                    }
                }

                if ($type instanceof NullableType) {
                    $type = $type->getWrappedType();
                }

                if ($type instanceof CollectionType) {
                    $this->handleAllConstraint($property, $allConstraint, $type->getCollectionValueType(), $metadata);
                }

                if (null !== $typeConstraint = $this->getTypeConstraint($type)) {
                    $metadata->addPropertyConstraint($property, $typeConstraint);
                }
            }

            if (!$nullable && !$hasNotBlankConstraint && !$hasNotNullConstraint) {
                $metadata->addPropertyConstraint($property, new NotNull());
            }
        }

        return $loaded;
    }

    /**
     * BC layer for PropertyTypeExtractorInterface::getTypes().
     * Can be removed as soon as PropertyTypeExtractorInterface::getTypes() is removed (8.0).
     *
     * @return TypeInfoType|list<PropertyInfoType>|null
     */
    private function getPropertyTypes(string $className, string $property): TypeInfoType|array|null
    {
        if (class_exists(TypeInfoType::class) && method_exists($this->typeExtractor, 'getType')) {
            return $this->typeExtractor->getType($className, $property);
        }

        return $this->typeExtractor->getTypes($className, $property);
    }

    /**
     * BC layer for PropertyTypeExtractorInterface::getTypes().
     * Can be removed as soon as PropertyTypeExtractorInterface::getTypes() is removed (8.0).
     */
    private function getTypeConstraintLegacy(string $builtinType, PropertyInfoType $type): Type
    {
        if (PropertyInfoType::BUILTIN_TYPE_OBJECT === $builtinType && null !== $className = $type->getClassName()) {
            return new Type(type: $className);
        }

        return new Type(type: $builtinType);
    }

    private function getTypeConstraint(TypeInfoType $type): ?Type
    {
        // BC layer for type-info < 7.2
        if (!interface_exists(CompositeTypeInterface::class)) {
            if ($type instanceof UnionType || $type instanceof IntersectionType) {
                return ($type->isA(TypeIdentifier::INT) || $type->isA(TypeIdentifier::FLOAT) || $type->isA(TypeIdentifier::STRING) || $type->isA(TypeIdentifier::BOOL)) ? new Type(['type' => 'scalar']) : null;
            }

            $baseType = $type->getBaseType();

            if ($baseType instanceof ObjectType) {
                return new Type(type: $baseType->getClassName());
            }

            if (TypeIdentifier::MIXED !== $baseType->getTypeIdentifier()) {
                return new Type(type: $baseType->getTypeIdentifier()->value);
            }

            return null;
        }

        if ($type instanceof CompositeTypeInterface) {
            return $type->isIdentifiedBy(
                TypeIdentifier::INT,
                TypeIdentifier::FLOAT,
                TypeIdentifier::STRING,
                TypeIdentifier::BOOL,
                TypeIdentifier::TRUE,
                TypeIdentifier::FALSE,
            ) ? new Type(type: 'scalar') : null;
        }

        while ($type instanceof WrappingTypeInterface) {
            $type = $type->getWrappedType();
        }

        if ($type instanceof ObjectType) {
            return new Type(type: $type->getClassName());
        }

        if ($type instanceof BuiltinType && TypeIdentifier::MIXED !== $type->getTypeIdentifier()) {
            return new Type(type: $type->getTypeIdentifier()->value);
        }

        return null;
    }

    private function handleAllConstraint(string $property, ?All $allConstraint, TypeInfoType $type, ClassMetadata $metadata): void
    {
        $containsTypeConstraint = false;
        $containsNotNullConstraint = false;
        if (null !== $allConstraint) {
            foreach ($allConstraint->constraints as $constraint) {
                if ($constraint instanceof Type) {
                    $containsTypeConstraint = true;
                } elseif ($constraint instanceof NotNull) {
                    $containsNotNullConstraint = true;
                }
            }
        }

        $constraints = [];
        if (!$containsNotNullConstraint && !$type->isNullable()) {
            $constraints[] = new NotNull();
        }

        if (!$containsTypeConstraint && null !== $typeConstraint = $this->getTypeConstraint($type)) {
            $constraints[] = $typeConstraint;
        }

        if (!$constraints) {
            return;
        }

        if (null === $allConstraint) {
            $metadata->addPropertyConstraint($property, new All(constraints: $constraints));
        } else {
            $allConstraint->constraints = array_merge($allConstraint->constraints, $constraints);
        }
    }

    /**
     * BC layer for PropertyTypeExtractorInterface::getTypes().
     * Can be removed as soon as PropertyTypeExtractorInterface::getTypes() is removed (8.0).
     */
    private function handleAllConstraintLegacy(string $property, ?All $allConstraint, PropertyInfoType $propertyInfoType, ClassMetadata $metadata): void
    {
        $containsTypeConstraint = false;
        $containsNotNullConstraint = false;
        if (null !== $allConstraint) {
            foreach ($allConstraint->constraints as $constraint) {
                if ($constraint instanceof Type) {
                    $containsTypeConstraint = true;
                } elseif ($constraint instanceof NotNull) {
                    $containsNotNullConstraint = true;
                }
            }
        }

        $constraints = [];
        if (!$containsNotNullConstraint && !$propertyInfoType->isNullable()) {
            $constraints[] = new NotNull();
        }

        if (!$containsTypeConstraint) {
            $constraints[] = $this->getTypeConstraintLegacy($propertyInfoType->getBuiltinType(), $propertyInfoType);
        }

        if (null === $allConstraint) {
            $metadata->addPropertyConstraint($property, new All(constraints: $constraints));
        } else {
            $allConstraint->constraints = array_merge($allConstraint->constraints, $constraints);
        }
    }
}
