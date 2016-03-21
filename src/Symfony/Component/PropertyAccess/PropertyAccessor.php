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
 * @author Nicolas Grekas <p@tchwork.com>
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
    const REF = 1;

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
    private static $previousErrorHandler;
    private static $errorHandler = array(__CLASS__, 'handleError');
    private static $resultProto = array(self::VALUE => null);

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

        $zval = array(
            self::VALUE => $objectOrArray,
        );
        $propertyValues = $this->readPropertiesUntil($zval, $propertyPath, $propertyPath->getLength());

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

        $zval = array(
            self::VALUE => $objectOrArray,
            self::REF => &$objectOrArray,
        );
        $propertyValues = $this->readPropertiesUntil($zval, $propertyPath, $propertyPath->getLength() - 1);
        $overwrite = true;

        try {
            if (PHP_VERSION_ID < 70000) {
                self::$previousErrorHandler = set_error_handler(self::$errorHandler);
            }

            for ($i = count($propertyValues) - 1; 0 <= $i; --$i) {
                $zval = $propertyValues[$i];
                unset($propertyValues[$i]);

                if ($overwrite) {
                    $property = $propertyPath->getElement($i);

                    if ($propertyPath->isIndex($i)) {
                        if ($overwrite = !isset($zval[self::REF])) {
                            $ref = &$zval[self::REF];
                        }
                        $this->writeIndex($zval, $property, $value);
                        if ($overwrite) {
                            $zval[self::VALUE] = $zval[self::REF];
                        }
                    } else {
                        $this->writeProperty($zval, $property, $value);
                    }
                }

                $value = $zval[self::VALUE];
            }
        } catch (\TypeError $e) {
            try {
                self::throwUnexpectedTypeException($e->getMessage(), $e->getTrace(), 0);
            } catch (UnexpectedTypeException $e) {
            }
        } catch (\Exception $e) {
        } catch (\Throwable $e) {
        }

        if (PHP_VERSION_ID < 70000) {
            restore_error_handler();
            self::$previousErrorHandler = null;
        }
        if (isset($e)) {
            throw $e;
        }
    }

    /**
     * @internal
     */
    public static function handleError($type, $message, $file, $line, $context)
    {
        if (E_RECOVERABLE_ERROR === $type) {
            self::throwUnexpectedTypeException($message, debug_backtrace(false), 1);
        }

        return null !== self::$previousErrorHandler && false !== call_user_func(self::$previousErrorHandler, $type, $message, $file, $line, $context);
    }

    private static function throwUnexpectedTypeException($message, $trace, $i)
    {
        if (isset($trace[$i]['file']) && __FILE__ === $trace[$i]['file']) {
            $pos = strpos($message, $delim = 'must be of the type ') ?: strpos($message, $delim = 'must be an instance of ');
            $pos += strlen($delim);

            throw new UnexpectedTypeException($trace[$i]['args'][0], substr($message, $pos, strpos($message, ',', $pos) - $pos));
        }
    }

    /**
     * Reads the path from an object up to a given path index.
     *
     * @param array                 $zval         The array containing the object or array to read from
     * @param PropertyPathInterface $propertyPath The property path to read
     * @param int                   $lastIndex    The index up to which should be read
     *
     * @return array The values read in the path.
     *
     * @throws UnexpectedTypeException If a value within the path is neither object nor array.
     */
    private function readPropertiesUntil($zval, PropertyPathInterface $propertyPath, $lastIndex)
    {
        if (!is_object($zval[self::VALUE]) && !is_array($zval[self::VALUE])) {
            throw new UnexpectedTypeException($zval[self::VALUE], 'object or array');
        }

        // Add the root object to the list
        $propertyValues = array($zval);

        for ($i = 0; $i < $lastIndex; ++$i) {
            $property = $propertyPath->getElement($i);
            $isIndex = $propertyPath->isIndex($i);

            if ($isIndex) {
                // Create missing nested arrays on demand
                if ($i + 1 < $propertyPath->getLength() && (
                    ($zval[self::VALUE] instanceof \ArrayAccess && !$zval[self::VALUE]->offsetExists($property)) ||
                    (is_array($zval[self::VALUE]) && !isset($zval[self::VALUE][$property]) && !array_key_exists($property, $zval[self::VALUE]))
                )) {
                    $zval[self::VALUE][$property] = array();

                    if (isset($zval[self::REF])) {
                        $zval[self::REF] = $zval[self::VALUE];
                    }
                }

                $zval = $this->readIndex($zval, $property);
            } else {
                $zval = $this->readProperty($zval, $property);
            }

            // the final value of the path must not be validated
            if ($i + 1 < $propertyPath->getLength() && !is_object($zval[self::VALUE]) && !is_array($zval[self::VALUE])) {
                throw new UnexpectedTypeException($zval[self::VALUE], 'object or array');
            }

            $propertyValues[] = $zval;
        }

        return $propertyValues;
    }

    /**
     * Reads a key from an array-like structure.
     *
     * @param array      $zval  The array containing the array or \ArrayAccess object to read from
     * @param string|int $index The key to read
     *
     * @return array The array containing the value of the key
     *
     * @throws NoSuchPropertyException If the array does not implement \ArrayAccess or it is not an array
     */
    private function readIndex($zval, $index)
    {
        if (!$zval[self::VALUE] instanceof \ArrayAccess && !is_array($zval[self::VALUE])) {
            throw new NoSuchPropertyException(sprintf('Index "%s" cannot be read from object of type "%s" because it doesn\'t implement \ArrayAccess', $index, get_class($zval[self::VALUE])));
        }

        $result = self::$resultProto;

        if (isset($zval[self::VALUE][$index])) {
            $result[self::VALUE] = $zval[self::VALUE][$index];

            if (!isset($zval[self::REF])) {
                // Save creating references when doing read-only lookups
            } elseif (is_array($zval[self::VALUE])) {
                $result[self::REF] = &$zval[self::REF][$index];
            } elseif (is_object($result[self::VALUE])) {
                $result[self::REF] = $result[self::VALUE];
            }
        }

        return $result;
    }

    /**
     * Reads the a property from an object.
     *
     * @param array  $zval     The array containing the object to read from
     * @param string $property The property to read.
     *
     * @return array The array containing the value of the property
     *
     * @throws NoSuchPropertyException If the property does not exist or is not public.
     */
    private function readProperty($zval, $property)
    {
        if (!is_object($zval[self::VALUE])) {
            throw new NoSuchPropertyException(sprintf('Cannot read property "%s" from an array. Maybe you should write the property path as "[%s]" instead?', $property, $property));
        }

        $result = self::$resultProto;
        $object = $zval[self::VALUE];
        $access = $this->getReadAccessInfo(get_class($object), $property);

        if (self::ACCESS_TYPE_METHOD === $access[self::ACCESS_TYPE]) {
            $result[self::VALUE] = $object->{$access[self::ACCESS_NAME]}();
        } elseif (self::ACCESS_TYPE_PROPERTY === $access[self::ACCESS_TYPE]) {
            $result[self::VALUE] = $object->{$access[self::ACCESS_NAME]};

            if ($access[self::ACCESS_REF] && isset($zval[self::REF])) {
                $result[self::REF] = &$object->{$access[self::ACCESS_NAME]};
            }
        } elseif (!$access[self::ACCESS_HAS_PROPERTY] && property_exists($object, $property)) {
            // Needed to support \stdClass instances. We need to explicitly
            // exclude $classHasProperty, otherwise if in the previous clause
            // a *protected* property was found on the class, property_exists()
            // returns true, consequently the following line will result in a
            // fatal error.

            $result[self::VALUE] = $object->$property;
            if (isset($zval[self::REF])) {
                $result[self::REF] = &$object->$property;
            }
        } elseif (self::ACCESS_TYPE_MAGIC === $access[self::ACCESS_TYPE]) {
            // we call the getter and hope the __call do the job
            $result[self::VALUE] = $object->{$access[self::ACCESS_NAME]}();
        } else {
            throw new NoSuchPropertyException($access[self::ACCESS_NAME]);
        }

        // Objects are always passed around by reference
        if (isset($zval[self::REF]) && is_object($result[self::VALUE])) {
            $result[self::REF] = $result[self::VALUE];
        }

        return $result;
    }

    /**
     * Guesses how to read the property value.
     *
     * @param string $class
     * @param string $property
     *
     * @return array
     */
    private function getReadAccessInfo($class, $property)
    {
        $key = $class.'::'.$property;

        if (isset($this->readPropertyCache[$key])) {
            $access = $this->readPropertyCache[$key];
        } else {
            $access = array();

            $reflClass = new \ReflectionClass($class);
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
     * @param array      $zval  The array containing the array or \ArrayAccess object to write to
     * @param string|int $index The index to write at
     * @param mixed      $value The value to write
     *
     * @throws NoSuchPropertyException If the array does not implement \ArrayAccess or it is not an array
     */
    private function writeIndex($zval, $index, $value)
    {
        if (!$zval[self::VALUE] instanceof \ArrayAccess && !is_array($zval[self::VALUE])) {
            throw new NoSuchPropertyException(sprintf('Index "%s" cannot be modified in object of type "%s" because it doesn\'t implement \ArrayAccess', $index, get_class($zval[self::VALUE])));
        }

        $zval[self::REF][$index] = $value;
    }

    /**
     * Sets the value of the property at the given index in the path.
     *
     * @param array  $zval     The array containing the object to write to
     * @param string $property The property to write
     * @param mixed  $value    The value to write
     *
     * @throws NoSuchPropertyException If the property does not exist or is not public.
     */
    private function writeProperty($zval, $property, $value)
    {
        if (!is_object($zval[self::VALUE])) {
            throw new NoSuchPropertyException(sprintf('Cannot write property "%s" to an array. Maybe you should write the property path as "[%s]" instead?', $property, $property));
        }

        $object = $zval[self::VALUE];
        $access = $this->getWriteAccessInfo(get_class($object), $property, $value);

        if (self::ACCESS_TYPE_METHOD === $access[self::ACCESS_TYPE]) {
            $object->{$access[self::ACCESS_NAME]}($value);
        } elseif (self::ACCESS_TYPE_PROPERTY === $access[self::ACCESS_TYPE]) {
            $object->{$access[self::ACCESS_NAME]} = $value;
        } elseif (self::ACCESS_TYPE_ADDER_AND_REMOVER === $access[self::ACCESS_TYPE]) {
            // At this point the add and remove methods have been found
            $previousValue = $this->readProperty($zval, $property);
            $previousValue = $previousValue[self::VALUE];

            if ($previousValue instanceof \Traversable) {
                $previousValue = iterator_to_array($previousValue);
            }
            if ($previousValue && is_array($previousValue)) {
                if (is_object($value)) {
                    $value = iterator_to_array($value);
                }
                foreach ($previousValue as $key => $item) {
                    if (!in_array($item, $value, true)) {
                        unset($previousValue[$key]);
                        $object->{$access[self::ACCESS_REMOVER]}($item);
                    }
                }
            } else {
                $previousValue = false;
            }

            foreach ($value as $item) {
                if (!$previousValue || !in_array($item, $previousValue, true)) {
                    $object->{$access[self::ACCESS_ADDER]}($item);
                }
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
     * @param string $class
     * @param string $property
     * @param mixed  $value
     *
     * @return array
     */
    private function getWriteAccessInfo($class, $property, $value)
    {
        $key = $class.'::'.$property;
        $guessedAdders = '';

        if (isset($this->writePropertyCache[$key])) {
            $access = $this->writePropertyCache[$key];
        } else {
            $access = array();

            $reflClass = new \ReflectionClass($class);
            $access[self::ACCESS_HAS_PROPERTY] = $reflClass->hasProperty($property);
            $plural = $this->camelize($property);

            // Any of the two methods is required, but not yet known
            $singulars = (array) StringUtil::singularify($plural);

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
     * @return bool Whether the method is public and has $parameters required parameters
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
