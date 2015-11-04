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
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class GetSetMethodNormalizer extends AbstractNormalizer
{
    /**
     * {@inheritdoc}
     *
     * @throws LogicException
     * @throws CircularReferenceException
     */
    public function normalize($object, $format = null, array $context = array())
    {
        if ($this->isCircularReference($object, $context)) {
            return $this->handleCircularReference($object);
        }

        $reflectionObject = new \ReflectionObject($object);
        $reflectionMethods = $reflectionObject->getMethods(\ReflectionMethod::IS_PUBLIC);
        $allowedAttributes = $this->getAllowedAttributes($object, $context, true);

        $attributes = array();
        foreach ($reflectionMethods as $method) {
            if ($this->isGetMethod($method)) {
                $attributeName = lcfirst(substr($method->name, 0 === strpos($method->name, 'is') ? 2 : 3));
                if (in_array($attributeName, $this->ignoredAttributes)) {
                    continue;
                }

                if (false !== $allowedAttributes && !in_array($attributeName, $allowedAttributes)) {
                    continue;
                }

                $attributeValue = $method->invoke($object);
                if (isset($this->callbacks[$attributeName])) {
                    $attributeValue = call_user_func($this->callbacks[$attributeName], $attributeValue);
                }
                if (null !== $attributeValue && !is_scalar($attributeValue)) {
                    if (!$this->serializer instanceof NormalizerInterface) {
                        throw new LogicException(sprintf('Cannot normalize attribute "%s" because injected serializer is not a normalizer', $attributeName));
                    }

                    $attributeValue = $this->serializer->normalize($attributeValue, $format, $context);
                }

                if ($this->nameConverter) {
                    $attributeName = $this->nameConverter->normalize($attributeName);
                }

                $attributes[$attributeName] = $attributeValue;
            }
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
        $normalizedData = $this->prepareForDenormalization($data);

        $reflectionClass = new \ReflectionClass($class);
        $object = $this->instantiateObject($normalizedData, $class, $context, $reflectionClass, $allowedAttributes);

        $classMethods = get_class_methods($object);
        foreach ($normalizedData as $attribute => $value) {
            if ($this->nameConverter) {
                $attribute = $this->nameConverter->denormalize($attribute);
            }

            $allowed = $allowedAttributes === false || in_array($attribute, $allowedAttributes);
            $ignored = in_array($attribute, $this->ignoredAttributes);

            if ($allowed && !$ignored) {
                $setter = 'set'.ucfirst($attribute);

                if (in_array($setter, $classMethods) && !$reflectionClass->getMethod($setter)->isStatic()) {
                    $object->$setter($value);
                }
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

        return
            !$method->isStatic() &&
            (
                ((0 === strpos($method->name, 'get') && 3 < $methodLength) ||
                (0 === strpos($method->name, 'is') && 2 < $methodLength)) &&
                0 === $method->getNumberOfRequiredParameters()
            )
        ;
    }
}
