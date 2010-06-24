<?php

namespace Symfony\Components\Form;

use Symfony\Components\Form\Exception\InvalidPropertyPathException;

/**
 * Allows easy traversing of a property path
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class PropertyPath
{
    /**
     * The current index of the traversal
     * @var integer
     */
    protected $currentIndex = 0;

    /**
     * The elements of the property path
     * @var array
     */
    protected $elements = array();

    /**
     * Contains a boolean for each property in $elements denoting whether this
     * element is a property. It is an index otherwise.
     * @var array
     */
    protected $isProperty = array();

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
        if (empty($propertyPath)) {
            throw new InvalidPropertyPathException('The property path must not be empty');
        }

        $this->string = $propertyPath;
        $position = 0;
        $remaining = $propertyPath;

        // first element is evaluated differently - no leading dot for properties
        $pattern = '/^((\w+)|\[(\w+)\])(.*)/';

        while (preg_match($pattern, $remaining, $matches)) {
            if (!empty($matches[2])) {
                $this->elements[] = $matches[2];
                $this->isProperty[] = true;
            } else {
                $this->elements[] = $matches[3];
                $this->isProperty[] = false;
            }

            $position += strlen($matches[1]);
            $remaining = $matches[4];
            $pattern = '/^(\.(\w+)|\[(\w+)\])(.*)/';
        }

        if (!empty($remaining)) {
            throw new InvalidPropertyPathException(sprintf(
                'Could not parse property path "%s". Unexpected token "%s" at position %d',
                $propertyPath,
                $remaining{0},
                $position
            ));
        }
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
     * Returns the current element of the path
     *
     * @return string
     */
    public function getCurrent()
    {
        return $this->elements[$this->currentIndex];
    }

    /**
     * Returns whether the current element is a property
     *
     * @return boolean
     */
    public function isProperty()
    {
        return $this->isProperty[$this->currentIndex];
    }

    /**
     * Returns whether the currente element is an array index
     *
     * @return boolean
     */
    public function isIndex()
    {
        return !$this->isProperty();
    }

    /**
     * Returns whether there is a next element in the path
     *
     * @return boolean
     */
    public function hasNext()
    {
        return isset($this->elements[$this->currentIndex + 1]);
    }

    /**
     * Sets the internal cursor to the next element in the path
     *
     * Use hasNext() to verify whether there is a next element before calling this
     * method, otherwise an exception will be thrown.
     *
     * @throws OutOfBoundsException  If there is no next element
     */
    public function next()
    {
        if (!$this->hasNext()) {
            throw new \OutOfBoundsException('There is no next element in the path');
        }

        ++$this->currentIndex;
    }

    /**
     * Sets the internal cursor to the first element in the path
     */
    public function rewind()
    {
        $this->currentIndex = 0;
    }
}
