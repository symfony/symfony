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
use Symfony\Component\PropertyAccess\Exception\PropertyAccessDeniedException;
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

    /**
     * Should not be used by application code. Use
     * {@link PropertyAccess::getPropertyAccessor()} instead.
     */
    public function __construct()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getValue($objectOrArray, $propertyPath)
    {
        if (is_string($propertyPath)) {
            $propertyPath = new PropertyPath($propertyPath);
        } elseif (!$propertyPath instanceof PropertyPathInterface) {
            throw new UnexpectedTypeException($propertyPath, 'string or Symfony\Component\PropertyAccess\PropertyPathInterface');
        }

        $propertyValues =& $this->readPropertiesUntil($objectOrArray, $propertyPath, $propertyPath->getLength() - 1);

        return $propertyValues[count($propertyValues) - 1][self::VALUE];
    }

    /**
     * {@inheritdoc}
     */
    public function setValue(&$objectOrArray, $propertyPath, $value)
    {
        if (is_string($propertyPath)) {
            $propertyPath = new PropertyPath($propertyPath);
        } elseif (!$propertyPath instanceof PropertyPathInterface) {
            throw new UnexpectedTypeException($propertyPath, 'string or Symfony\Component\PropertyAccess\PropertyPathInterface');
        }

        $propertyValues =& $this->readPropertiesUntil($objectOrArray, $propertyPath, $propertyPath->getLength() - 2);
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
                $isIndex = $propertyPath->isIndex($i);

                $this->writeProperty($objectOrArray, $propertyPath, $property, $singular, $isIndex, $value);
            }

            $value =& $objectOrArray;
            $overwrite = !$propertyValues[$i][self::IS_REF];
        }
    }

    /**
     * Reads the path from an object up to a given path index.
     *
     * @param object|array          $objectOrArray The object or array to read from.
     * @param PropertyPathInterface $propertyPath  The property path to read.
     * @param integer               $lastIndex     The integer up to which should be read.
     *
     * @return array The values read in the path.
     *
     * @throws UnexpectedTypeException If a value within the path is neither object nor array.
     */
    private function &readPropertiesUntil(&$objectOrArray, PropertyPathInterface $propertyPath, $lastIndex)
    {
        $propertyValues = array();

        for ($i = 0; $i <= $lastIndex; ++$i) {
            if (!is_object($objectOrArray) && !is_array($objectOrArray)) {
                throw new UnexpectedTypeException($objectOrArray, 'object or array');
            }

            $property = $propertyPath->getElement($i);
            $isIndex = $propertyPath->isIndex($i);
            $isArrayAccess = is_array($objectOrArray) || $objectOrArray instanceof \ArrayAccess;

            // Create missing nested arrays on demand
            if ($isIndex && $isArrayAccess && !isset($objectOrArray[$property])) {
                $objectOrArray[$property] = $i + 1 < $propertyPath->getLength() ? array() : null;
            }

            $propertyValue =& $this->readProperty($objectOrArray, $propertyPath, $property, $isIndex);
            $objectOrArray =& $propertyValue[self::VALUE];

            $propertyValues[] =& $propertyValue;
        }

        return $propertyValues;
    }

    /**
     * Reads the a property from an object or array.
     *
     * @param object|array          $objectOrArray The object or array to read from.
     * @param PropertyPathInterface $propertyPath  The property path to read.
     * @param string                $property      The property to read.
     * @param Boolean               $isIndex       Whether to interpret the property as index.
     *
     * @return mixed The value of the read property
     *
     * @throws NoSuchPropertyException       If the property does not exist.
     * @throws PropertyAccessDeniedException If the property cannot be accessed due to
     *                                       access restrictions (private or protected).
     */
    private function &readProperty(&$objectOrArray, PropertyPathInterface $propertyPath, $property, $isIndex)
    {
        // Use an array instead of an object since performance is
        // very crucial here
        $result = array(
            self::VALUE => null,
            self::IS_REF => false
        );

        if ($isIndex) {
            if (!$objectOrArray instanceof \ArrayAccess && !is_array($objectOrArray)) {
                throw new NoSuchPropertyException(sprintf('Index "%s" cannot be read from object of type "%s" because it doesn\'t implement \ArrayAccess', $property, get_class($objectOrArray)));
            }

            if (isset($objectOrArray[$property])) {
                if (is_array($objectOrArray)) {
                    $result[self::VALUE] =& $objectOrArray[$property];
                    $result[self::IS_REF] = true;
                } else {
                    $result[self::VALUE] = $objectOrArray[$property];
                }
            }
        } elseif (is_object($objectOrArray)) {
            $camelProp = $this->camelize($property);
            $reflClass = new \ReflectionClass($objectOrArray);
            $getter = 'get'.$camelProp;
            $isser = 'is'.$camelProp;
            $hasser = 'has'.$camelProp;

            if ($reflClass->hasMethod($getter)) {
                if (!$reflClass->getMethod($getter)->isPublic()) {
                    throw new PropertyAccessDeniedException(sprintf('Method "%s()" is not public in class "%s"', $getter, $reflClass->name));
                }

                $result[self::VALUE] = $objectOrArray->$getter();
            } elseif ($reflClass->hasMethod($isser)) {
                if (!$reflClass->getMethod($isser)->isPublic()) {
                    throw new PropertyAccessDeniedException(sprintf('Method "%s()" is not public in class "%s"', $isser, $reflClass->name));
                }

                $result[self::VALUE] = $objectOrArray->$isser();
            } elseif ($reflClass->hasMethod($hasser)) {
                if (!$reflClass->getMethod($hasser)->isPublic()) {
                    throw new PropertyAccessDeniedException(sprintf('Method "%s()" is not public in class "%s"', $hasser, $reflClass->name));
                }

                $result[self::VALUE] = $objectOrArray->$hasser();
            } elseif ($reflClass->hasMethod('__get')) {
                // needed to support magic method __get
                $result[self::VALUE] = $objectOrArray->$property;
            } elseif ($reflClass->hasProperty($property)) {
                if (!$reflClass->getProperty($property)->isPublic()) {
                    throw new PropertyAccessDeniedException(sprintf('Property "%s" is not public in class "%s". Maybe you should create the method "%s()" or "%s()" or "%s()"?', $property, $reflClass->name, $getter, $isser, $hasser));
                }

                $result[self::VALUE] =& $objectOrArray->$property;
                $result[self::IS_REF] = true;
            } elseif (property_exists($objectOrArray, $property)) {
                // needed to support \stdClass instances
                $result[self::VALUE] =& $objectOrArray->$property;
                $result[self::IS_REF] = true;
            } else {
                throw new NoSuchPropertyException(sprintf('Neither property "%s" nor method "%s()" nor method "%s()" exists in class "%s"', $property, $getter, $isser, $reflClass->name));
            }
        } else {
            throw new NoSuchPropertyException(sprintf('Cannot read property "%s" from an array. Maybe you should write the property path as "[%s]" instead?', $property, $property));
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
     * @param object|array          $objectOrArray The object or array to write to.
     * @param PropertyPathInterface $propertyPath  The property path to write.
     * @param string                $property      The property to write.
     * @param string|null           $singular      The singular form of the property name or null.
     * @param Boolean               $isIndex       Whether to interpret the property as index.
     * @param mixed                 $value         The value to write.
     *
     * @throws NoSuchPropertyException       If the property does not exist.
     * @throws PropertyAccessDeniedException If the property cannot be accessed due to
     *                                       access restrictions (private or protected).
     */
    private function writeProperty(&$objectOrArray, PropertyPathInterface $propertyPath, $property, $singular, $isIndex, $value)
    {
        $adderRemoverError = null;

        if ($isIndex) {
            if (!$objectOrArray instanceof \ArrayAccess && !is_array($objectOrArray)) {
                throw new NoSuchPropertyException(sprintf('Index "%s" cannot be modified in object of type "%s" because it doesn\'t implement \ArrayAccess', $property, get_class($objectOrArray)));
            }

            $objectOrArray[$property] = $value;
        } elseif (is_object($objectOrArray)) {
            $reflClass = new \ReflectionClass($objectOrArray);

            // The plural form is the last element of the property path
            $plural = $this->camelize($propertyPath->getElement($propertyPath->getLength() - 1));

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
                    $propertyValue = $this->readProperty($objectOrArray, $propertyPath, $property, $isIndex);
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
                        call_user_func(array($objectOrArray, $methods[1]), $item);
                    }

                    foreach ($itemsToAdd as $item) {
                        call_user_func(array($objectOrArray, $methods[0]), $item);
                    }

                    return;
                } else {
                    $adderRemoverError = ', nor could adders and removers be found based on the ';
                    if (null === $singular) {
                        // $adderRemoverError .= 'guessed singulars: '.implode(', ', $singulars).' (provide a singular by suffixing the property path with "|{singular}" to override the guesser)';
                        $adderRemoverError .= 'guessed singulars: '.implode(', ', $singulars);
                    } else {
                        $adderRemoverError .= 'passed singular: '.$singular;
                    }
                }
            }

            $setter = 'set'.$this->camelize($property);
            if ($reflClass->hasMethod($setter)) {
                if (!$reflClass->getMethod($setter)->isPublic()) {
                    throw new PropertyAccessDeniedException(sprintf('Method "%s()" is not public in class "%s"', $setter, $reflClass->name));
                }

                $objectOrArray->$setter($value);
            } elseif ($reflClass->hasMethod('__set')) {
                // needed to support magic method __set
                $objectOrArray->$property = $value;
            } elseif ($reflClass->hasProperty($property)) {
                if (!$reflClass->getProperty($property)->isPublic()) {
                    throw new PropertyAccessDeniedException(sprintf('Property "%s" is not public in class "%s"%s. Maybe you should create the method "%s()"?', $property, $reflClass->name, $adderRemoverError, $setter));
                }

                $objectOrArray->$property = $value;
            } elseif (property_exists($objectOrArray, $property)) {
                // needed to support \stdClass instances
                $objectOrArray->$property = $value;
            } else {
                throw new NoSuchPropertyException(sprintf('Neither element "%s" nor method "%s()" exists in class "%s"%s', $property, $setter, $reflClass->name, $adderRemoverError));
            }
        } else {
            throw new NoSuchPropertyException(sprintf('Cannot write property "%s" in an array. Maybe you should write the property path as "[%s]" instead?', $property, $property));
        }
    }

    /**
     * Camelizes a given string.
     *
     * @param  string $string Some string.
     *
     * @return string The camelized version of the string.
     */
    private function camelize($string)
    {
        return preg_replace_callback('/(^|_|\.)+(.)/', function ($match) { return ('.' === $match[1] ? '_' : '').strtoupper($match[2]); }, $string);
    }

    /**
     * Searches for add and remove methods.
     *
     * @param \ReflectionClass $reflClass The reflection class for the given object
     * @param array            $singulars The singular form of the property name or null.
     *
     * @return array|null An array containing the adder and remover when found, null otherwise.
     *
     * @throws NoSuchPropertyException If the property does not exist.
     */
    private function findAdderAndRemover(\ReflectionClass $reflClass, array $singulars)
    {
        foreach ($singulars as $singular) {
            $addMethod = 'add' . $singular;
            $removeMethod = 'remove' . $singular;

            $addMethodFound = $this->isAccessible($reflClass, $addMethod, 1);
            $removeMethodFound = $this->isAccessible($reflClass, $removeMethod, 1);

            if ($addMethodFound && $removeMethodFound) {
                return array($addMethod, $removeMethod);
            }

            if ($addMethodFound xor $removeMethodFound) {
                throw new NoSuchPropertyException(sprintf(
                    'Found the public method "%s", but did not find a public "%s" on class %s',
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
     * @param  \ReflectionClass $class      The class of the method.
     * @param  string           $methodName The method name.
     * @param  integer          $parameters The number of parameters.
     *
     * @return Boolean Whether the method is public and has $parameters
     *                                      required parameters.
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
