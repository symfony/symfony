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

use Symfony\Component\Serializer\Annotation\Ignore;

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
    private static $reflectionCache = [];
    private static $setterAccessibleCache = [];

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, ?string $format = null)
    {
        return parent::supportsNormalization($data, $format) && $this->supports(\get_class($data), true);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, string $type, ?string $format = null)
    {
        return parent::supportsDenormalization($data, $type, $format) && $this->supports($type, false);
    }

    /**
     * {@inheritdoc}
     */
    public function hasCacheableSupportsMethod(): bool
    {
        return __CLASS__ === static::class;
    }

    /**
     * Checks if the given class has any getter or setter method.
     */
    private function supports(string $class, bool $readAttributes): bool
    {
        if (null !== $this->classDiscriminatorResolver && $this->classDiscriminatorResolver->getMappingForClass($class)) {
            return true;
        }

        if (!isset(self::$reflectionCache[$class])) {
            self::$reflectionCache[$class] = new \ReflectionClass($class);
        }

        $reflection = self::$reflectionCache[$class];

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $reflectionMethod) {
            if ($readAttributes ? $this->isGetMethod($reflectionMethod) : $this->isSetMethod($reflectionMethod)) {
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
        return !$method->isStatic()
            && (\PHP_VERSION_ID < 80000 || !$method->getAttributes(Ignore::class))
            && !$method->getNumberOfRequiredParameters()
            && ((2 < ($methodLength = \strlen($method->name)) && str_starts_with($method->name, 'is'))
                || (3 < $methodLength && (str_starts_with($method->name, 'has') || str_starts_with($method->name, 'get')))
            );
    }

    /**
     * Checks if a method's name matches /^set.+$/ and can be called non-statically with one parameter.
     */
    private function isSetMethod(\ReflectionMethod $method): bool
    {
        return !$method->isStatic()
            && (\PHP_VERSION_ID < 80000 || !$method->getAttributes(Ignore::class))
            && 0 < $method->getNumberOfParameters()
            && str_starts_with($method->name, 'set');
    }

    /**
     * {@inheritdoc}
     */
    protected function extractAttributes(object $object, ?string $format = null, array $context = [])
    {
        $reflectionObject = new \ReflectionObject($object);
        $reflectionMethods = $reflectionObject->getMethods(\ReflectionMethod::IS_PUBLIC);

        $attributes = [];
        foreach ($reflectionMethods as $method) {
            if (!$this->isGetMethod($method)) {
                continue;
            }

            $attributeName = lcfirst(substr($method->name, str_starts_with($method->name, 'is') ? 2 : 3));

            if ($this->isAllowedAttribute($object, $attributeName, $format, $context)) {
                $attributes[] = $attributeName;
            }
        }

        return $attributes;
    }

    /**
     * {@inheritdoc}
     */
    protected function getAttributeValue(object $object, string $attribute, ?string $format = null, array $context = [])
    {
        $getter = 'get'.$attribute;
        if (method_exists($object, $getter) && \is_callable([$object, $getter])) {
            return $object->$getter();
        }

        $isser = 'is'.$attribute;
        if (method_exists($object, $isser) && \is_callable([$object, $isser])) {
            return $object->$isser();
        }

        $haser = 'has'.$attribute;
        if (method_exists($object, $haser) && \is_callable([$object, $haser])) {
            return $object->$haser();
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    protected function setAttributeValue(object $object, string $attribute, $value, ?string $format = null, array $context = [])
    {
        $setter = 'set'.$attribute;
        $key = \get_class($object).':'.$setter;

        if (!isset(self::$setterAccessibleCache[$key])) {
            self::$setterAccessibleCache[$key] = method_exists($object, $setter) && \is_callable([$object, $setter]) && !(new \ReflectionMethod($object, $setter))->isStatic();
        }

        if (self::$setterAccessibleCache[$key]) {
            $object->$setter($value);
        }
    }

    protected function isAllowedAttribute($classOrObject, string $attribute, ?string $format = null, array $context = [])
    {
        if (!parent::isAllowedAttribute($classOrObject, $attribute, $format, $context)) {
            return false;
        }

        $class = \is_object($classOrObject) ? \get_class($classOrObject) : $classOrObject;

        if (!isset(self::$reflectionCache[$class])) {
            self::$reflectionCache[$class] = new \ReflectionClass($class);
        }

        $reflection = self::$reflectionCache[$class];

        if ($context['_read_attributes'] ?? true) {
            foreach (['get', 'is', 'has'] as $getterPrefix) {
                $getter = $getterPrefix.$attribute;
                $reflectionMethod = $reflection->hasMethod($getter) ? $reflection->getMethod($getter) : null;
                if ($reflectionMethod && $this->isGetMethod($reflectionMethod)) {
                    return true;
                }
            }

            return false;
        }

        $setter = 'set'.$attribute;
        if ($reflection->hasMethod($setter) && $this->isSetMethod($reflection->getMethod($setter))) {
            return true;
        }

        $constructor = $reflection->getConstructor();

        if ($constructor && $constructor->isPublic()) {
            foreach ($constructor->getParameters() as $parameter) {
                if ($parameter->getName() === $attribute) {
                    return true;
                }
            }
        }

        return false;
    }
}
