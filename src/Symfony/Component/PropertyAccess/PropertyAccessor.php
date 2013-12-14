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
use Symfony\Component\PropertyAccess\Exception\NoSuchIndexException;
use Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException;

/**
 * Default implementation of {@link PropertyAccessorInterface}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PropertyAccessor implements PropertyAccessorInterface
{
    const VALUE = 0;
    const IS_REF = 1;

    const METHOD_PROPERTY = "@prop";
    const METHOD_GETTER = "@getter";
    const METHOD_MAGIC_GETTER = "@magic-getter";
    const METHOD_HASSER = "@hasser";
    const METHOD_ISSER = "@isser";

    /**
     * @var Boolean
     */
    private $magicCall;

    /**
     * @var Boolean
     */
    private $throwExceptionOnInvalidIndex;

    /**
     * Should not be used by application code. Use
     * {@link PropertyAccess::createPropertyAccessor()} instead.
     */
    public function __construct($magicCall = false, $throwExceptionOnInvalidIndex = false)
    {
        $this->magicCall = $magicCall;
        $this->throwExceptionOnInvalidIndex = $throwExceptionOnInvalidIndex;
    }

    /**
     * {@inheritdoc}
     */
    public function getValue($objectOrArray, $propertyPath)
    {
        $propertyPath = $this->getPropertyPath($propertyPath);

        $propertyValues =& $this->readPropertiesUntil($objectOrArray, $propertyPath, $propertyPath->getLength(), $this->throwExceptionOnInvalidIndex);

        return $propertyValues[count($propertyValues) - 1][self::VALUE];
    }

    /**
     * {@inheritdoc}
     */
    public function setValue(&$objectOrArray, $propertyPath, $value)
    {
        $propertyPath = $this->getPropertyPath($propertyPath);

        $propertyValues =& $this->readPropertiesUntil($objectOrArray, $propertyPath, $propertyPath->getLength() - 1);
        $overwrite = true;

        // Add the root object to the list
        array_unshift($propertyValues, array(
            self::VALUE => &$objectOrArray,
            self::IS_REF => true,
        ));

        for ($i = count($propertyValues) - 1; $i >= 0; --$i) {
            $objectOrArray =& $propertyValues[$i][self::VALUE];

            if ($overwrite) {
                if (!is_object($objectOrArray) && !is_array($objectOrArray)) {
                    throw new UnexpectedTypeException($objectOrArray, 'object or array');
                }

                $property = $propertyPath->getElement($i);
                //$singular = $propertyPath->singulars[$i];
                $singular = null;

                if ($propertyPath->isIndex($i)) {
                    $this->writeIndex($objectOrArray, $property, $value);
                } else {
                    $this->writeProperty($objectOrArray, $property, $singular, $value);
                }
            }

            $value =& $objectOrArray;
            $overwrite = !$propertyValues[$i][self::IS_REF];
        }
    }

    /**
     * @param object|array $objectOrArray
     * @param string $propertyName
     *
     * @return Boolean
     */
    public function isAccessible($objectOrArray, $propertyName)
    {
        if ($this->isArrayAccess($objectOrArray)) {
            return isset($objectOrArray[$propertyName]);
        }
        try {
            $this->getAccessMethod($objectOrArray, $propertyName);

            return true;
        } catch (NoSuchPropertyException $e) {
            return false;
        }
    }

    /**
     * Reads the path from an object up to a given path index.
     *
     * @param object|array          $objectOrArray The object or array to read from
     * @param PropertyPathInterface $propertyPath  The property path to read
     * @param integer               $lastIndex     The index up to which should be read
     * @param Boolean               $throwExceptionOnNonexistantIndex if the exception should thrown on invalid index
     *
     * @return array The values read in the path.
     *
     * @throws UnexpectedTypeException If a value within the path is neither object nor array.
     * @throws NoSuchIndexException if path is not found and $throwExceptionOnNonexistantIndex flag is true
     */
    private function &readPropertiesUntil(&$objectOrArray, PropertyPathInterface $propertyPath, $lastIndex, $throwExceptionOnNonexistantIndex = false)
    {
        $propertyValues = array();

        for ($i = 0; $i < $lastIndex; ++$i) {
            if (!is_object($objectOrArray) && !is_array($objectOrArray)) {
                throw new UnexpectedTypeException($objectOrArray, 'object or array');
            }

            $property = $propertyPath->getElement($i);
            $isIndex = $propertyPath->isIndex($i);

            // Create missing nested arrays on demand
            if ($isIndex && $this->isArrayAccess($objectOrArray) && !isset($objectOrArray[$property])) {
                if ($throwExceptionOnNonexistantIndex) {
                    throw new NoSuchIndexException(sprintf('Cannot read property "%s". Available properties are "%s"', $property, print_r(array_keys($objectOrArray), true)));
                }
                $objectOrArray[$property] = $i + 1 < $propertyPath->getLength() ? array() : null;
            }

            if ($isIndex) {
                $propertyValue =& $this->readIndex($objectOrArray, $property);
            } else {
                $propertyValue =& $this->readProperty($objectOrArray, $property);
            }

            $objectOrArray =& $propertyValue[self::VALUE];

            $propertyValues[] =& $propertyValue;
        }

        return $propertyValues;
    }

    /**
     * Reads a key from an array-like structure.
     *
     * @param \ArrayAccess|array $array The array or \ArrayAccess object to read from
     * @param string|integer     $index The key to read
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
            self::IS_REF => false
        );

        if (isset($array[$index])) {
            if (is_array($array)) {
                $result[self::VALUE] =& $array[$index];
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
     * @throws \UnexpectedValueException
     */
    private function &readProperty(&$object, $property)
    {
        // Use an array instead of an object since performance is
        // very crucial here
        $result = array(
            self::VALUE => null,
            self::IS_REF => false
        );

        if (!is_object($object)) {
            throw new NoSuchPropertyException(sprintf('Cannot read property "%s" from an array. Maybe you should write the property path as "[%s]" instead?', $property, $property));
        }

        $accessMethod = $this->getAccessMethod($object, $property);
        switch ($accessMethod) {
            case self::METHOD_GETTER:
                $getter = $this->buildMethod("get", $property);
                $result[self::VALUE] = $object->$getter();
                break;
            case self::METHOD_PROPERTY:
                $result[self::IS_REF] = true;
                $result[self::VALUE] = $object->$property;
                break;
            case self::METHOD_MAGIC_GETTER:
                $result[self::VALUE] = $object->$property;
                break;
            case self::METHOD_HASSER:
                $hasser = $this->buildMethod("has", $property);
                $result[self::VALUE] = $object->$hasser();
                break;
            case self::METHOD_ISSER:
                $isser = $this->buildMethod("is", $property);
                $result[self::VALUE] = $object->$isser();
                break;
            default:
                throw new \UnexpectedValueException(sprintf(
                    "Unexpected access method: %s",
                    $accessMethod
                ));
                break;
        }

        // Objects are always passed around by reference
        if (is_object($result[self::VALUE])) {
            $result[self::IS_REF] = true;
        }

        return $result;
    }

    /**
     * Sets the value of the property at the given index in the path
     *
     * @param \ArrayAccess|array $array An array or \ArrayAccess object to write to
     * @param string|integer     $index The index to write at
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
     * Sets the value of the property at the given index in the path
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
        $guessedAdders = '';

        if (!is_object($object)) {
            throw new NoSuchPropertyException(sprintf('Cannot write property "%s" to an array. Maybe you should write the property path as "[%s]" instead?', $property, $property));
        }

        $reflClass = new \ReflectionClass($object);
        $plural = $this->camelize($property);

        // Any of the two methods is required, but not yet known
        $singulars = null !== $singular ? array($singular) : (array) StringUtil::singularify($plural);

        if (is_array($value) || $value instanceof \Traversable) {
            $methods = $this->findAdderAndRemover($reflClass, $singulars);

            if (null !== $methods) {
                // At this point the add and remove methods have been found
                // Use iterator_to_array() instead of clone in order to prevent side effects
                // see https://github.com/symfony/symfony/issues/4670
                $itemsToAdd = is_object($value) ? iterator_to_array($value) : $value;
                $itemToRemove = array();
                $propertyValue = $this->readProperty($object, $property);
                $previousValue = $propertyValue[self::VALUE];

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
                    call_user_func(array($object, $methods[1]), $item);
                }

                foreach ($itemsToAdd as $item) {
                    call_user_func(array($object, $methods[0]), $item);
                }

                return;
            } else {
                // It is sufficient to include only the adders in the error
                // message. If the user implements the adder but not the remover,
                // an exception will be thrown in findAdderAndRemover() that
                // the remover has to be implemented as well.
                $guessedAdders = '"add'.implode('()", "add', $singulars).'()", ';
            }
        }

        $setter = 'set'.$this->camelize($property);
        $classHasProperty = $reflClass->hasProperty($property);

        if ($reflClass->hasMethod($setter) && $reflClass->getMethod($setter)->isPublic()) {
            $object->$setter($value);
        } elseif ($reflClass->hasMethod('__set') && $reflClass->getMethod('__set')->isPublic()) {
            $object->$property = $value;
        } elseif ($classHasProperty && $reflClass->getProperty($property)->isPublic()) {
            $object->$property = $value;
        } elseif (!$classHasProperty && property_exists($object, $property)) {
            // Needed to support \stdClass instances. We need to explicitly
            // exclude $classHasProperty, otherwise if in the previous clause
            // a *protected* property was found on the class, property_exists()
            // returns true, consequently the following line will result in a
            // fatal error.
            $object->$property = $value;
        } elseif ($this->magicCall && $reflClass->hasMethod('__call') && $reflClass->getMethod('__call')->isPublic()) {
            // we call the getter and hope the __call do the job
            $object->$setter($value);
        } else {
            throw new NoSuchPropertyException(sprintf(
                'Neither the property "%s" nor one of the methods %s"%s()", '.
                '"__set()" or "__call()" exist and have public access in class "%s".',
                $property,
                $guessedAdders,
                $setter,
                $reflClass->name
            ));
        }
    }

    /**
     * Camelizes a given string.
     *
     * @param  string $string Some string
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

            $addMethodFound = $this->isMethodAccessible($reflClass, $addMethod, 1);
            $removeMethodFound = $this->isMethodAccessible($reflClass, $removeMethod, 1);

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

        return null;
    }

    /**
     * Returns whether a method is public and has a specific number of required parameters.
     *
     * @param  \ReflectionClass $class      The class of the method
     * @param  string           $methodName The method name
     * @param  integer          $parameters The number of parameters
     *
     * @return Boolean Whether the method is public and has $parameters
     *                                      required parameters
     */
    private function isMethodAccessible(\ReflectionClass $class, $methodName, $parameters)
    {
        if ($class->hasMethod($methodName)) {
            $method = $class->getMethod($methodName);

            if ($method->isPublic() && $method->getNumberOfRequiredParameters() === $parameters) {
                return true;
            }
        }

        return false;
    }

    private function getPropertyPath($propertyPath)
    {
        if (is_string($propertyPath)) {
            $propertyPath = new PropertyPath($propertyPath);
        } elseif (!$propertyPath instanceof PropertyPathInterface) {
            throw new UnexpectedTypeException($propertyPath, 'string or Symfony\Component\PropertyAccess\PropertyPathInterface');
        }

        return $propertyPath;
    }

    private function hasGetter($object, $property)
    {
        return $this->hasMethod("get", $object, $property);
    }

    private function hasMagicGetter($object)
    {
        return $this->hasMethod("__get", $object, "");
    }

    private function hasHasser($object, $property)
    {
        return $this->hasMethod("has", $object, $property);
    }

    private function hasIsser($object, $property)
    {
        return $this->hasMethod("is", $object, $property);
    }

    private function hasMagicMethod($object)
    {
        return $this->hasMethod("__call", $object, "");
    }

    private function hasMethod($methodPrefix, $object, $property)
    {
        $method = $methodPrefix.$this->camelize($property);
        $reflector = new \ReflectionClass($object);

        return $reflector->hasMethod($method) && $reflector->getMethod($method)->isPublic();
    }

    private function buildMethod($methodPrefix, $property)
    {
        return $methodPrefix.$this->camelize($property);
    }

    private function isArrayAccess($objectOrArray)
    {
        return is_array($objectOrArray) || $objectOrArray instanceof \ArrayAccess;
    }

    /**
     * Return one of METHOD_* class constants reprsenting suitable access method
     */
    private function getAccessMethod($object, $property)
    {
        $reflClass = new \ReflectionClass($object);
        $getter = $this->buildMethod("get", $property);
        $isser = $this->buildMethod("is", $property);
        $hasser = $this->buildMethod("has", $property);
        $classHasProperty = $reflClass->hasProperty($property);

        if ($this->hasGetter($object, $property)) {
            return self::METHOD_GETTER;
        } elseif ($this->hasIsser($object, $property)) {
            return self::METHOD_ISSER;
        } elseif ($this->hasHasser($object, $property)) {
            return self::METHOD_HASSER;
        } elseif ($this->hasMagicGetter($object)) {
            return self::METHOD_MAGIC_GETTER;
        } elseif ($classHasProperty && $reflClass->getProperty($property)->isPublic()) {
            return self::METHOD_PROPERTY;
        } elseif (!$classHasProperty && property_exists($object, $property)) {
            // Needed to support \stdClass instances. We need to explicitly
            // exclude $classHasProperty, otherwise if in the previous clause
            // a *protected* property was found on the class, property_exists()
            // returns true, consequently the following line will result in a
            // fatal error.
            return self::METHOD_PROPERTY;
        } elseif ($this->magicCall && $this->hasMagicMethod($object)) {
            // we call the getter and hope the __call do the job
            return self::METHOD_GETTER;
        } else {
            $methods = array($getter, $isser, $hasser, '__get');
            if ($this->magicCall) {
                $methods[] = '__call';
            }

            throw new NoSuchPropertyException(sprintf(
                'Neither the property "%s" nor one of the methods "%s()" '.
                'exist and have public access in class "%s".',
                $property,
                implode('()", "', $methods),
                $reflClass->name
            ));
        }
    }
}
