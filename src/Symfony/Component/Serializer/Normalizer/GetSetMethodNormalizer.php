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
class GetSetMethodNormalizer extends AbstractObjectNormalizer
{
    private static $setterAccessibleCache = [];

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null)
    {
        return parent::supportsNormalization($data, $format) && $this->supports(\get_class($data));
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null)
    {
        return parent::supportsDenormalization($data, $type, $format) && $this->supports($type);
    }

    /**
     * {@inheritdoc}
     */
    public function hasCacheableSupportsMethod(): bool
    {
        return __CLASS__ === static::class;
    }

    /**
     * Checks if the given class has any getter method.
     */
    private function supports(string $class): bool
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
     * Checks if a method's name matches /^(get|is|has).+$/ and can be called non-statically without parameters.
     */
    private function isGetMethod(\ReflectionMethod $method): bool
    {
        $methodLength = \strlen($method->name);

        return
            !$method->isStatic() &&
            (
                ((0 === strpos($method->name, 'get') && 3 < $methodLength) ||
                (0 === strpos($method->name, 'is') && 2 < $methodLength) ||
                (0 === strpos($method->name, 'has') && 3 < $methodLength)) &&
                0 === $method->getNumberOfRequiredParameters()
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function extractAttributes($object, $format = null, array $context = [])
    {
        $reflectionObject = new \ReflectionObject($object);
        $reflectionMethods = $reflectionObject->getMethods(\ReflectionMethod::IS_PUBLIC);

        $attributes = [];
        foreach ($reflectionMethods as $method) {
            if (!$this->isGetMethod($method)) {
                continue;
            }

            $attributeName = lcfirst(substr($method->name, 0 === strpos($method->name, 'is') ? 2 : 3));

            if ($this->isAllowedAttribute($object, $attributeName, $format, $context)) {
                $attributes[] = $attributeName;
            }
        }

        return $attributes;
    }

    /**
     * {@inheritdoc}
     */
    protected function getAttributeValue($object, $attribute, $format = null, array $context = [])
    {
        $ucfirsted = ucfirst($attribute);

        $getter = 'get'.$ucfirsted;
        if (\is_callable([$object, $getter])) {
            return $object->$getter();
        }

        $isser = 'is'.$ucfirsted;
        if (\is_callable([$object, $isser])) {
            return $object->$isser();
        }

        $haser = 'has'.$ucfirsted;
        if (\is_callable([$object, $haser])) {
            return $object->$haser();
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    protected function setAttributeValue($object, $attribute, $value, $format = null, array $context = [])
    {
        $setter = 'set'.ucfirst($attribute);
        $key = \get_class($object).':'.$setter;

        if (!isset(self::$setterAccessibleCache[$key])) {
            self::$setterAccessibleCache[$key] = \is_callable([$object, $setter]) && !(new \ReflectionMethod($object, $setter))->isStatic();
        }

        if (self::$setterAccessibleCache[$key]) {
            $object->$setter($value);
        }
    }
}
