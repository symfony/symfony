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
class PropertyNormalizer extends AbstractObjectNormalizer
{
    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null)
    {
        return parent::supportsNormalization($data, $format) && $this->supports(get_class($data));
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null)
    {
        return parent::supportsDenormalization($data, $type, $format) && $this->supports($type);
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
            if (!$property->isStatic()) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     *
     * Ignores allowed attributes not applicable on properties (e.g. groups set on a getter method).
     */
    protected function getAllowedAttributes($classOrObject, array $context, $attributesAsString = false)
    {
        $allowedAttributes = parent::getAllowedAttributes($classOrObject, $context, $attributesAsString);

        if (false === $allowedAttributes) {
            return false;
        }

        $class = is_string($classOrObject) ? $classOrObject : get_class($classOrObject);

        $attributes = array();
        foreach ($allowedAttributes as $allowedAttribute) {
            $propertyName = $attributesAsString ? $allowedAttribute : $allowedAttribute->getName();

            try {
                $reflectionProperty = new \ReflectionProperty($class, $propertyName);
                if (!$reflectionProperty->isStatic()) {
                    $attributes[] = $allowedAttribute;
                }
            } catch (\ReflectionException $reflectionException) {
                // Ignore attributes not applicable for this normalizer
            }
        }

        return $attributes;
    }

    /**
     * {@inheritdoc}
     */
    protected function extractAttributes($object, $format = null, array $context = array())
    {
        $reflectionObject = new \ReflectionObject($object);
        $attributes = array();

        foreach ($reflectionObject->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $attributes[] = $property->name;
        }

        return $attributes;
    }

    /**
     * {@inheritdoc}
     */
    protected function getAttributeValue($object, $attribute, $format = null, array $context = array())
    {
        try {
            $reflectionProperty = new \ReflectionProperty(get_class($object), $attribute);
        } catch (\ReflectionException $reflectionException) {
            return;
        }

        // Override visibility
        if (!$reflectionProperty->isPublic()) {
            $reflectionProperty->setAccessible(true);
        }

        return $reflectionProperty->getValue($object);
    }

    /**
     * {@inheritdoc}
     */
    protected function setAttributeValue($object, $attribute, $value, $format = null, array $context = array())
    {
        try {
            $reflectionProperty = new \ReflectionProperty(get_class($object), $attribute);
        } catch (\ReflectionException $reflectionException) {
            return;
        }

        if ($reflectionProperty->isStatic()) {
            return;
        }

        // Override visibility
        if (!$reflectionProperty->isPublic()) {
            $reflectionProperty->setAccessible(true);
        }

        $reflectionProperty->setValue($object, $value);
    }
}
