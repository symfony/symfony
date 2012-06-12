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
     * The elements of the property path
     * @var array
     */
    protected $elements = array();

    /**
     * The number of elements in the property path
     * @var integer
     */
    protected $length;

    /**
     * Contains a Boolean for each property in $elements denoting whether this
     * element is an index. It is a property otherwise.
     * @var array
     */
    protected $isIndex = array();

    /**
     * String representation of the path
     * @var string
     */
    protected $string;

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
            if ($matches[2] !== '') {
                $this->elements[] = $matches[2];
                $this->isIndex[] = false;
            } else {
                $this->elements[] = $matches[3];
                $this->isIndex[] = true;
            }

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
     * @param integer $index The index in the property path
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
     * @param integer $index The index in the property path
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
     * prefixed with "get" or "is".
     *
     * If the getter does not exist, this method tries to find a public
     * property. The value of the property is then returned.
     *
     * If neither is found, an exception is thrown.
     *
     * @param object|array $objectOrArray The object or array to traverse
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
     * @param object|array $objectOrArray The object or array to traverse
     * @param mixed        $value         The value at the end of the property path
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
     * @param object  $object       The object to read from
     * @param integer $currentIndex The index of the read property in the path
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
            $reflClass = new \ReflectionClass($object);
            $getter = 'get'.$camelProp;
            $isser = 'is'.$camelProp;

            if ($reflClass->hasMethod($getter)) {
                if (!$reflClass->getMethod($getter)->isPublic()) {
                    throw new PropertyAccessDeniedException(sprintf('Method "%s()" is not public in class "%s"', $getter, $reflClass->name));
                }

                return $object->$getter();
            } elseif ($reflClass->hasMethod($isser)) {
                if (!$reflClass->getMethod($isser)->isPublic()) {
                    throw new PropertyAccessDeniedException(sprintf('Method "%s()" is not public in class "%s"', $isser, $reflClass->name));
                }

                return $object->$isser();
            } elseif ($reflClass->hasMethod('__get')) {
                // needed to support magic method __get
                return $object->$property;
            } elseif ($reflClass->hasProperty($property)) {
                if (!$reflClass->getProperty($property)->isPublic()) {
                    throw new PropertyAccessDeniedException(sprintf('Property "%s" is not public in class "%s". Maybe you should create the method "%s()" or "%s()"?', $property, $reflClass->name, $getter, $isser));
                }

                return $object->$property;
            } elseif (property_exists($object, $property)) {
                // needed to support \stdClass instances
                return $object->$property;
            } else {
                throw new InvalidPropertyException(sprintf('Neither property "%s" nor method "%s()" nor method "%s()" exists in class "%s"', $property, $getter, $isser, $reflClass->name));
            }
        }
    }

    /**
     * Sets the value of the property at the given index in the path
     *
     * @param object  $objectOrArray The object or array to traverse
     * @param integer $currentIndex  The index of the modified property in the path
     * @param mixed   $value         The value to set
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
            $reflClass = new \ReflectionClass($objectOrArray);
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
                    throw new PropertyAccessDeniedException(sprintf('Property "%s" is not public in class "%s". Maybe you should create the method "%s()"?', $property, $reflClass->name, $setter));
                }

                $objectOrArray->$property = $value;
            } elseif (property_exists($objectOrArray, $property)) {
                // needed to support \stdClass instances
                $objectOrArray->$property = $value;
            } else {
                throw new InvalidPropertyException(sprintf('Neither element "%s" nor method "%s()" exists in class "%s"', $property, $setter, $reflClass->name));
            }
        } else {
            $objectOrArray[$property] = $value;
        }
    }

    protected function camelize($property)
    {
        return preg_replace_callback('/(^|_|\.)+(.)/', function ($match) { return ('.' === $match[1] ? '_' : '').strtoupper($match[2]); }, $property);
    }
}
