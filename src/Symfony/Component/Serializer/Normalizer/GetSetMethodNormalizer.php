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
 * Converts between objects with getter and setter methods and arrays.
 *
 * The normalization process looks at all public methods and calls the ones
 * which have a name starting with get and take no parameters. The result is a
 * map from property names (method name stripped of the get prefix and converted
 * to lower case) to property values. Property values are normalized through the
 * serializer.
 *
 * The denormalization first looks at the constructor of the given class to see
 * if any of the parameters have the same name as one of the properties. The
 * constructor is then called with all parameters or an exception is thrown if
 * any required parameters were not present as properties. Then the denormalizer
 * walks through the given map of property names to property values to see if a
 * setter method exists for any of the properties. If a setter exists it is
 * called with the property value. No automatic denormalization of the value
 * takes place.
 *
 * @author Nils Adermann <naderman@naderman.de>
 */
class GetSetMethodNormalizer extends SerializerAwareNormalizer implements NormalizerInterface, DenormalizerInterface
{
    protected $callbacks = array();
    protected $ignoredAttributes = array();
    protected $camelizedAttributes = array();

    /**
     * Set normalization callbacks.
     *
     * @param callable[] $callbacks help normalize the result
     *
     * @throws InvalidArgumentException if a non-callable callback is set
     *
     * @return GetSetMethodNormalizer
     */
    public function setCallbacks(array $callbacks)
    {
        foreach ($callbacks as $attribute => $callback) {
            if (!is_callable($callback)) {
                throw new InvalidArgumentException(sprintf('The given callback for attribute "%s" is not callable.', $attribute));
            }
        }
        $this->callbacks = $callbacks;

        return $this;
    }

    /**
     * Set ignored attributes for normalization
     *
     * @param array $ignoredAttributes
     *
     * @return GetSetMethodNormalizer
     */
    public function setIgnoredAttributes(array $ignoredAttributes)
    {
        $this->ignoredAttributes = $ignoredAttributes;

        return $this;
    }

    /**
     * Set attributes to be camelized on denormalize
     *
     * @param array $camelizedAttributes
     *
     * @return GetSetMethodNormalizer
     */
    public function setCamelizedAttributes(array $camelizedAttributes)
    {
        $this->camelizedAttributes = $camelizedAttributes;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($object, $format = null, array $context = array())
    {
        $reflectionObject = new \ReflectionObject($object);
        $reflectionMethods = $reflectionObject->getMethods(\ReflectionMethod::IS_PUBLIC);

        $attributes = array();
        foreach ($reflectionMethods as $method) {
            if ($this->isGetMethod($method)) {
                $attributeName = lcfirst(substr($method->name, 0 === strpos($method->name, 'is') ? 2 : 3));

                if (in_array($attributeName, $this->ignoredAttributes)) {
                    continue;
                }

                $attributeValue = $method->invoke($object);
                if (array_key_exists($attributeName, $this->callbacks)) {
                    $attributeValue = call_user_func($this->callbacks[$attributeName], $attributeValue);
                }
                if (null !== $attributeValue && !is_scalar($attributeValue)) {
                    if (!$this->serializer instanceof NormalizerInterface) {
                        throw new \LogicException(sprintf('Cannot normalize attribute "%s" because injected serializer is not a normalizer', $attributeName));
                    }
                    $attributeValue = $this->serializer->normalize($attributeValue, $format);
                }

                $attributes[$attributeName] = $attributeValue;
            }
        }

        return $attributes;
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, $class, $format = null, array $context = array())
    {
        if (is_array($data) || is_object($data) && $data instanceof \ArrayAccess) {
            $normalizedData = $data;
        } elseif (is_object($data)) {
            $normalizedData = array();

            foreach ($data as $attribute => $value) {
                $normalizedData[$attribute] = $value;
            }
        } else {
            $normalizedData = array();
        }

        $reflectionClass = new \ReflectionClass($class);
        $constructor = $reflectionClass->getConstructor();

        if ($constructor) {
            $constructorParameters = $constructor->getParameters();

            $params = array();
            foreach ($constructorParameters as $constructorParameter) {
                $paramName = lcfirst($this->formatAttribute($constructorParameter->name));

                if (isset($normalizedData[$paramName])) {
                    $params[] = $normalizedData[$paramName];
                    // don't run set for a parameter passed to the constructor
                    unset($normalizedData[$paramName]);
                } elseif ($constructorParameter->isOptional()) {
                    $params[] = $constructorParameter->getDefaultValue();
                } else {
                    throw new RuntimeException(
                        'Cannot create an instance of '.$class.
                        ' from serialized data because its constructor requires '.
                        'parameter "'.$constructorParameter->name.
                        '" to be present.');
                }
            }

            $object = $reflectionClass->newInstanceArgs($params);
        } else {
            $object = new $class();
        }

        foreach ($normalizedData as $attribute => $value) {
            $setter = 'set'.$this->formatAttribute($attribute);

            if (method_exists($object, $setter)) {
                $object->$setter($value);
            }
        }

        return $object;
    }

    /**
     * Format attribute name to access parameters or methods
     * As option, if attribute name is found on camelizedAttributes array
     * returns attribute name in camelcase format
     *
     * @param string $attributeName
     * @return string
     */
    protected function formatAttribute($attributeName)
    {
        if (in_array($attributeName, $this->camelizedAttributes)) {
            return preg_replace_callback(
                '/(^|_|\.)+(.)/', function ($match) {
                    return ('.' === $match[1] ? '_' : '').strtoupper($match[2]);
                }, $attributeName
            );
        }

        return $attributeName;
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
     * Checks if the given class has any get{Property} method.
     *
     * @param string $class
     *
     * @return bool
     */
    private function supports($class)
    {
        $class = new \ReflectionClass($class);
        $methods = $class->getMethods(\ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $method) {
            if ($this->isGetMethod($method)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if a method's name is get.* or is.*, and can be called without parameters.
     *
     * @param \ReflectionMethod $method the method to check
     *
     * @return bool whether the method is a getter or boolean getter.
     */
    private function isGetMethod(\ReflectionMethod $method)
    {
        $methodLength = strlen($method->name);

        return (
            ((0 === strpos($method->name, 'get') && 3 < $methodLength) ||
            (0 === strpos($method->name, 'is') && 2 < $methodLength)) &&
            0 === $method->getNumberOfRequiredParameters()
        );
    }
}
