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

use Symfony\Component\PropertyAccess\Exception\InvalidArgumentException;
use Symfony\Component\PropertyAccess\Exception\InvalidPropertyPathException;
use Symfony\Component\PropertyAccess\Exception\OutOfBoundsException;

/**
 * Default implementation of {@link PropertyPathInterface}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PropertyPath implements \IteratorAggregate, PropertyPathInterface
{
    /**
     * Character used for separating between plural and singular of an element.
     */
    public const SINGULAR_SEPARATOR = '|';

    /**
     * The elements of the property path.
     *
     * @var array
     */
    private $elements = [];

    /**
     * The number of elements in the property path.
     *
     * @var int
     */
    private $length;

    /**
     * Contains a Boolean for each property in $elements denoting whether this
     * element is an index. It is a property otherwise.
     *
     * @var array
     */
    private $isIndex = [];

    /**
     * String representation of the path.
     *
     * @var string
     */
    private $pathAsString;

    /**
     * Constructs a property path from a string.
     *
     * @param PropertyPath|string $propertyPath The property path as string or instance
     *
     * @throws InvalidArgumentException     If the given path is not a string
     * @throws InvalidPropertyPathException If the syntax of the property path is not valid
     */
    public function __construct($propertyPath)
    {
        // Can be used as copy constructor
        if ($propertyPath instanceof self) {
            /* @var PropertyPath $propertyPath */
            $this->elements = $propertyPath->elements;
            $this->length = $propertyPath->length;
            $this->isIndex = $propertyPath->isIndex;
            $this->pathAsString = $propertyPath->pathAsString;

            return;
        }
        if (!\is_string($propertyPath)) {
            throw new InvalidArgumentException(sprintf('The property path constructor needs a string or an instance of "Symfony\Component\PropertyAccess\PropertyPath". Got: "%s".', \is_object($propertyPath) ? \get_class($propertyPath) : \gettype($propertyPath)));
        }

        if ('' === $propertyPath) {
            throw new InvalidPropertyPathException('The property path should not be empty.');
        }

        $this->pathAsString = $propertyPath;
        $position = 0;
        $remaining = $propertyPath;

        // first element is evaluated differently - no leading dot for properties
        $pattern = '/^(([^\.\[]++)|\[([^\]]++)\])(.*)/';

        while (preg_match($pattern, $remaining, $matches)) {
            if ('' !== $matches[2]) {
                $element = $matches[2];
                $this->isIndex[] = false;
            } else {
                $element = $matches[3];
                $this->isIndex[] = true;
            }

            $this->elements[] = $element;

            $position += \strlen($matches[1]);
            $remaining = $matches[4];
            $pattern = '/^(\.([^\.|\[]++)|\[([^\]]++)\])(.*)/';
        }

        if ('' !== $remaining) {
            throw new InvalidPropertyPathException(sprintf('Could not parse property path "%s". Unexpected token "%s" at position %d.', $propertyPath, $remaining[0], $position));
        }

        $this->length = \count($this->elements);
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
        array_pop($parent->isIndex);

        return $parent;
    }

    /**
     * Returns a new iterator for this path.
     *
     * @return PropertyPathIteratorInterface
     */
    #[\ReturnTypeWillChange]
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
            throw new OutOfBoundsException(sprintf('The index "%s" is not within the property path.', $index));
        }

        return $this->elements[$index];
    }

    /**
     * {@inheritdoc}
     */
    public function isProperty($index)
    {
        if (!isset($this->isIndex[$index])) {
            throw new OutOfBoundsException(sprintf('The index "%s" is not within the property path.', $index));
        }

        return !$this->isIndex[$index];
    }

    /**
     * {@inheritdoc}
     */
    public function isIndex($index)
    {
        if (!isset($this->isIndex[$index])) {
            throw new OutOfBoundsException(sprintf('The index "%s" is not within the property path.', $index));
        }

        return $this->isIndex[$index];
    }
}
