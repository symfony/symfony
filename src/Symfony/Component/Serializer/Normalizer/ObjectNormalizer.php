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
use Symfony\Component\Serializer\Exception\CircularReferenceException;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

/**
 * Converts between objects and arrays using the PropertyAccess component.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class ObjectNormalizer extends AbstractNormalizer
{
    private static $attributesCache = array();

    /**
     * @var PropertyAccessorInterface
     */
    protected $propertyAccessor;

    public function __construct(ClassMetadataFactoryInterface $classMetadataFactory = null, NameConverterInterface $nameConverter = null, PropertyAccessorInterface $propertyAccessor = null)
    {
        parent::__construct($classMetadataFactory, $nameConverter);

        $this->propertyAccessor = $propertyAccessor ?: PropertyAccess::createPropertyAccessor();
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null)
    {
        return is_object($data) && !$data instanceof \Traversable;
    }

    /**
     * {@inheritdoc}
     *
     * @throws CircularReferenceException
     */
    public function normalize($object, $format = null, array $context = array())
    {
        if ($this->isCircularReference($object, $context)) {
            return $this->handleCircularReference($object);
        }

        $data = array();
        $stack = array();
        $attributes = $this->getAttributes($object, $context);

        foreach ($attributes as $attribute) {
            if (!$this->isAttributeToNormalize($object, $attribute, $context)) {
                continue;
            }

            $attributeValue = $this->propertyAccessor->getValue($object, $attribute);

            if (isset($this->callbacks[$attribute])) {
                $attributeValue = call_user_func($this->callbacks[$attribute], $attributeValue);
            }

            // Recursive call are done at the end of the process to allow @MaxDepth to work
            if (null !== $attributeValue && !is_scalar($attributeValue)) {
                $stack[$attribute] = $attributeValue;
                continue;
            }

            $data = $this->setAttribute($data, $attribute, $attributeValue);
        }

        return $this->normalizeComplexTypes($data, $stack, $format, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null)
    {
        return class_exists($type);
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, $class, $format = null, array $context = array())
    {
        $allowedAttributes = $this->getAllowedAttributes($class, $context, true);
        $normalizedData = $this->prepareForDenormalization($data);

        $reflectionClass = new \ReflectionClass($class);
        $object = $this->instantiateObject($normalizedData, $class, $context, $reflectionClass, $allowedAttributes);

        foreach ($normalizedData as $attribute => $value) {
            if ($this->nameConverter) {
                $attribute = $this->nameConverter->denormalize($attribute);
            }

            $allowed = $allowedAttributes === false || in_array($attribute, $allowedAttributes);
            $ignored = in_array($attribute, $this->ignoredAttributes);

            if ($allowed && !$ignored) {
                try {
                    $this->propertyAccessor->setValue($object, $attribute, $value);
                } catch (NoSuchPropertyException $exception) {
                    // Properties not found are ignored
                }
            }
        }

        return $object;
    }

    /**
     * Gets and caches attributes for this class and context.
     *
     * @param object $object
     * @param array  $context
     *
     * @return array
     */
    private function getAttributes($object, array $context)
    {
        $key = sprintf('%s-%s', get_class($object), serialize($context));

        if (isset(self::$attributesCache[$key])) {
            return self::$attributesCache[$key];
        }

        $allowedAttributes = $this->getAllowedAttributes($object, $context, true);

        if (false !== $allowedAttributes) {
            return self::$attributesCache[$key] = $allowedAttributes;
        }

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

            $name = $reflMethod->getName();

            if (strpos($name, 'get') === 0 || strpos($name, 'has') === 0) {
                // getters and hassers
                $attributes[lcfirst(substr($name, 3))] = true;
            } elseif (strpos($name, 'is') === 0) {
                // issers
                $attributes[lcfirst(substr($name, 2))] = true;
            }
        }

        // properties
        foreach ($reflClass->getProperties(\ReflectionProperty::IS_PUBLIC) as $reflProperty) {
            if ($reflProperty->isStatic()) {
                continue;
            }

            $attributes[$reflProperty->getName()] = true;
        }

        return self::$attributesCache[$key] = array_keys($attributes);
    }
}
