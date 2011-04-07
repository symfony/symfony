<?php

namespace Symfony\Component\Serializer\Normalizer;

use Symfony\Component\Serializer\SerializerInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

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
class GetSetMethodNormalizer extends AbstractNormalizer
{
    /**
     * {@inheritdoc}
     */
    public function normalize($object, $format, $properties = null)
    {
        $propertyMap = (null === $properties) ? null : array_flip(array_map('strtolower', $properties));

        $reflectionObject = new \ReflectionObject($object);
        $reflectionMethods = $reflectionObject->getMethods(\ReflectionMethod::IS_PUBLIC);

        $attributes = array();
        foreach ($reflectionMethods as $method) {
            if ($this->isGetMethod($method)) {
                $attributeName = strtolower(substr($method->getName(), 3));

                if (null === $propertyMap || isset($propertyMap[$attributeName])) {
                    $attributeValue = $method->invoke($object);
                    if ($this->serializer->isStructuredType($attributeValue)) {
                        $attributeValue = $this->serializer->normalize($attributeValue, $format);
                    }

                    $attributes[$attributeName] = $attributeValue;
                }
            }
        }

        return $attributes;
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, $class, $format = null)
    {
        $reflectionClass = new \ReflectionClass($class);
        $constructor = $reflectionClass->getConstructor();

        if ($constructor) {
            $constructorParameters = $constructor->getParameters();

            $params = array();
            foreach ($constructorParameters as $constructorParameter) {
                $paramName = strtolower($constructorParameter->getName());

                if (isset($data[$paramName])) {
                    $params[] = $data[$paramName];
                    // don't run set for a parameter passed to the constructor
                    unset($data[$paramName]);
                } else if (!$constructorParameter->isOptional()) {
                    throw new \RuntimeException(
                        'Cannot create an instance of ' . $class .
                        ' from serialized data because its constructor requires ' .
                        'parameter "' . $constructorParameter->getName() .
                        '" to be present.');
                }
            }

            $object = $reflectionClass->newInstanceArgs($params);
        } else {
            $object = new $class;
        }

        foreach ($data as $attribute => $value) {
            $setter = 'set' . $attribute;
            if (method_exists($object, $setter)) {
                $object->$setter($value);
            }
        }

        return $object;
    }

    /**
     * Checks if the given class has any get{Property} method.
     *
     * @param  ReflectionClass $class  A ReflectionClass instance of the class
     *                                 to serialize into or from.
     * @param  string $format The format being (de-)serialized from or into.
     * @return Boolean Whether the class has any getters.
     */
    public function supports(\ReflectionClass $class, $format = null)
    {
        $methods = $class->getMethods(\ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $method) {
            if ($this->isGetMethod($method)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if a method's name is get.* and can be called without parameters.
     *
     * @param ReflectionMethod $method the method to check
     * @return Boolean whether the method is a getter.
     */
    private function isGetMethod(\ReflectionMethod $method)
    {
        return (
            0 === strpos($method->getName(), 'get') &&
            3 < strlen($method->getName()) &&
            0 === $method->getNumberOfRequiredParameters()
        );
    }
}
