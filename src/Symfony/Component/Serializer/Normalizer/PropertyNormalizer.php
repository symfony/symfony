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

use Symfony\Component\Serializer\Exception\InvalidArgumentException;
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
 */
class PropertyNormalizer extends SerializerAwareNormalizer implements NormalizerInterface, DenormalizerInterface
{
    private $callbacks = array();
    private $ignoredAttributes = array();
    private $camelizedAttributes = array();

    /**
     * Set normalization callbacks
     *
     * @param array $callbacks help normalize the result
     *
     * @throws InvalidArgumentException if a non-callable callback is set
     */
    public function setCallbacks(array $callbacks)
    {
        foreach ($callbacks as $attribute => $callback) {
            if (!is_callable($callback)) {
                throw new InvalidArgumentException(sprintf(
                    'The given callback for attribute "%s" is not callable.',
                    $attribute
                ));
            }
        }
        $this->callbacks = $callbacks;
    }

    /**
     * Set ignored attributes for normalization.
     *
     * @param array $ignoredAttributes
     */
    public function setIgnoredAttributes(array $ignoredAttributes)
    {
        $this->ignoredAttributes = $ignoredAttributes;
    }

    /**
     * Set attributes to be camelized on denormalize
     *
     * @param array $camelizedAttributes
     */
    public function setCamelizedAttributes(array $camelizedAttributes)
    {
        $this->camelizedAttributes = $camelizedAttributes;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($object, $format = null, array $context = array())
    {
        $reflectionObject = new \ReflectionObject($object);
        $attributes = array();

        foreach ($reflectionObject->getProperties() as $property) {
            if (in_array($property->name, $this->ignoredAttributes)) {
                continue;
            }

            // Override visibility
            if (! $property->isPublic()) {
                $property->setAccessible(true);
            }

            $attributeValue = $property->getValue($object);

            if (array_key_exists($property->name, $this->callbacks)) {
                $attributeValue = call_user_func($this->callbacks[$property->name], $attributeValue);
            }
            if (null !== $attributeValue && !is_scalar($attributeValue)) {
                $attributeValue = $this->serializer->normalize($attributeValue, $format);
            }

            $attributes[$property->name] = $attributeValue;
        }

        return $attributes;
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, $class, $format = null, array $context = array())
    {
        $reflectionClass = new \ReflectionClass($class);
        $constructor = $reflectionClass->getConstructor();

        if ($constructor) {
            $constructorParameters = $constructor->getParameters();

            $params = array();
            foreach ($constructorParameters as $constructorParameter) {
                $paramName = lcfirst($this->formatAttribute($constructorParameter->name));

                if (isset($data[$paramName])) {
                    $params[] = $data[$paramName];
                    // don't run set for a parameter passed to the constructor
                    unset($data[$paramName]);
                } elseif (!$constructorParameter->isOptional()) {
                    throw new RuntimeException(sprintf(
                        'Cannot create an instance of %s from serialized data because '.
                        'its constructor requires parameter "%s" to be present.',
                        $class,
                        $constructorParameter->name
                    ));
                }
            }

            $object = $reflectionClass->newInstanceArgs($params);
        } else {
            $object = new $class();
        }

        foreach ($data as $propertyName => $value) {
            $propertyName = lcfirst($this->formatAttribute($propertyName));

            if ($reflectionClass->hasProperty($propertyName)) {
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
        return is_object($data) && $this->supports(get_class($data));
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null)
    {
        return $this->supports($type);
    }

    /**
     * Format an attribute name, for example to convert a snake_case name to camelCase.
     *
     * @param  string $attributeName
     *
     * @return string
     */
    protected function formatAttribute($attributeName)
    {
        if (in_array($attributeName, $this->camelizedAttributes)) {
            return preg_replace_callback('/(^|_|\.)+(.)/', function ($match) {
                return ('.' === $match[1] ? '_' : '').strtoupper($match[2]);
            }, $attributeName);
        }

        return $attributeName;
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
