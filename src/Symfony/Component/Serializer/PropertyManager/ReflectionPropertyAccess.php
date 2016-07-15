<?php

namespace Symfony\Component\Serializer\PropertyManager;

use Symfony\Component\Serializer\Exception\LogicException;

/**
 * An easy way to get properties from an object.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class ReflectionPropertyAccess
{
    /**
     * @param object $object
     * @param string $propertyName
     * @param mixed $value
     */
    public function setValue($object, $propertyName, $value)
    {
        $reflectionProperty = $this->getReflectionProperty($object, $propertyName);
        $reflectionProperty->setValue($object, $value);
    }

    /**
     * @param object $object
     * @param string $propertyName
     *
     * @return mixed
     */
    public function getValue($object, $propertyName)
    {
        $reflectionProperty = $this->getReflectionProperty($object, $propertyName);

        return $reflectionProperty->getValue($object);
    }

    /**
     * @param mixed $objectOrClass
     * @param string $propertyName
     *
     * @return \ReflectionProperty
     */
    private function getReflectionProperty($objectOrClass, $propertyName)
    {
        $reflectionClass = new \ReflectionClass($objectOrClass);

        if (!$reflectionClass->hasProperty($propertyName)) {
            if (false === $parent = get_parent_class($objectOrClass)) {
                if (is_object($objectOrClass)) {
                    $objectOrClass = get_class($objectOrClass);
                }
                throw new LogicException(sprintf('There is no property "%s" on class "%s"', $propertyName, $objectOrClass));
            }

            try {
                return $this->getReflectionProperty($parent, $propertyName);
            } catch (LogicException $e) {
                if (is_object($objectOrClass)) {
                    $objectOrClass = get_class($objectOrClass);
                }
                throw new LogicException(sprintf('There is no property "%s" on class "%s"', $propertyName, $objectOrClass), 0, $e);
            }
        }

        $reflectionProperty = $reflectionClass->getProperty($propertyName);
        $reflectionProperty->setAccessible(true);

        return $reflectionProperty;
    }
}
