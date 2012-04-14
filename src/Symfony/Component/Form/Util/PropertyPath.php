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
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class PropertyPath implements \IteratorAggregate
{
    /**
     * Character used for separating between plural and singular of an element.
     * @var string
     */
    const SINGULAR_SEPARATOR = '|';

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
    private $string;

    /**
     * Positions where the individual elements start in the string representation
     * @var array
     */
    private $positions;

    /**
     * Parses the given property path
     *
     * @param string $propertyPath
     */
    public function __construct($propertyPath)
    {
        if (null === $propertyPath) {
            throw new InvalidPropertyPathException('The property path must not be empty');
        }

        $this->string = (string) $propertyPath;
        $position = 0;
        $remaining = $propertyPath;

        // first element is evaluated differently - no leading dot for properties
        $pattern = '/^(([^\.\[]+)|\[([^\]]+)\])(.*)/';

        while (preg_match($pattern, $remaining, $matches)) {
            $this->positions[] = $position;

            if ('' !== $matches[2]) {
                $element = $matches[2];
                $this->isIndex[] = false;
            } else {
                $element = $matches[3];
                $this->isIndex[] = true;
            }

            $pos = strpos($element, self::SINGULAR_SEPARATOR);
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

        if (!empty($remaining)) {
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
     * Returns the string representation of the property path
     *
     * @return string
     */
    public function __toString()
    {
        return $this->string;
    }

    /**
     * Returns the length of the property path.
     *
     * @return integer
     */
    public function getLength()
    {
        return $this->length;
    }

    /**
     * Returns the parent property path.
     *
     * The parent property path is the one that contains the same items as
     * this one except for the last one.
     *
     * If this property path only contains one item, null is returned.
     *
     * @return PropertyPath The parent path or null.
     */
    public function getParent()
    {
        if ($this->length <= 1) {
            return null;
        }

        $parent = clone $this;

        --$parent->length;
        $parent->string = substr($parent->string, 0, $parent->positions[$parent->length]);
        array_pop($parent->elements);
        array_pop($parent->singulars);
        array_pop($parent->isIndex);
        array_pop($parent->positions);

        return $parent;
    }

    /**
     * Returns a new iterator for this path
     *
     * @return PropertyPathIterator
     */
    public function getIterator()
    {
        return new PropertyPathIterator($this);
    }

    /**
     * Returns the elements of the property path as array
     *
     * @return array   An array of property/index names
     */
    public function getElements()
    {
        return $this->elements;
    }

    /**
     * Returns the element at the given index in the property path
     *
     * @param integer $index The index key
     *
     * @return string  A property or index name
     */
    public function getElement($index)
    {
        return $this->elements[$index];
    }

    /**
     * Returns whether the element at the given index is a property
     *
     * @param  integer $index  The index in the property path
     *
     * @return Boolean         Whether the element at this index is a property
     */
    public function isProperty($index)
    {
        return !$this->isIndex[$index];
    }

    /**
     * Returns whether the element at the given index is an array index
     *
     * @param  integer $index  The index in the property path
     *
     * @return Boolean         Whether the element at this index is an array index
     */
    public function isIndex($index)
    {
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
     * @param  object|array $objectOrArray   The object or array to traverse
     *
     * @return mixed                         The value at the end of the property path
     *
     * @throws InvalidPropertyException      If the property/getter does not exist
     * @throws PropertyAccessDeniedException If the property/getter exists but is not public
     */
    public function getValue($objectOrArray)
    {
        for ($i = 0; $i < $this->length; ++$i) {
            if (is_object($objectOrArray)) {
                $value = $this->readProperty($objectOrArray, $i);
            // arrays need to be treated separately (due to PHP bug?)
            // http://bugs.php.net/bug.php?id=52133
            } elseif (is_array($objectOrArray)) {
                $property = $this->elements[$i];
                if (!array_key_exists($property, $objectOrArray)) {
                    $objectOrArray[$property] = $i + 1 < $this->length ? array() : null;
                }
                $value =& $objectOrArray[$property];
            } else {
                throw new UnexpectedTypeException($objectOrArray, 'object or array');
            }

            $objectOrArray =& $value;
        }

        return $value;
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
     * @param  object|array $objectOrArray    The object or array to traverse
     * @param  mixed        $value            The value at the end of the property path
     *
     * @throws InvalidPropertyException       If the property/setter does not exist
     * @throws PropertyAccessDeniedException  If the property/setter exists but is not public
     */
    public function setValue(&$objectOrArray, $value)
    {
        for ($i = 0, $l = $this->length - 1; $i < $l; ++$i) {

            if (is_object($objectOrArray)) {
                $nestedObject = $this->readProperty($objectOrArray, $i);
            // arrays need to be treated separately (due to PHP bug?)
            // http://bugs.php.net/bug.php?id=52133
            } elseif (is_array($objectOrArray)) {
                $property = $this->elements[$i];
                if (!array_key_exists($property, $objectOrArray)) {
                    $objectOrArray[$property] = array();
                }
                $nestedObject =& $objectOrArray[$property];
            } else {
                throw new UnexpectedTypeException($objectOrArray, 'object or array');
            }

            $objectOrArray =& $nestedObject;
        }

        if (!is_object($objectOrArray) && !is_array($objectOrArray)) {
            throw new UnexpectedTypeException($objectOrArray, 'object or array');
        }

        $this->writeProperty($objectOrArray, $i, $value);
    }

    /**
     * Reads the value of the property at the given index in the path
     *
     * @param  object $object         The object to read from
     * @param  integer $currentIndex  The index of the read property in the path
     *
     * @return mixed                  The value of the property
     */
    protected function readProperty($object, $currentIndex)
    {
        $property = $this->elements[$currentIndex];

        if ($this->isIndex[$currentIndex]) {
            if (!$object instanceof \ArrayAccess) {
                throw new InvalidPropertyException(sprintf('Index "%s" cannot be read from object of type "%s" because it doesn\'t implement \ArrayAccess', $property, get_class($object)));
            }

            if (isset($object[$property])) {
                return $object[$property];
            }
        } else {
            $camelProp = $this->camelize($property);
            $reflClass = new ReflectionClass($object);
            $getter = 'get'.$camelProp;
            $isser = 'is'.$camelProp;
            $hasser = 'has'.$camelProp;

            if ($reflClass->hasMethod($getter)) {
                if (!$reflClass->getMethod($getter)->isPublic()) {
                    throw new PropertyAccessDeniedException(sprintf('Method "%s()" is not public in class "%s"', $getter, $reflClass->getName()));
                }

                return $object->$getter();
            } elseif ($reflClass->hasMethod($isser)) {
                if (!$reflClass->getMethod($isser)->isPublic()) {
                    throw new PropertyAccessDeniedException(sprintf('Method "%s()" is not public in class "%s"', $isser, $reflClass->getName()));
                }

                return $object->$isser();
            } elseif ($reflClass->hasMethod($hasser)) {
                if (!$reflClass->getMethod($hasser)->isPublic()) {
                    throw new PropertyAccessDeniedException(sprintf('Method "%s()" is not public in class "%s"', $hasser, $reflClass->getName()));
                }

                return $object->$hasser();
            } elseif ($reflClass->hasMethod('__get')) {
                // needed to support magic method __get
                return $object->$property;
            } elseif ($reflClass->hasProperty($property)) {
                if (!$reflClass->getProperty($property)->isPublic()) {
                    throw new PropertyAccessDeniedException(sprintf('Property "%s" is not public in class "%s". Maybe you should create the method "%s()" or "%s()"?', $property, $reflClass->getName(), $getter, $isser));
                }

                return $object->$property;
            } elseif (property_exists($object, $property)) {
                // needed to support \stdClass instances
                return $object->$property;
            } else {
                throw new InvalidPropertyException(sprintf('Neither property "%s" nor method "%s()" nor method "%s()" exists in class "%s"', $property, $getter, $isser, $reflClass->getName()));
            }
        }
    }

    /**
     * Sets the value of the property at the given index in the path
     *
     * @param object  $objectOrArray The object or array to traverse
     * @param integer $currentIndex  The index of the modified property in the path
     * @param mixed $value           The value to set
     */
    protected function writeProperty(&$objectOrArray, $currentIndex, $value)
    {
        $property = $this->elements[$currentIndex];

        if (is_object($objectOrArray) && $this->isIndex[$currentIndex]) {
            if (!$objectOrArray instanceof \ArrayAccess) {
                throw new InvalidPropertyException(sprintf('Index "%s" cannot be modified in object of type "%s" because it doesn\'t implement \ArrayAccess', $property, get_class($objectOrArray)));
            }

            $objectOrArray[$property] = $value;
        } elseif (is_object($objectOrArray)) {
            $reflClass = new ReflectionClass($objectOrArray);
            $setter = 'set'.$this->camelize($property);
            $addMethod = null;
            $removeMethod = null;
            $plural = null;

            // Check if the parent has matching methods to add/remove items
            if (is_array($value) || $value instanceof Traversable) {
                $singular = $this->singulars[$currentIndex];
                if (null !== $singular) {
                    $addMethod = 'add' . ucfirst($singular);
                    $removeMethod = 'remove' . ucfirst($singular);

                    if (!$this->isAccessible($reflClass, $addMethod, 1)) {
                        throw new InvalidPropertyException(sprintf(
                            'The public method "%s" with exactly one required parameter was not found on class %s',
                            $addMethod,
                            $reflClass->getName()
                        ));
                    }

                    if (!$this->isAccessible($reflClass, $removeMethod, 1)) {
                        throw new InvalidPropertyException(sprintf(
                            'The public method "%s" with exactly one required parameter was not found on class %s',
                            $removeMethod,
                            $reflClass->getName()
                        ));
                    }
                } else {
                    // The plural form is the last element of the property path
                    $plural = ucfirst($this->elements[$this->length - 1]);

                    // Any of the two methods is required, but not yet known
                    $singulars = (array) FormUtil::singularify($plural);

                    foreach ($singulars as $singular) {
                        $addMethodName = 'add' . $singular;
                        $removeMethodName = 'remove' . $singular;

                        if ($this->isAccessible($reflClass, $addMethodName, 1)) {
                            $addMethod = $addMethodName;
                        }

                        if ($this->isAccessible($reflClass, $removeMethodName, 1)) {
                            $removeMethod = $removeMethodName;
                        }

                        if ($addMethod && !$removeMethod) {
                            throw new InvalidPropertyException(sprintf(
                                'Found the public method "%s", but did not find a public "%s" on class %s',
                                $addMethodName,
                                $removeMethodName,
                                $reflClass->getName()
                            ));
                        }

                        if ($removeMethod && !$addMethod) {
                            throw new InvalidPropertyException(sprintf(
                                'Found the public method "%s", but did not find a public "%s" on class %s',
                                $removeMethodName,
                                $addMethodName,
                                $reflClass->getName()
                            ));
                        }

                        if ($addMethod && $removeMethod) {
                            break;
                        }
                    }
                }
            }

            // Collection with matching adder/remover in $objectOrArray
            if ($addMethod && $removeMethod) {
                $itemsToAdd = is_object($value) ? clone $value : $value;
                $previousValue = $this->readProperty($objectOrArray, $currentIndex);

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

                        // Item not found, remove
                        $objectOrArray->$removeMethod($previousItem);
                    }
                }

                foreach ($itemsToAdd as $item) {
                    $objectOrArray->$addMethod($item);
                }
            } elseif ($reflClass->hasMethod($setter)) {
                if (!$reflClass->getMethod($setter)->isPublic()) {
                    throw new PropertyAccessDeniedException(sprintf('Method "%s()" is not public in class "%s"', $setter, $reflClass->getName()));
                }

                $objectOrArray->$setter($value);
            } elseif ($reflClass->hasMethod('__set')) {
                // needed to support magic method __set
                $objectOrArray->$property = $value;
            } elseif ($reflClass->hasProperty($property)) {
                if (!$reflClass->getProperty($property)->isPublic()) {
                    throw new PropertyAccessDeniedException(sprintf('Property "%s" is not public in class "%s". Maybe you should create the method "%s()"?', $property, $reflClass->getName(), $setter));
                }

                $objectOrArray->$property = $value;
            } elseif (property_exists($objectOrArray, $property)) {
                // needed to support \stdClass instances
                $objectOrArray->$property = $value;
            } else {
                throw new InvalidPropertyException(sprintf('Neither element "%s" nor method "%s()" exists in class "%s"', $property, $setter, $reflClass->getName()));
            }
        } else {
            $objectOrArray[$property] = $value;
        }
    }

    protected function camelize($property)
    {
        return preg_replace_callback('/(^|_|\.)+(.)/', function ($match) { return ('.' === $match[1] ? '_' : '').strtoupper($match[2]); }, $property);
    }

    private function isAccessible(ReflectionClass $reflClass, $methodName, $numberOfRequiredParameters)
    {
        if ($reflClass->hasMethod($methodName)) {
            $method = $reflClass->getMethod($methodName);

            if ($method->isPublic() && $method->getNumberOfRequiredParameters() === $numberOfRequiredParameters) {
                return true;
            }
        }

        return false;
    }
}
