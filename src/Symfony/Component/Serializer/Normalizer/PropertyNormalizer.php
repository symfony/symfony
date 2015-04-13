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

use Symfony\Component\Serializer\Exception\CircularReferenceException;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Exception\RuntimeException;

/**
 * Converts between objects and arrays by mapping properties.
 *
 * The normalization process looks for all the object's properties (public and private).
 * The result is a map from property names to property values. Property values
 * are normalized through the serializer.
 *
 * The denormalization first looks at the constructor of the given class to see
 * if any of the parameters have the same name as one of the properties. The
 * constructor is then called with all parameters or an exception is thrown if
 * any required parameters were not present as properties. Then the denormalizer
 * walks through the given map of property names to property values to see if a
 * property with the corresponding name exists. If found, the property gets the value.
 *
 * @author Matthieu Napoli <matthieu@mnapoli.fr>
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class PropertyNormalizer extends AbstractNormalizer
{
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

        $reflectionObject = new \ReflectionObject($object);
        $attributes = array();
        $allowedAttributes = $this->getAllowedAttributes($object, $context, true);

        foreach ($reflectionObject->getProperties() as $property) {
            if (in_array($property->name, $this->ignoredAttributes)) {
                continue;
            }

            if (false !== $allowedAttributes && !in_array($property->name, $allowedAttributes)) {
                continue;
            }

            // Override visibility
            if (! $property->isPublic()) {
                $property->setAccessible(true);
            }

            $attributeValue = $property->getValue($object);

            if (isset($this->callbacks[$property->name])) {
                $attributeValue = call_user_func($this->callbacks[$property->name], $attributeValue);
            }
            if (null !== $attributeValue && !is_scalar($attributeValue)) {
                if (!$this->serializer instanceof NormalizerInterface) {
                    throw new LogicException(sprintf('Cannot normalize attribute "%s" because injected serializer is not a normalizer', $property->name));
                }

                $attributeValue = $this->serializer->normalize($attributeValue, $format, $context);
            }

            $propertyName = $property->name;
            if ($this->nameConverter) {
                $propertyName = $this->nameConverter->normalize($propertyName);
            }

            $attributes[$propertyName] = $attributeValue;
        }

        return $attributes;
    }

    /**
     * {@inheritdoc}
     *
     * @throws RuntimeException
     */
    public function denormalize($data, $class, $format = null, array $context = array())
    {
        $allowedAttributes = $this->getAllowedAttributes($class, $context, true);
        $data = $this->prepareForDenormalization($data);

        $reflectionClass = new \ReflectionClass($class);
        $object = $this->instantiateObject($data, $class, $context, $reflectionClass, $allowedAttributes);

        foreach ($data as $propertyName => $value) {
            if ($this->nameConverter) {
                $propertyName = $this->nameConverter->denormalize($propertyName);
            }

            $allowed = $allowedAttributes === false || in_array($propertyName, $allowedAttributes);
            $ignored = in_array($propertyName, $this->ignoredAttributes);
            if ($allowed && !$ignored && $reflectionClass->hasProperty($propertyName)) {
                $property = $reflectionClass->getProperty($propertyName);

                // Override visibility
                if (! $property->isPublic()) {
                    $property->setAccessible(true);
                }

                $property->setValue($object, $value);
            }
        }

        return $object;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null)
    {
        return is_object($data) && !$data instanceof \Traversable && $this->supports(get_class($data));
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null)
    {
        return class_exists($type) && $this->supports($type);
    }

    /**
     * Checks if the given class has any non-static property.
     *
     * @param string $class
     *
     * @return bool
     */
    private function supports($class)
    {
        $class = new \ReflectionClass($class);

        // We look for at least one non-static property
        foreach ($class->getProperties() as $property) {
            if (! $property->isStatic()) {
                return true;
            }
        }

        return false;
    }
}
