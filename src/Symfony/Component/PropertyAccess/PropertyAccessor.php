<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess;

use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException;

/**
 * Default implementation of {@link PropertyAccessorInterface}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PropertyAccessor implements PropertyAccessorInterface
{
    /**
     * @internal
     */
    const VALUE = 0;

    /**
     * @internal
     */
    const IS_REF = 1;

    /**
     * @internal
     */
    const ACCESS_HAS_PROPERTY = 0;

    /**
     * @internal
     */
    const ACCESS_TYPE = 1;

    /**
     * @internal
     */
    const ACCESS_NAME = 2;

    /**
     * @internal
     */
    const ACCESS_REF = 3;

    /**
     * @internal
     */
    const ACCESS_ADDER = 4;

    /**
     * @internal
     */
    const ACCESS_REMOVER = 5;

    /**
     * @internal
     */
    const ACCESS_TYPE_METHOD = 0;

    /**
     * @internal
     */
    const ACCESS_TYPE_PROPERTY = 1;

    /**
     * @internal
     */
    const ACCESS_TYPE_MAGIC = 2;

    /**
     * @internal
     */
    const ACCESS_TYPE_ADDER_AND_REMOVER = 3;

    /**
     * @internal
     */
    const ACCESS_TYPE_NOT_FOUND = 4;

    private $magicCall;
    private $readPropertyCache = array();
    private $writePropertyCache = array();

    /**
     * Should not be used by application code. Use
     * {@link PropertyAccess::createPropertyAccessor()} instead.
     */
    public function __construct($magicCall = false)
    {
        $this->magicCall = $magicCall;
    }

    /**
     * {@inheritdoc}
     */
    public function getValue($objectOrArray, $propertyPath)
    {
        if (!$propertyPath instanceof PropertyPathInterface) {
            $propertyPath = new PropertyPath($propertyPath);
        }

        $propertyValues = &$this->readPropertiesUntil($objectOrArray, $propertyPath, $propertyPath->getLength());

        return $propertyValues[count($propertyValues) - 1][self::VALUE];
    }

    /**
     * {@inheritdoc}
     */
    public function setValue(&$objectOrArray, $propertyPath, $value)
    {
        if (!$propertyPath instanceof PropertyPathInterface) {
            $propertyPath = new PropertyPath($propertyPath);
        }

        $propertyValues = &$this->readPropertiesUntil($objectOrArray, $propertyPath, $propertyPath->getLength() - 1);
        $overwrite = true;

        // Add the root object to the list
        array_unshift($propertyValues, array(
            self::VALUE => &$objectOrArray,
            self::IS_REF => true,
        ));

        for ($i = count($propertyValues) - 1; $i >= 0; --$i) {
            $objectOrArray = &$propertyValues[$i][self::VALUE];

            if ($overwrite) {
                $property = $propertyPath->getElement($i);
                //$singular = $propertyPath->singulars[$i];
                $singular = null;

                if ($propertyPath->isIndex($i)) {
                    $this->writeIndex($objectOrArray, $property, $value);
                } else {
                    $this->writeProperty($objectOrArray, $property, $singular, $value);
                }
            }

            $value = &$objectOrArray;
            $overwrite = !$propertyValues[$i][self::IS_REF];
        }
    }

    /**
     * Reads the path from an object up to a given path index.
     *
     * @param object|array          $objectOrArray The object or array to read from
     * @param PropertyPathInterface $propertyPath  The property path to read
     * @param int                   $lastIndex     The index up to which should be read
     *
     * @return array The values read in the path.
     *
     * @throws UnexpectedTypeException If a value within the path is neither object nor array.
     */
    private function &readPropertiesUntil(&$objectOrArray, PropertyPathInterface $propertyPath, $lastIndex)
    {
        if (!is_object($objectOrArray) && !is_array($objectOrArray)) {
            throw new UnexpectedTypeException($objectOrArray, 'object or array');
        }

        $propertyValues = array();

        for ($i = 0; $i < $lastIndex; ++$i) {
            $property = $propertyPath->getElement($i);
            $isIndex = $propertyPath->isIndex($i);

            // Create missing nested arrays on demand
            if (
                $isIndex &&
                (
                    ($objectOrArray instanceof \ArrayAccess && !isset($objectOrArray[$property])) ||
                    (is_array($objectOrArray) && !array_key_exists($property, $objectOrArray))
                )
            ) {
                if ($i + 1 < $propertyPath->getLength()) {
                    $objectOrArray[$property] = array();
                }
            }

            if ($isIndex) {
                $propertyValue = &$this->readIndex($objectOrArray, $property);
            } else {
                $propertyValue = &$this->readProperty($objectOrArray, $property);
            }

            $objectOrArray = &$propertyValue[self::VALUE];

            // the final value of the path must not be validated
            if ($i + 1 < $propertyPath->getLength() && !is_object($objectOrArray) && !is_array($objectOrArray)) {
                throw new UnexpectedTypeException($objectOrArray, 'object or array');
            }

            $propertyValues[] = &$propertyValue;
        }

        return $propertyValues;
    }

    /**
     * Reads a key from an array-like structure.
     *
     * @param \ArrayAccess|array $array The array or \ArrayAccess object to read from
     * @param string|int         $index The key to read
     *
     * @return mixed The value of the key
     *
     * @throws NoSuchPropertyException If the array does not implement \ArrayAccess or it is not an array
     */
    private function &readIndex(&$array, $index)
    {
        if (!$array instanceof \ArrayAccess && !is_array($array)) {
            throw new NoSuchPropertyException(sprintf('Index "%s" cannot be read from object of type "%s" because it doesn\'t implement \ArrayAccess', $index, get_class($array)));
        }

        // Use an array instead of an object since performance is very crucial here
        $result = array(
            self::VALUE => null,
            self::IS_REF => false,
        );

        if (isset($array[$index])) {
            if (is_array($array)) {
                $result[self::VALUE] = &$array[$index];
                $result[self::IS_REF] = true;
            } else {
                $result[self::VALUE] = $array[$index];
                // Objects are always passed around by reference
                $result[self::IS_REF] = is_object($array[$index]) ? true : false;
            }
        }

        return $result;
    }

    /**
     * Reads the a property from an object or array.
     *
     * @param object $object   The object to read from.
     * @param string $property The property to read.
     *
     * @return mixed The value of the read property
     *
     * @throws NoSuchPropertyException If the property does not exist or is not
     *                                 public.
     */
    private function &readProperty(&$object, $property)
    {
        // Use an array instead of an object since performance is
        // very crucial here
        $result = array(
            self::VALUE => null,
            self::IS_REF => false,
        );

        if (!is_object($object)) {
            throw new NoSuchPropertyException(sprintf('Cannot read property "%s" from an array. Maybe you should write the property path as "[%s]" instead?', $property, $property));
        }

        $access = $this->getReadAccessInfo($object, $property);

        if (self::ACCESS_TYPE_METHOD === $access[self::ACCESS_TYPE]) {
            $result[self::VALUE] = $object->{$access[self::ACCESS_NAME]}();
        } elseif (self::ACCESS_TYPE_PROPERTY === $access[self::ACCESS_TYPE]) {
            if ($access[self::ACCESS_REF]) {
                $result[self::VALUE] = &$object->{$access[self::ACCESS_NAME]};
                $result[self::IS_REF] = true;
            } else {
                $result[self::VALUE] = $object->{$access[self::ACCESS_NAME]};
            }
        } elseif (!$access[self::ACCESS_HAS_PROPERTY] && property_exists($object, $property)) {
            // Needed to support \stdClass instances. We need to explicitly
            // exclude $classHasProperty, otherwise if in the previous clause
            // a *protected* property was found on the class, property_exists()
            // returns true, consequently the following line will result in a
            // fatal error.

            $result[self::VALUE] = &$object->$property;
            $result[self::IS_REF] = true;
        } elseif (self::ACCESS_TYPE_MAGIC === $access[self::ACCESS_TYPE]) {
            // we call the getter and hope the __call do the job
            $result[self::VALUE] = $object->{$access[self::ACCESS_NAME]}();
        } else {
            throw new NoSuchPropertyException($access[self::ACCESS_NAME]);
        }

        // Objects are always passed around by reference
        if (is_object($result[self::VALUE])) {
            $result[self::IS_REF] = true;
        }

        return $result;
    }

    /**
     * Guesses how to read the property value.
     *
     * @param string $object
     * @param string $property
     *
     * @return array
     */
    private function getReadAccessInfo($object, $property)
    {
        $key = get_class($object).'::'.$property;

        if (isset($this->readPropertyCache[$key])) {
            $access = $this->readPropertyCache[$key];
        } else {
            $access = array();

            $reflClass = new \ReflectionClass($object);
            $access[self::ACCESS_HAS_PROPERTY] = $reflClass->hasProperty($property);
            $camelProp = $this->camelize($property);
            $getter = 'get'.$camelProp;
            $isser = 'is'.$camelProp;
            $hasser = 'has'.$camelProp;
            $classHasProperty = $reflClass->hasProperty($property);

            if ($reflClass->hasMethod($getter) && $reflClass->getMethod($getter)->isPublic()) {
                $access[self::ACCESS_TYPE] = self::ACCESS_TYPE_METHOD;
                $access[self::ACCESS_NAME] = $getter;
            } elseif ($reflClass->hasMethod($isser) && $reflClass->getMethod($isser)->isPublic()) {
                $access[self::ACCESS_TYPE] = self::ACCESS_TYPE_METHOD;
                $access[self::ACCESS_NAME] = $isser;
            } elseif ($reflClass->hasMethod($hasser) && $reflClass->getMethod($hasser)->isPublic()) {
                $access[self::ACCESS_TYPE] = self::ACCESS_TYPE_METHOD;
                $access[self::ACCESS_NAME] = $hasser;
            } elseif ($reflClass->hasMethod('__get') && $reflClass->getMethod('__get')->isPublic()) {
                $access[self::ACCESS_TYPE] = self::ACCESS_TYPE_PROPERTY;
                $access[self::ACCESS_NAME] = $property;
                $access[self::ACCESS_REF] = false;
            } elseif ($classHasProperty && $reflClass->getProperty($property)->isPublic()) {
                $access[self::ACCESS_TYPE] = self::ACCESS_TYPE_PROPERTY;
                $access[self::ACCESS_NAME] = $property;
                $access[self::ACCESS_REF] = true;

                $result[self::VALUE] = &$object->$property;
                $result[self::IS_REF] = true;
            } elseif ($this->magicCall && $reflClass->hasMethod('__call') && $reflClass->getMethod('__call')->isPublic()) {
                // we call the getter and hope the __call do the job
                $access[self::ACCESS_TYPE] = self::ACCESS_TYPE_MAGIC;
                $access[self::ACCESS_NAME] = $getter;
            } else {
                $methods = array($getter, $isser, $hasser, '__get');
                if ($this->magicCall) {
                    $methods[] = '__call';
                }

                $access[self::ACCESS_TYPE] = self::ACCESS_TYPE_NOT_FOUND;
                $access[self::ACCESS_NAME] = sprintf(
                    'Neither the property "%s" nor one of the methods "%s()" '.
                    'exist and have public access in class "%s".',
                    $property,
                    implode('()", "', $methods),
                    $reflClass->name
                );
            }

            $this->readPropertyCache[$key] = $access;
        }

        return $access;
    }

    /**
     * Sets the value of the property at the given index in the path.
     *
     * @param \ArrayAccess|array $array An array or \ArrayAccess object to write to
     * @param string|int         $index The index to write at
     * @param mixed              $value The value to write
     *
     * @throws NoSuchPropertyException If the array does not implement \ArrayAccess or it is not an array
     */
    private function writeIndex(&$array, $index, $value)
    {
        if (!$array instanceof \ArrayAccess && !is_array($array)) {
            throw new NoSuchPropertyException(sprintf('Index "%s" cannot be modified in object of type "%s" because it doesn\'t implement \ArrayAccess', $index, get_class($array)));
        }

        $array[$index] = $value;
    }

    /**
     * Sets the value of the property at the given index in the path.
     *
     * @param object|array $object   The object or array to write to
     * @param string       $property The property to write
     * @param string|null  $singular The singular form of the property name or null
     * @param mixed        $value    The value to write
     *
     * @throws NoSuchPropertyException If the property does not exist or is not
     *                                 public.
     */
    private function writeProperty(&$object, $property, $singular, $value)
    {
        if (!is_object($object)) {
            throw new NoSuchPropertyException(sprintf('Cannot write property "%s" to an array. Maybe you should write the property path as "[%s]" instead?', $property, $property));
        }

        $access = $this->getWriteAccessInfo($object, $property, $singular, $value);

        if (self::ACCESS_TYPE_METHOD === $access[self::ACCESS_TYPE]) {
            $object->{$access[self::ACCESS_NAME]}($value);
        } elseif (self::ACCESS_TYPE_PROPERTY === $access[self::ACCESS_TYPE]) {
            $object->{$access[self::ACCESS_NAME]} = $value;
        } elseif (self::ACCESS_TYPE_ADDER_AND_REMOVER === $access[self::ACCESS_TYPE]) {
            // At this point the add and remove methods have been found
            // Use iterator_to_array() instead of clone in order to prevent side effects
            // see https://github.com/symfony/symfony/issues/4670
            $itemsToAdd = is_object($value) ? iterator_to_array($value) : $value;
            $itemToRemove = array();
            $propertyValue = &$this->readProperty($object, $property);
            $previousValue = $propertyValue[self::VALUE];
            // remove reference to avoid modifications
            unset($propertyValue);

            if (is_array($previousValue) || $previousValue instanceof \Traversable) {
                foreach ($previousValue as $previousItem) {
                    foreach ($value as $key => $item) {
                        if ($item === $previousItem) {
                            // Item found, don't add
                            unset($itemsToAdd[$key]);

                            // Next $previousItem
                            continue 2;
                        }
                    }

                    // Item not found, add to remove list
                    $itemToRemove[] = $previousItem;
                }
            }

            foreach ($itemToRemove as $item) {
                $object->{$access[self::ACCESS_REMOVER]}($item);
            }

            foreach ($itemsToAdd as $item) {
                $object->{$access[self::ACCESS_ADDER]}($item);
            }
        } elseif (!$access[self::ACCESS_HAS_PROPERTY] && property_exists($object, $property)) {
            // Needed to support \stdClass instances. We need to explicitly
            // exclude $classHasProperty, otherwise if in the previous clause
            // a *protected* property was found on the class, property_exists()
            // returns true, consequently the following line will result in a
            // fatal error.

            $object->$property = $value;
        } elseif (self::ACCESS_TYPE_MAGIC === $access[self::ACCESS_TYPE]) {
            $object->{$access[self::ACCESS_NAME]}($value);
        } else {
            throw new NoSuchPropertyException($access[self::ACCESS_NAME]);
        }
    }

    /**
     * Guesses how to write the property value.
     *
     * @param string      $object
     * @param string      $property
     * @param string|null $singular
     * @param mixed       $value
     *
     * @return array
     */
    private function getWriteAccessInfo($object, $property, $singular, $value)
    {
        $key = get_class($object).'::'.$property;
        $guessedAdders = '';

        if (isset($this->writePropertyCache[$key])) {
            $access = $this->writePropertyCache[$key];
        } else {
            $access = array();

            $reflClass = new \ReflectionClass($object);
            $access[self::ACCESS_HAS_PROPERTY] = $reflClass->hasProperty($property);
            $plural = $this->camelize($property);

            // Any of the two methods is required, but not yet known
            $singulars = null !== $singular ? array($singular) : (array) StringUtil::singularify($plural);

            if (is_array($value) || $value instanceof \Traversable) {
                $methods = $this->findAdderAndRemover($reflClass, $singulars);

                if (null === $methods) {
                    // It is sufficient to include only the adders in the error
                    // message. If the user implements the adder but not the remover,
                    // an exception will be thrown in findAdderAndRemover() that
                    // the remover has to be implemented as well.
                    $guessedAdders = '"add'.implode('()", "add', $singulars).'()", ';
                } else {
                    $access[self::ACCESS_TYPE] = self::ACCESS_TYPE_ADDER_AND_REMOVER;
                    $access[self::ACCESS_ADDER] = $methods[0];
                    $access[self::ACCESS_REMOVER] = $methods[1];
                }
            }

            if (!isset($access[self::ACCESS_TYPE])) {
                $setter = 'set'.$this->camelize($property);
                $classHasProperty = $reflClass->hasProperty($property);

                if ($reflClass->hasMethod($setter) && $reflClass->getMethod($setter)->isPublic()) {
                    $access[self::ACCESS_TYPE] = self::ACCESS_TYPE_METHOD;
                    $access[self::ACCESS_NAME] = $setter;
                } elseif ($reflClass->hasMethod('__set') && $reflClass->getMethod('__set')->isPublic()) {
                    $access[self::ACCESS_TYPE] = self::ACCESS_TYPE_PROPERTY;
                    $access[self::ACCESS_NAME] = $property;
                } elseif ($classHasProperty && $reflClass->getProperty($property)->isPublic()) {
                    $access[self::ACCESS_TYPE] = self::ACCESS_TYPE_PROPERTY;
                    $access[self::ACCESS_NAME] = $property;
                } elseif ($this->magicCall && $reflClass->hasMethod('__call') && $reflClass->getMethod('__call')->isPublic()) {
                    // we call the getter and hope the __call do the job
                    $access[self::ACCESS_TYPE] = self::ACCESS_TYPE_MAGIC;
                    $access[self::ACCESS_NAME] = $setter;
                } else {
                    $access[self::ACCESS_TYPE] = self::ACCESS_TYPE_NOT_FOUND;
                    $access[self::ACCESS_NAME] = sprintf(
                        'Neither the property "%s" nor one of the methods %s"%s()", '.
                        '"__set()" or "__call()" exist and have public access in class "%s".',
                        $property,
                        $guessedAdders,
                        $setter,
                        $reflClass->name
                    );
                }
            }

            $this->writePropertyCache[$key] = $access;
        }

        return $access;
    }

    /**
     * Camelizes a given string.
     *
     * @param string $string Some string
     *
     * @return string The camelized version of the string
     */
    private function camelize($string)
    {
        return preg_replace_callback('/(^|_|\.)+(.)/', function ($match) { return ('.' === $match[1] ? '_' : '').strtoupper($match[2]); }, $string);
    }

    /**
     * Searches for add and remove methods.
     *
     * @param \ReflectionClass $reflClass The reflection class for the given object
     * @param array            $singulars The singular form of the property name or null
     *
     * @return array|null An array containing the adder and remover when found, null otherwise
     *
     * @throws NoSuchPropertyException If the property does not exist
     */
    private function findAdderAndRemover(\ReflectionClass $reflClass, array $singulars)
    {
        foreach ($singulars as $singular) {
            $addMethod = 'add'.$singular;
            $removeMethod = 'remove'.$singular;

            $addMethodFound = $this->isAccessible($reflClass, $addMethod, 1);
            $removeMethodFound = $this->isAccessible($reflClass, $removeMethod, 1);

            if ($addMethodFound && $removeMethodFound) {
                return array($addMethod, $removeMethod);
            }

            if ($addMethodFound xor $removeMethodFound) {
                throw new NoSuchPropertyException(sprintf(
                    'Found the public method "%s()", but did not find a public "%s()" on class %s',
                    $addMethodFound ? $addMethod : $removeMethod,
                    $addMethodFound ? $removeMethod : $addMethod,
                    $reflClass->name
                ));
            }
        }
    }

    /**
     * Returns whether a method is public and has a specific number of required parameters.
     *
     * @param \ReflectionClass $class      The class of the method
     * @param string           $methodName The method name
     * @param int              $parameters The number of parameters
     *
     * @return bool Whether the method is public and has $parameters
     *              required parameters
     */
    private function isAccessible(\ReflectionClass $class, $methodName, $parameters)
    {
        if ($class->hasMethod($methodName)) {
            $method = $class->getMethod($methodName);

            if ($method->isPublic() && $method->getNumberOfRequiredParameters() === $parameters) {
                return true;
            }
        }

        return false;
    }
}
