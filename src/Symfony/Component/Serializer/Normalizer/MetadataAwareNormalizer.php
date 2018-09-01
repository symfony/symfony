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

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\Serializer\Annotation\ExclusionPolicy;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorResolverInterface;
use Symfony\Component\Serializer\Mapping\ClassMetadataInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

/**
 * Converts between objects and arrays using the Reflection and respect the metadata.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
final class MetadataAwareNormalizer extends AbstractObjectNormalizer
{
    /**
     * @var PropertyAccessorInterface
     */
    protected $propertyAccessor;

    public function __construct(ClassMetadataFactoryInterface $classMetadataFactory = null, NameConverterInterface $nameConverter = null, PropertyTypeExtractorInterface $propertyTypeExtractor = null, ClassDiscriminatorResolverInterface $classDiscriminatorResolver = null, PropertyAccessorInterface $propertyAccessor = null) {
        if (null === $propertyAccessor) {
            $propertyAccessor = PropertyAccess::createPropertyAccessor();
        }
        $this->propertyAccessor = $propertyAccessor;
        parent::__construct($classMetadataFactory, $nameConverter, $propertyTypeExtractor, $classDiscriminatorResolver);
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
                0 !== $reflMethod->getNumberOfRequiredParameters() ||
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

        if (null !== $function = $attributeMetadata[$attribute]->getMethodsAccessor()) {
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

        if (null !== $function = $attributeMetadata[$attribute]->getMethodsMutator()) {
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

        if (ExclusionPolicy::ALL === $classMetadata->getExclusionPolicy()) {
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
        if (!\is_object($data)) {
            return false;
        }

        if (!$this->classMetadataFactory) {
            return false;
        }

        return true;
    }

    /**
     * Update data for normalization.
     *
     * {@inheritdoc}
     */
    protected function updateData(array $data, string  $attribute, $attributeValue/*, $object*/): array
    {
        if (3 === \func_num_args()) {
            @trigger_error('Fourth argument to MetadataAwareNormalizer must be the object.', E_USER_DEPRECATED);

            return $data;
        }
        $object = func_get_arg(3);


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
    protected function prepareForDenormalization($data/*, string $class*/)
    {
        if (1 === \func_num_args()) {
            @trigger_error('Second argument to MetadataAwareNormalizer must be the class name', E_USER_DEPRECATED);
            return (array) $data;
        }

        $class =  func_get_arg(1);
        $preparedData = array();
        $data = (array) $data;
        /** @var ClassMetadataInterface $classMetadata */
        $classMetadata = $this->classMetadataFactory->getMetadataFor($class);
        $attributeMetadata = $classMetadata->getAttributesMetadata();

        // We should not do anything if the class is read only.
        if (true === $classMetadata->getReadOnly()) {
            return array();
        }

        $validSerializedKeys = array();
        foreach ($attributeMetadata as $attributeName => $metadata) {
            $attributeReadOnly = $metadata->getReadOnly();
            if (true === $attributeReadOnly || false !== $attributeReadOnly) {
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
            if (\in_array($serializedKeyName, $validSerializedKeyNames)) {
                // Replace with the keys for the serialized attribute
                $preparedData[$validSerializedKeys[$serializedKeyName]] = $value;
            }
        }

        // Assert: The keys in this array are the attribute name passed throw the nameConverter::normalize
        return $preparedData;
    }
}
