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
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\Serializer\Annotation\ExclusionPolicy;
use Symfony\Component\Serializer\Mapping\AttributeMetadataInterface;
use Symfony\Component\Serializer\Mapping\ClassMetadataInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\Serializer\PropertyManager\ReflectionPropertyAccess;

/**
 * Converts between objects and arrays using the Reflection and respect the metadata.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class MetadataAwareNormalizer extends AbstractObjectNormalizer
{
    /**
     * @var ReflectionPropertyAccess
     */
    protected $propertyAccessor;

    public function __construct(
        ClassMetadataFactoryInterface $classMetadataFactory,
        NameConverterInterface $nameConverter = null,
        PropertyAccessorInterface $propertyAccessor = null,
        PropertyTypeExtractorInterface $propertyTypeExtractor = null)
    {
        parent::__construct($classMetadataFactory, $nameConverter, $propertyTypeExtractor);

        $this->propertyAccessor = new ReflectionPropertyAccess();
    }

    /**
     * {@inheritdoc}
     */
    protected function extractAttributes($object, $format = null, array $context = array())
    {
        // If not using groups, detect manually
        $attributes = array();

        // methods
        $reflClass = new \ReflectionClass($object);
        foreach ($reflClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $reflMethod) {
            if (
                $reflMethod->getNumberOfRequiredParameters() !== 0 ||
                $reflMethod->isStatic() ||
                $reflMethod->isConstructor() ||
                $reflMethod->isDestructor()
            ) {
                continue;
            }

            $attributeName = $reflMethod->name;
            if (null !== $attributeName && $this->isAllowedAttribute($object, $attributeName, $format, $context)) {
                $attributes[$attributeName] = true;
            }
        }

        // properties
        foreach ($reflClass->getProperties() as $reflProperty) {
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
    protected function getAttributeValue($object, $attribute, $format = null, array $context = array())
    {
        /** @var ClassMetadataInterface $classMetadata */
        $classMetadata = $this->classMetadataFactory->getMetadataFor($object);
        $attributeMetadata = $classMetadata->getAttributesMetadata();

        if (null !== $function = $attributeMetadata[$attribute]->getAccessorGetter()) {
            return $object->$function();
        }

        return $this->propertyAccessor->getValue($object, $attribute);
    }

    /**
     * {@inheritdoc}
     */
    protected function setAttributeValue($object, $attribute, $value, $format = null, array $context = array())
    {
        /** @var ClassMetadataInterface $classMetadata */
        $classMetadata = $this->classMetadataFactory->getMetadataFor($object);
        $attributeMetadata = $classMetadata->getAttributesMetadata();

        if (null !== $function = $attributeMetadata[$attribute]->getAccessorSetter()) {
            return $object->$function($value);
        }

        return $this->propertyAccessor->setValue($object, $attribute, $value);
    }

    /**
     * Is this attribute allowed?
     *
     * @param object|string $classOrObject
     * @param string        $attribute
     * @param string|null   $format
     * @param array         $context
     *
     * @return bool
     */
    protected function isAllowedAttribute($classOrObject, $attribute, $format = null, array $context = array())
    {
        /** @var ClassMetadataInterface $classMetadata */
        $classMetadata = $this->classMetadataFactory->getMetadataFor($classOrObject);
        $attributeMetadata = $classMetadata->getAttributesMetadata();

        if (true === $attributeMetadata[$attribute]->getExclude()) {
            return false;
        }

        if (true === $attributeMetadata[$attribute]->getExpose()) {
            return true;
        }

        if ($classMetadata->getExclusionPolicy() === ExclusionPolicy::ALL) {
            return false;
        }

        // If no exclusion policy or ExclusionPolicy::NONE
        return parent::isAllowedAttribute($classOrObject, $attribute, $format, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null)
    {
        if (!is_object($data)) {
            return false;
        }

        if (!$this->classMetadataFactory) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function instantiateObject(array &$data, $class, array &$context, \ReflectionClass $reflectionClass, $allowedAttributes)
    {
        if (
            isset($context[AbstractNormalizer::OBJECT_TO_POPULATE]) &&
            is_object($context[AbstractNormalizer::OBJECT_TO_POPULATE]) &&
            $context[AbstractNormalizer::OBJECT_TO_POPULATE] instanceof $class
        ) {
            $object = $context[AbstractNormalizer::OBJECT_TO_POPULATE];
            unset($context[AbstractNormalizer::OBJECT_TO_POPULATE]);

            return $object;
        }

        $reflectionClass = new \ReflectionClass($class);
        $constructor = $reflectionClass->getConstructor();
        if (!$constructor) {
            return new $class();
        }

        return $reflectionClass->newInstanceWithoutConstructor();
    }

    /**
     * Update data for normalization.
     *
     * {@inheritdoc}
     */
    protected function updateData(array $data, $attribute, $attributeValue, $object)
    {
        /** @var ClassMetadataInterface $classMetadata */
        $classMetadata = $this->classMetadataFactory->getMetadataFor($object);
        $attributeMetadata = $classMetadata->getAttributesMetadata();

        if (null !== $name = $attributeMetadata[$attribute]->getSerializedName()) {
            $attribute = $name;
        } elseif ($this->nameConverter) {
            $attribute = $this->nameConverter->normalize($attribute);
        }

        $data[$attribute] = $attributeValue;

        return $data;
    }

    /**
     * Prepare data for denormalization.
     *
     * {@inheritdoc}
     */
    protected function prepareForDenormalization($data, $class)
    {
        $preparedData = [];
        $data = (array) $data;
        /** @var ClassMetadataInterface $classMetadata */
        $classMetadata = $this->classMetadataFactory->getMetadataFor($class);
        $attributeMetadata = $classMetadata->getAttributesMetadata();
        $classReadOnly = true === $classMetadata->getReadOnly();

        $validSerializedKeys = [];
        foreach ($attributeMetadata as $attributeName => $metadata) {
            $attributeReadOnly = $metadata->getReadOnly();
            if ($attributeReadOnly === true || ($classReadOnly && $attributeReadOnly !== false)) {
                // This is not a valid key
                continue;
            }

            // Get the special serialized name
            if (null === $serializedName = $metadata->getSerializedName()) {
                $serializedName = $attributeName;
            }

            $serializedAttributeName = $attributeName;
            if ($this->nameConverter) {
                // We want to convert this no matter if we got a serialized name in the meta or not
                $serializedName = $this->nameConverter->normalize($serializedName);
                $serializedAttributeName = $this->nameConverter->normalize($attributeName);
            }

            $validSerializedKeys[$serializedName] = $serializedAttributeName;
        }

        $validSerializedKeyNames = array_keys($validSerializedKeys);
        foreach ($data as $serializedKeyName => $value) {
            if (in_array($serializedKeyName, $validSerializedKeyNames)) {
                // Replace with the keys for the serialized attribute
                $preparedData[$validSerializedKeys[$serializedKeyName]] = $value;
            }
        }

        // Assert: The keys in this array are the attribute name passed throw the nameConverter::normalize
        return $preparedData;
    }
}
