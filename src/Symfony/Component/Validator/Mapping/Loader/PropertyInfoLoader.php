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

    private PropertyListExtractorInterface $listExtractor;
    private PropertyTypeExtractorInterface $typeExtractor;
    private PropertyAccessExtractorInterface $accessExtractor;
    private ?string $classValidatorRegexp;

    public function __construct(PropertyListExtractorInterface $listExtractor, PropertyTypeExtractorInterface $typeExtractor, PropertyAccessExtractorInterface $accessExtractor, string $classValidatorRegexp = null)
    {
        $this->listExtractor = $listExtractor;
        $this->typeExtractor = $typeExtractor;
        $this->accessExtractor = $accessExtractor;
        $this->classValidatorRegexp = $classValidatorRegexp;
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

            $types = $this->typeExtractor->getTypes($className, $property);
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
            $builtinTypes = [];
            $nullable = false;
            $scalar = true;
            foreach ($types as $type) {
                $builtinTypes[] = $type->getBuiltinType();

                if ($scalar && !\in_array($type->getBuiltinType(), [PropertyInfoType::BUILTIN_TYPE_INT, PropertyInfoType::BUILTIN_TYPE_FLOAT, PropertyInfoType::BUILTIN_TYPE_STRING, PropertyInfoType::BUILTIN_TYPE_BOOL], true)) {
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
                        $this->handleAllConstraint($property, $allConstraint, $collectionValueType, $metadata);
                    }

                    $metadata->addPropertyConstraint($property, $this->getTypeConstraint($builtinTypes[0], $types[0]));
                } elseif ($scalar) {
                    $metadata->addPropertyConstraint($property, new Type(['type' => 'scalar']));
                }
            }

            if (!$nullable && !$hasNotBlankConstraint && !$hasNotNullConstraint) {
                $metadata->addPropertyConstraint($property, new NotNull());
            }
        }

        return $loaded;
    }

    private function getTypeConstraint(string $builtinType, PropertyInfoType $type): Type
    {
        if (PropertyInfoType::BUILTIN_TYPE_OBJECT === $builtinType && null !== $className = $type->getClassName()) {
            return new Type(['type' => $className]);
        }

        return new Type(['type' => $builtinType]);
    }

    private function handleAllConstraint(string $property, ?All $allConstraint, PropertyInfoType $propertyInfoType, ClassMetadata $metadata)
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
            $constraints[] = $this->getTypeConstraint($propertyInfoType->getBuiltinType(), $propertyInfoType);
        }

        if (null === $allConstraint) {
            $metadata->addPropertyConstraint($property, new All(['constraints' => $constraints]));
        } else {
            $allConstraint->constraints = array_merge($allConstraint->constraints, $constraints);
        }
    }
}
