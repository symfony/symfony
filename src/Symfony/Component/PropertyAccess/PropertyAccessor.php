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

use Symfony\Component\PropertyAccess\Exception\AccessException;
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
    const IS_REF_CHAINED = 2;

    /**
     * @var bool
     */
    private $magicCall;

    /**
     * @var bool
     */
    private $ignoreInvalidIndices;

    /**
     * Should not be used by application code. Use
     * {@link PropertyAccess::createPropertyAccessor()} instead.
     */
    public function __construct($magicCall = false, $throwExceptionOnInvalidIndex = false)
    {
        $this->magicCall = $magicCall;
        $this->ignoreInvalidIndices = !$throwExceptionOnInvalidIndex;
    }

    /**
     * {@inheritdoc}
     */
    public function getValue($objectOrArray, $propertyPath)
    {
        if (!$propertyPath instanceof PropertyPathInterface) {
            $propertyPath = new PropertyPath($propertyPath);
        }

        $propertyValues = &$this->readPropertiesUntil($objectOrArray, $propertyPath, $propertyPath->getLength(), $this->ignoreInvalidIndices);

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

        // Add the root object to the list
        array_unshift($propertyValues, array(
            self::VALUE => &$objectOrArray,
            self::IS_REF => true,
            self::IS_REF_CHAINED => true,
        ));

        for ($i = count($propertyValues) - 1; $i >= 0; --$i) {
            $objectOrArray = &$propertyValues[$i][self::VALUE];

            $property = $propertyPath->getElement($i);

            // We only need set value for current element if:
            // 1. it's the parent of the last index element
            // OR
            // 2. it's child is not passed by reference
            //
            // This may avoid uncessary value setting process for array elements.
            // For example:
            // '[a][b][c]' => 'old-value'
            // If we want to change its value to 'new-value', 
            // we only need set value for '[a][b][c]' and it's safe to ignore '[a][b]' and '[a]'
            //
            if ($i === count($propertyValues) - 1 || !$propertyValues[$i + 1][self::IS_REF]) {

                if ($propertyPath->isIndex($i)) {
                    $this->writeIndex($objectOrArray, $property, $value);
                } else {
                    $this->writeProperty($objectOrArray, $property, $value);
                }

                // if current element is an object
                // OR
                // if current element's reference chain is not broken - current element 
                // as well as all its ancients in the property path are all passed by reference, 
                // then there is no need to continue the value setting process
                if (is_object($propertyValues[$i][self::VALUE]) || $propertyValues[$i][self::IS_REF_CHAINED]) {
                    return;
                }

            }

            $value = &$objectOrArray;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isReadable($objectOrArray, $propertyPath)
    {
        if (!$propertyPath instanceof PropertyPathInterface) {
            $propertyPath = new PropertyPath($propertyPath);
        }

        try {
            $this->readPropertiesUntil($objectOrArray, $propertyPath, $propertyPath->getLength(), $this->ignoreInvalidIndices);

            return true;
        } catch (AccessException $e) {
            return false;
        } catch (UnexpectedTypeException $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isWritable($objectOrArray, $propertyPath)
    {
        if (!$propertyPath instanceof PropertyPathInterface) {
            $propertyPath = new PropertyPath($propertyPath);
        }

        try {
            $propertyValues = $this->readPropertiesUntil($objectOrArray, $propertyPath, $propertyPath->getLength() - 1);

            // Add the root object to the list
            array_unshift($propertyValues, array(
                self::VALUE => $objectOrArray,
                self::IS_REF => true,
                self::IS_REF_CHAINED => true,
            ));

            for ($i = count($propertyValues) - 1; $i >= 0; --$i) {
                $objectOrArray = $propertyValues[$i][self::VALUE];

                $property = $propertyPath->getElement($i);

                if ($propertyPath->isIndex($i)) {
                    if (!$objectOrArray instanceof \ArrayAccess && !is_array($objectOrArray)) {
                        return false;
                    }
                } else {
                    if (!$this->isPropertyWritable($objectOrArray, $property)) {
                        return false;
                    }
                }

                if (is_object($propertyValues[$i][self::VALUE]) || $propertyValues[$i][self::IS_REF_CHAINED]) {
                    return true;
                }
            }

            return true;
        } catch (AccessException $e) {
            return false;
        } catch (UnexpectedTypeException $e) {
            return false;
        }
    }

    /**
     * Reads the path from an object up to a given path index.
     *
     * @param object|array          $objectOrArray        The object or array to read from
     * @param PropertyPathInterface $propertyPath         The property path to read
     * @param int                   $lastIndex            The index up to which should be read
     * @param bool                  $ignoreInvalidIndices Whether to ignore invalid indices
     *                                                    or throw an exception
     *
     * @return array The values read in the path.
     *
     * @throws UnexpectedTypeException If a value within the path is neither object nor array.
     * @throws NoSuchIndexException    If a non-existing index is accessed
     */
    private function &readPropertiesUntil(&$objectOrArray, PropertyPathInterface $propertyPath, $lastIndex, $ignoreInvalidIndices = true)
    {
        if (!is_object($objectOrArray) && !is_array($objectOrArray)) {
            throw new UnexpectedTypeException($objectOrArray, 'object or array');
        }

        $propertyValues = array();

        for ($i = 0; $i < $lastIndex; ++$i) {
            $property = $propertyPath->getElement($i);
            $isIndex = $propertyPath->isIndex($i);

            // Create missing nested arrays on demand
            if ($isIndex &&
                (
                    ($objectOrArray instanceof \ArrayAccess && !isset($objectOrArray[$property])) ||
                    (is_array($objectOrArray) && !array_key_exists($property, $objectOrArray))
                )
            ) {
                if (!$ignoreInvalidIndices) {
                    if (!is_array($objectOrArray)) {
                        if (!$objectOrArray instanceof \Traversable) {
                            throw new NoSuchIndexException(sprintf(
                                'Cannot read index "%s" while trying to traverse path "%s".',
                                $property,
                                (string) $propertyPath
                            ));
                        }

                        $objectOrArray = iterator_to_array($objectOrArray);
                    }

                    throw new NoSuchIndexException(sprintf(
                        'Cannot read index "%s" while trying to traverse path "%s". Available indices are "%s".',
                        $property,
                        (string) $propertyPath,
                        print_r(array_keys($objectOrArray), true)
                    ));
                }

                $objectOrArray[$property] = $i + 1 < $propertyPath->getLength() ? array() : null;
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

            $propertyValue[self::IS_REF_CHAINED] = $propertyValue[self::IS_REF] && ($i == 0 || $propertyValues[$i - 1][self::IS_REF_CHAINED]);

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
     * @throws NoSuchIndexException If the array does not implement \ArrayAccess or it is not an array
     */
    private function &readIndex(&$array, $index)
    {
        if (!$array instanceof \ArrayAccess && !is_array($array)) {
            throw new NoSuchIndexException(sprintf('Cannot read index "%s" from object of type "%s" because it doesn\'t implement \ArrayAccess.', $index, get_class($array)));
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
            throw new NoSuchPropertyException(sprintf('Cannot read property "%s" from an array. Maybe you intended to write the property path as "[%s]" instead.', $property, $property));
        }

        $camelized = $this->camelize($property);
        $reflClass = new \ReflectionClass($object);
        $getter = 'get'.$camelized;
        $getsetter = lcfirst($camelized); // jQuery style, e.g. read: last(), write: last($item)
        $isser = 'is'.$camelized;
        $hasser = 'has'.$camelized;
        $classHasProperty = $reflClass->hasProperty($property);

        if ($reflClass->hasMethod($getter) && $reflClass->getMethod($getter)->isPublic()) {
            $result[self::VALUE] = $object->$getter();
        } elseif ($this->isMethodAccessible($reflClass, $getsetter, 0)) {
            $result[self::VALUE] = $object->$getsetter();
        } elseif ($reflClass->hasMethod($isser) && $reflClass->getMethod($isser)->isPublic()) {
            $result[self::VALUE] = $object->$isser();
        } elseif ($reflClass->hasMethod($hasser) && $reflClass->getMethod($hasser)->isPublic()) {
            $result[self::VALUE] = $object->$hasser();
        } elseif ($classHasProperty && $reflClass->getProperty($property)->isPublic()) {
            $result[self::VALUE] = &$object->$property;
            $result[self::IS_REF] = true;
        } elseif ($reflClass->hasMethod('__get') && $reflClass->getMethod('__get')->isPublic()) {
            $result[self::VALUE] = $object->$property;
        } elseif (!$classHasProperty && property_exists($object, $property)) {
            // Needed to support \stdClass instances. We need to explicitly
            // exclude $classHasProperty, otherwise if in the previous clause
            // a *protected* property was found on the class, property_exists()
            // returns true, consequently the following line will result in a
            // fatal error.
            $result[self::VALUE] = &$object->$property;
            $result[self::IS_REF] = true;
        } elseif ($this->magicCall && $reflClass->hasMethod('__call') && $reflClass->getMethod('__call')->isPublic()) {
            // we call the getter and hope the __call do the job
            $result[self::VALUE] = $object->$getter();
        } else {
            $methods = array($getter, $getsetter, $isser, $hasser, '__get');
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

        // Objects are always passed around by reference
        if (is_object($result[self::VALUE])) {
            $result[self::IS_REF] = true;
        }

        return $result;
    }

    /**
     * Sets the value of an index in a given array-accessible value.
     *
     * @param \ArrayAccess|array $array An array or \ArrayAccess object to write to
     * @param string|int         $index The index to write at
     * @param mixed              $value The value to write
     *
     * @throws NoSuchIndexException If the array does not implement \ArrayAccess or it is not an array
     */
    private function writeIndex(&$array, $index, $value)
    {
        if (!$array instanceof \ArrayAccess && !is_array($array)) {
            throw new NoSuchIndexException(sprintf('Cannot modify index "%s" in object of type "%s" because it doesn\'t implement \ArrayAccess', $index, get_class($array)));
        }

        $array[$index] = $value;
    }

    /**
     * Sets the value of a property in the given object.
     *
     * @param object $object   The object to write to
     * @param string $property The property to write
     * @param mixed  $value    The value to write
     *
     * @throws NoSuchPropertyException If the property does not exist or is not
     *                                 public.
     */
    private function writeProperty(&$object, $property, $value)
    {
        if (!is_object($object)) {
            throw new NoSuchPropertyException(sprintf('Cannot write property "%s" to an array. Maybe you should write the property path as "[%s]" instead?', $property, $property));
        }

        $reflClass = new \ReflectionClass($object);
        $camelized = $this->camelize($property);
        $singulars = (array) StringUtil::singularify($camelized);

        if (is_array($value) || $value instanceof \Traversable) {
            $methods = $this->findAdderAndRemover($reflClass, $singulars);

            // Use addXxx() and removeXxx() to write the collection
            if (null !== $methods) {
                $this->writeCollection($object, $property, $value, $methods[0], $methods[1]);

                return;
            }
        }

        $setter = 'set'.$camelized;
        $getsetter = lcfirst($camelized); // jQuery style, e.g. read: last(), write: last($item)
        $classHasProperty = $reflClass->hasProperty($property);

        if ($this->isMethodAccessible($reflClass, $setter, 1)) {
            $object->$setter($value);
        } elseif ($this->isMethodAccessible($reflClass, $getsetter, 1)) {
            $object->$getsetter($value);
        } elseif ($classHasProperty && $reflClass->getProperty($property)->isPublic()) {
            $object->$property = $value;
        } elseif ($this->isMethodAccessible($reflClass, '__set', 2)) {
            $object->$property = $value;
        } elseif (!$classHasProperty && property_exists($object, $property)) {
            // Needed to support \stdClass instances. We need to explicitly
            // exclude $classHasProperty, otherwise if in the previous clause
            // a *protected* property was found on the class, property_exists()
            // returns true, consequently the following line will result in a
            // fatal error.
            $object->$property = $value;
        } elseif ($this->magicCall && $this->isMethodAccessible($reflClass, '__call', 2)) {
            // we call the getter and hope the __call do the job
            $object->$setter($value);
        } else {
            throw new NoSuchPropertyException(sprintf(
                'Neither the property "%s" nor one of the methods %s"%s()", "%s()", '.
                '"__set()" or "__call()" exist and have public access in class "%s".',
                $property,
                implode('', array_map(function ($singular) {
                    return '"add'.$singular.'()"/"remove'.$singular.'()", ';
                }, $singulars)),
                $setter,
                $getsetter,
                $reflClass->name
            ));
        }
    }

    /**
     * Adjusts a collection-valued property by calling add*() and remove*()
     * methods.
     *
     * @param object             $object       The object to write to
     * @param string             $property     The property to write
     * @param array|\Traversable $collection   The collection to write
     * @param string             $addMethod    The add*() method
     * @param string             $removeMethod The remove*() method
     */
    private function writeCollection($object, $property, $collection, $addMethod, $removeMethod)
    {
        // At this point the add and remove methods have been found
        // Use iterator_to_array() instead of clone in order to prevent side effects
        // see https://github.com/symfony/symfony/issues/4670
        $itemsToAdd = is_object($collection) ? iterator_to_array($collection) : $collection;
        $itemToRemove = array();
        $propertyValue = &$this->readProperty($object, $property);
        $previousValue = $propertyValue[self::VALUE];
        // remove reference to avoid modifications
        unset($propertyValue);

        if (is_array($previousValue) || $previousValue instanceof \Traversable) {
            foreach ($previousValue as $previousItem) {
                foreach ($collection as $key => $item) {
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
            $object->{$removeMethod}($item);
        }

        foreach ($itemsToAdd as $item) {
            $object->{$addMethod}($item);
        }
    }

    /**
     * Returns whether a property is writable in the given object.
     *
     * @param object $object   The object to write to
     * @param string $property The property to write
     *
     * @return bool Whether the property is writable
     */
    private function isPropertyWritable($object, $property)
    {
        if (!is_object($object)) {
            return false;
        }

        $reflClass = new \ReflectionClass($object);

        $camelized = $this->camelize($property);
        $setter = 'set'.$camelized;
        $getsetter = lcfirst($camelized); // jQuery style, e.g. read: last(), write: last($item)
        $classHasProperty = $reflClass->hasProperty($property);

        if ($this->isMethodAccessible($reflClass, $setter, 1)
            || $this->isMethodAccessible($reflClass, $getsetter, 1)
            || $this->isMethodAccessible($reflClass, '__set', 2)
            || ($classHasProperty && $reflClass->getProperty($property)->isPublic())
            || (!$classHasProperty && property_exists($object, $property))
            || ($this->magicCall && $this->isMethodAccessible($reflClass, '__call', 2))) {
            return true;
        }

        $singulars = (array) StringUtil::singularify($camelized);

        // Any of the two methods is required, but not yet known
        if (null !== $this->findAdderAndRemover($reflClass, $singulars)) {
            return true;
        }

        return false;
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
        return strtr(ucwords(strtr($string, array('_' => ' '))), array(' ' => ''));
    }

    /**
     * Searches for add and remove methods.
     *
     * @param \ReflectionClass $reflClass The reflection class for the given object
     * @param array            $singulars The singular form of the property name or null
     *
     * @return array|null An array containing the adder and remover when found, null otherwise
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
        }
    }

    /**
     * Returns whether a method is public and has the number of required parameters.
     *
     * @param \ReflectionClass $class      The class of the method
     * @param string           $methodName The method name
     * @param int              $parameters The number of parameters
     *
     * @return bool Whether the method is public and has $parameters
     *              required parameters
     */
    private function isMethodAccessible(\ReflectionClass $class, $methodName, $parameters)
    {
        if ($class->hasMethod($methodName)) {
            $method = $class->getMethod($methodName);

            if ($method->isPublic()
                && $method->getNumberOfRequiredParameters() <= $parameters
                && $method->getNumberOfParameters() >= $parameters) {
                return true;
            }
        }

        return false;
    }
}
