<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Normalizer;

use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Mapping\AttributeMetadata;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorResolverInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

/**
 * Converts between objects and arrays using the PropertyAccess component.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class ObjectNormalizer extends AbstractObjectNormalizer
{
    protected $propertyAccessor;

    private $discriminatorCache = [];

    private $objectClassResolver;

    public function __construct(ClassMetadataFactoryInterface $classMetadataFactory = null, NameConverterInterface $nameConverter = null, PropertyAccessorInterface $propertyAccessor = null, PropertyTypeExtractorInterface $propertyTypeExtractor = null, ClassDiscriminatorResolverInterface $classDiscriminatorResolver = null, callable $objectClassResolver = null, array $defaultContext = [])
    {
        if (!class_exists(PropertyAccess::class)) {
            throw new LogicException('The ObjectNormalizer class requires the "PropertyAccess" component. Install "symfony/property-access" to use it.');
        }

        parent::__construct($classMetadataFactory, $nameConverter, $propertyTypeExtractor, $classDiscriminatorResolver, $objectClassResolver, $defaultContext);

        $this->propertyAccessor = $propertyAccessor ?: PropertyAccess::createPropertyAccessor();

        $this->objectClassResolver = $objectClassResolver ?? function ($class) {
            return \is_object($class) ? \get_class($class) : $class;
        };
    }

    /**
     * {@inheritdoc}
     */
    public function hasCacheableSupportsMethod(): bool
    {
        return __CLASS__ === \get_class($this);
    }

    /**
     * {@inheritdoc}
     */
    protected function extractAttributes($object, $format = null, array $context = [])
    {
        // If not using groups, detect manually
        $attributes = [];

        // methods
        $class = ($this->objectClassResolver)($object);
        $reflClass = new \ReflectionClass($class);

        foreach ($reflClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $reflMethod) {
            if (
                0 !== $reflMethod->getNumberOfRequiredParameters() ||
                $reflMethod->isStatic() ||
                $reflMethod->isConstructor() ||
                $reflMethod->isDestructor()
            ) {
                continue;
            }

            $name = $reflMethod->name;
            $attributeName = null;

            if (0 === strpos($name, 'get') || 0 === strpos($name, 'has')) {
                // getters and hassers
                $attributeName = substr($name, 3);

                if (!$reflClass->hasProperty($attributeName)) {
                    $attributeName = lcfirst($attributeName);
                }
            } elseif (0 === strpos($name, 'is')) {
                // issers
                $attributeName = substr($name, 2);

                if (!$reflClass->hasProperty($attributeName)) {
                    $attributeName = lcfirst($attributeName);
                }
            }

            if (null !== $attributeName && $this->isAllowedAttribute($object, $attributeName, $format, $context)) {
                $attributes[$attributeName] = true;
            }
        }

        $checkPropertyInitialization = \PHP_VERSION_ID >= 70400;

        // properties
        foreach ($reflClass->getProperties(\ReflectionProperty::IS_PUBLIC) as $reflProperty) {
            if ($checkPropertyInitialization && !$reflProperty->isInitialized($object)) {
                continue;
            }

            if ($reflProperty->isStatic() || !$this->isAllowedAttribute($object, $reflProperty->name, $format, $context)) {
                continue;
            }

            $attributes[$reflProperty->name] = true;
        }

        return array_keys($attributes);
    }

    /**
     * {@inheritdoc}
     */
    protected function getAttributeValue($object, $attribute, $format = null, array $context = [])
    {
        $cacheKey = \get_class($object);
        if (!\array_key_exists($cacheKey, $this->discriminatorCache)) {
            $this->discriminatorCache[$cacheKey] = null;
            if (null !== $this->classDiscriminatorResolver) {
                $mapping = $this->classDiscriminatorResolver->getMappingForMappedObject($object);
                $this->discriminatorCache[$cacheKey] = null === $mapping ? null : $mapping->getTypeProperty();
            }
        }

        return $attribute === $this->discriminatorCache[$cacheKey] ? $this->classDiscriminatorResolver->getTypeForMappedObject($object) : $this->propertyAccessor->getValue($object, $attribute);
    }

    /**
     * {@inheritdoc}
     */
    protected function setAttributeValue($object, $attribute, $value, $format = null, array $context = [])
    {
        try {
            $this->propertyAccessor->setValue($object, $attribute, $value);
        } catch (NoSuchPropertyException $exception) {
            // Properties not found are ignored
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getAllowedAttributes($classOrObject, array $context, $attributesAsString = false)
    {
        if (false === $allowedAttributes = parent::getAllowedAttributes($classOrObject, $context, $attributesAsString)) {
            return false;
        }

        if (null !== $this->classDiscriminatorResolver) {
            $class = \is_object($classOrObject) ? \get_class($classOrObject) : $classOrObject;
            if (null !== $discriminatorMapping = $this->classDiscriminatorResolver->getMappingForMappedObject($classOrObject)) {
                $allowedAttributes[] = $attributesAsString ? $discriminatorMapping->getTypeProperty() : new AttributeMetadata($discriminatorMapping->getTypeProperty());
            }

            if (null !== $discriminatorMapping = $this->classDiscriminatorResolver->getMappingForClass($class)) {
                foreach ($discriminatorMapping->getTypesMapping() as $mappedClass) {
                    $allowedAttributes = array_merge($allowedAttributes, parent::getAllowedAttributes($mappedClass, $context, $attributesAsString));
                }
            }
        }

        return $allowedAttributes;
    }
}
