<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Util;

use Traversable;
use ReflectionClass;
use Symfony\Component\Form\Exception\InvalidPropertyPathException;
use Symfony\Component\Form\Exception\InvalidPropertyException;
use Symfony\Component\Form\Exception\PropertyAccessDeniedException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;

/**
 * Allows easy traversing of a property path
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PropertyPath implements \IteratorAggregate, PropertyPathInterface
{
    /**
     * Character used for separating between plural and singular of an element.
     * @var string
     */
    const SINGULAR_SEPARATOR = '|';

    const VALUE = 0;
    const IS_REF = 1;

    /**
     * The elements of the property path
     * @var array
     */
    private $elements = array();

    /**
     * The singular forms of the elements in the property path.
     * @var array
     */
    private $singulars = array();

    /**
     * The number of elements in the property path
     * @var integer
     */
    private $length;

    /**
     * Contains a Boolean for each property in $elements denoting whether this
     * element is an index. It is a property otherwise.
     * @var array
     */
    private $isIndex = array();

    /**
     * String representation of the path
     * @var string
     */
    private $pathAsString;

    /**
     * Constructs a property path from a string.
     *
     * @param PropertyPath|string $propertyPath The property path as string or instance.
     *
     * @throws UnexpectedTypeException      If the given path is not a string.
     * @throws InvalidPropertyPathException If the syntax of the property path is not valid.
     */
    public function __construct($propertyPath)
    {
        // Can be used as copy constructor
        if ($propertyPath instanceof PropertyPath) {
            /* @var PropertyPath $propertyPath */
            $this->elements = $propertyPath->elements;
            $this->singulars = $propertyPath->singulars;
            $this->length = $propertyPath->length;
            $this->isIndex = $propertyPath->isIndex;
            $this->pathAsString = $propertyPath->pathAsString;

            return;
        }
        if (!is_string($propertyPath)) {
            throw new UnexpectedTypeException($propertyPath, 'string or Symfony\Component\Form\Util\PropertyPath');
        }

        if ('' === $propertyPath) {
            throw new InvalidPropertyPathException('The property path should not be empty.');
        }

        $this->pathAsString = $propertyPath;
        $position = 0;
        $remaining = $propertyPath;

        // first element is evaluated differently - no leading dot for properties
        $pattern = '/^(([^\.\[]+)|\[([^\]]+)\])(.*)/';

        while (preg_match($pattern, $remaining, $matches)) {
            if ('' !== $matches[2]) {
                $element = $matches[2];
                $this->isIndex[] = false;
            } else {
                $element = $matches[3];
                $this->isIndex[] = true;
            }
            // Disabled this behaviour as the syntax is not yet final
            //$pos = strpos($element, self::SINGULAR_SEPARATOR);
            $pos = false;
            $singular = null;

            if (false !== $pos) {
                $singular = substr($element, $pos + 1);
                $element = substr($element, 0, $pos);
            }

            $this->elements[] = $element;
            $this->singulars[] = $singular;

            $position += strlen($matches[1]);
            $remaining = $matches[4];
            $pattern = '/^(\.(\w+)|\[([^\]]+)\])(.*)/';
        }

        if ('' !== $remaining) {
            throw new InvalidPropertyPathException(sprintf(
                'Could not parse property path "%s". Unexpected token "%s" at position %d',
                $propertyPath,
                $remaining{0},
                $position
            ));
        }

        $this->length = count($this->elements);
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        return $this->pathAsString;
    }

    /**
     * {@inheritdoc}
     */
    public function getLength()
    {
        return $this->length;
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        if ($this->length <= 1) {
            return null;
        }

        $parent = clone $this;

        --$parent->length;
        $parent->pathAsString = substr($parent->pathAsString, 0, max(strrpos($parent->pathAsString, '.'), strrpos($parent->pathAsString, '[')));
        array_pop($parent->elements);
        array_pop($parent->singulars);
        array_pop($parent->isIndex);

        return $parent;
    }

    /**
     * Returns a new iterator for this path
     *
     * @return PropertyPathIteratorInterface
     */
    public function getIterator()
    {
        return new PropertyPathIterator($this);
    }

    /**
     * {@inheritdoc}
     */
    public function getElements()
    {
        return $this->elements;
    }

    /**
     * {@inheritdoc}
     */
    public function getElement($index)
    {
        if (!isset($this->elements[$index])) {
            throw new \OutOfBoundsException('The index ' . $index . ' is not within the property path');
        }

        return $this->elements[$index];
    }

    /**
     * {@inheritdoc}
     */
    public function isProperty($index)
    {
        if (!isset($this->isIndex[$index])) {
            throw new \OutOfBoundsException('The index ' . $index . ' is not within the property path');
        }

        return !$this->isIndex[$index];
    }

    /**
     * {@inheritdoc}
     */
    public function isIndex($index)
    {
        if (!isset($this->isIndex[$index])) {
            throw new \OutOfBoundsException('The index ' . $index . ' is not within the property path');
        }

        return $this->isIndex[$index];
    }

    /**
     * Returns the value at the end of the property path of the object
     *
     * Example:
     * <code>
     * $path = new PropertyPath('child.name');
     *
     * echo $path->getValue($object);
     * // equals echo $object->getChild()->getName();
     * </code>
     *
     * This method first tries to find a public getter for each property in the
     * path. The name of the getter must be the camel-cased property name
     * prefixed with "get", "is", or "has".
     *
     * If the getter does not exist, this method tries to find a public
     * property. The value of the property is then returned.
     *
     * If none of them are found, an exception is thrown.
     *
     * @param object|array $objectOrArray The object or array to traverse
     *
     * @return mixed The value at the end of the property path
     *
     * @throws InvalidPropertyException      If the property/getter does not exist
     * @throws PropertyAccessDeniedException If the property/getter exists but is not public
     */
    public function getValue($objectOrArray)
    {
        $propertyValues =& $this->readPropertiesUntil($objectOrArray, $this->length - 1);

        return $propertyValues[count($propertyValues) - 1][self::VALUE];
    }

    /**
     * Sets the value at the end of the property path of the object
     *
     * Example:
     * <code>
     * $path = new PropertyPath('child.name');
     *
     * echo $path->setValue($object, 'Fabien');
     * // equals echo $object->getChild()->setName('Fabien');
     * </code>
     *
     * This method first tries to find a public setter for each property in the
     * path. The name of the setter must be the camel-cased property name
     * prefixed with "set".
     *
     * If the setter does not exist, this method tries to find a public
     * property. The value of the property is then changed.
     *
     * If neither is found, an exception is thrown.
     *
     * @param object|array $objectOrArray The object or array to modify.
     * @param mixed        $value         The value to set at the end of the property path.
     *
     * @throws InvalidPropertyException      If a property does not exist.
     * @throws PropertyAccessDeniedException If a property cannot be accessed due to
     *                                       access restrictions (private or protected).
     * @throws UnexpectedTypeException       If a value within the path is neither object
     *                                       nor array.
     */
    public function setValue(&$objectOrArray, $value)
    {
        $propertyValues =& $this->readPropertiesUntil($objectOrArray, $this->length - 2);
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

                $property = $this->elements[$i];
                $singular = $this->singulars[$i];
                $isIndex = $this->isIndex[$i];

                $this->writeProperty($objectOrArray, $property, $singular, $isIndex, $value);
            }

            $value =& $objectOrArray;
            $overwrite = !$propertyValues[$i][self::IS_REF];
        }
    }

    /**
     * Reads the path from an object up to a given path index.
     *
     * @param object|array $objectOrArray The object or array to read from.
     * @param integer      $lastIndex     The integer up to which should be read.
     *
     * @return array The values read in the path.
     *
     * @throws UnexpectedTypeException If a value within the path is neither object nor array.
     */
    private function &readPropertiesUntil(&$objectOrArray, $lastIndex)
    {
        $propertyValues = array();

        for ($i = 0; $i <= $lastIndex; ++$i) {
            if (!is_object($objectOrArray) && !is_array($objectOrArray)) {
                throw new UnexpectedTypeException($objectOrArray, 'object or array');
            }

            $property = $this->elements[$i];
            $isIndex = $this->isIndex[$i];
            $isArrayAccess = is_array($objectOrArray) || $objectOrArray instanceof \ArrayAccess;

            // Create missing nested arrays on demand
            if ($isIndex && $isArrayAccess && !isset($objectOrArray[$property])) {
                $objectOrArray[$property] = $i + 1 < $this->length ? array() : null;
            }

            $propertyValue =& $this->readProperty($objectOrArray, $property, $isIndex);
            $objectOrArray =& $propertyValue[self::VALUE];

            $propertyValues[] =& $propertyValue;
        }

        return $propertyValues;
    }

    /**
     * Reads the a property from an object or array.
     *
     * @param object|array $objectOrArray The object or array to read from.
     * @param string       $property      The property to read.
     * @param Boolean      $isIndex       Whether to interpret the property as index.
     *
     * @return mixed The value of the read property
     *
     * @throws InvalidPropertyException      If the property does not exist.
     * @throws PropertyAccessDeniedException If the property cannot be accessed due to
     *                                       access restrictions (private or protected).
     */
    private function &readProperty(&$objectOrArray, $property, $isIndex)
    {
        // Use an array instead of an object since performance is
        // very crucial here
        $result = array(
            self::VALUE => null,
            self::IS_REF => false
        );

        if ($isIndex) {
            if (!$objectOrArray instanceof \ArrayAccess && !is_array($objectOrArray)) {
                throw new InvalidPropertyException(sprintf('Index "%s" cannot be read from object of type "%s" because it doesn\'t implement \ArrayAccess', $property, get_class($objectOrArray)));
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
            $reflClass = new ReflectionClass($objectOrArray);
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
                    throw new PropertyAccessDeniedException(sprintf('Property "%s" is not public in class "%s". Maybe you should create the method "%s()" or "%s()"?', $property, $reflClass->name, $getter, $isser));
                }

                $result[self::VALUE] =& $objectOrArray->$property;
                $result[self::IS_REF] = true;
            } elseif (property_exists($objectOrArray, $property)) {
                // needed to support \stdClass instances
                $result[self::VALUE] =& $objectOrArray->$property;
                $result[self::IS_REF] = true;
            } else {
                throw new InvalidPropertyException(sprintf('Neither property "%s" nor method "%s()" nor method "%s()" exists in class "%s"', $property, $getter, $isser, $reflClass->name));
            }
        } else {
            throw new InvalidPropertyException(sprintf('Cannot read property "%s" from an array. Maybe you should write the property path as "[%s]" instead?', $property, $property));
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
     * @param object|array $objectOrArray The object or array to write to.
     * @param string       $property      The property to write.
     * @param string|null  $singular      The singular form of the property name or null.
     * @param Boolean      $isIndex       Whether to interpret the property as index.
     * @param mixed        $value         The value to write.
     *
     * @throws InvalidPropertyException      If the property does not exist.
     * @throws PropertyAccessDeniedException If the property cannot be accessed due to
     *                                       access restrictions (private or protected).
     */
    private function writeProperty(&$objectOrArray, $property, $singular, $isIndex, $value)
    {
        $adderRemoverError = null;

        if ($isIndex) {
            if (!$objectOrArray instanceof \ArrayAccess && !is_array($objectOrArray)) {
                throw new InvalidPropertyException(sprintf('Index "%s" cannot be modified in object of type "%s" because it doesn\'t implement \ArrayAccess', $property, get_class($objectOrArray)));
            }

            $objectOrArray[$property] = $value;
        } elseif (is_object($objectOrArray)) {
            $reflClass = new ReflectionClass($objectOrArray);

            // The plural form is the last element of the property path
            $plural = $this->camelize($this->elements[$this->length - 1]);

            // Any of the two methods is required, but not yet known
            $singulars = null !== $singular ? array($singular) : (array) FormUtil::singularify($plural);

            if (is_array($value) || $value instanceof Traversable) {
                $methods = $this->findAdderAndRemover($reflClass, $singulars);
                if (null !== $methods) {
                    // At this point the add and remove methods have been found
                    // Use iterator_to_array() instead of clone in order to prevent side effects
                    // see https://github.com/symfony/symfony/issues/4670
                    $itemsToAdd = is_object($value) ? iterator_to_array($value) : $value;
                    $itemToRemove = array();
                    $propertyValue = $this->readProperty($objectOrArray, $property, $isIndex);
                    $previousValue = $propertyValue[self::VALUE];

                    if (is_array($previousValue) || $previousValue instanceof Traversable) {
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
                throw new InvalidPropertyException(sprintf('Neither element "%s" nor method "%s()" exists in class "%s"%s', $property, $setter, $reflClass->name, $adderRemoverError));
            }
        } else {
            throw new InvalidPropertyException(sprintf('Cannot write property "%s" in an array. Maybe you should write the property path as "[%s]" instead?', $property, $property));
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
     * @throws InvalidPropertyException      If the property does not exist.
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
                throw new InvalidPropertyException(sprintf(
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
    private function isAccessible(ReflectionClass $class, $methodName, $parameters)
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
