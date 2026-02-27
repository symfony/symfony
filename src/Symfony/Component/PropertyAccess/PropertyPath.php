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
 *
 * @implements \IteratorAggregate<int, string>
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
     * @var list<string>
     */
    private array $elements = [];

    /**
     * The number of elements in the property path.
     */
    private int $length;

    /**
     * Contains a Boolean for each property in $elements denoting whether this
     * element is an index. It is a property otherwise.
     *
     * @var array<bool>
     */
    private array $isIndex = [];

    /**
     * Contains a Boolean for each property in $elements denoting whether this
     * element is optional or not.
     *
     * @var array<bool>
     */
    private array $isNullSafe = [];

    /**
     * String representation of the path.
     */
    private string $pathAsString;

    /**
     * Constructs a property path from a string.
     *
     * @throws InvalidArgumentException     If the given path is not a string
     * @throws InvalidPropertyPathException If the syntax of the property path is not valid
     */
    public function __construct(self|string $propertyPath)
    {
        // Can be used as copy constructor
        if ($propertyPath instanceof self) {
            $this->elements = $propertyPath->elements;
            $this->length = $propertyPath->length;
            $this->isIndex = $propertyPath->isIndex;
            $this->isNullSafe = $propertyPath->isNullSafe;
            $this->pathAsString = $propertyPath->pathAsString;

            return;
        }

        if ('' === $propertyPath) {
            throw new InvalidPropertyPathException('The property path should not be empty.');
        }

        $this->pathAsString = $propertyPath;
        $position = 0;
        $remaining = $propertyPath;

        // first element is evaluated differently - no leading dot for properties
        $pattern = '/^(((?:[^\\\\.\[]|\\\\.)++)|\[([^\]]++)\])(.*)/';

        while (preg_match($pattern, $remaining, $matches)) {
            if ('' !== $matches[2]) {
                $element = $matches[2];
                $this->isIndex[] = false;
            } else {
                $element = $matches[3];
                $this->isIndex[] = true;
            }

            // Mark as optional when last character is "?".
            if (str_ends_with($element, '?')) {
                $this->isNullSafe[] = true;
                $element = substr($element, 0, -1);
            } else {
                $this->isNullSafe[] = false;
            }

            $element = preg_replace('/\\\([.[])/', '$1', $element);
            if (str_ends_with($element, '\\\\')) {
                $element = substr($element, 0, -1);
            }
            $this->elements[] = $element;

            $position += \strlen($matches[1]);
            $remaining = $matches[4];
            $pattern = '/^(\.((?:[^\\\\.\[]|\\\\.)++)|\[([^\]]++)\])(.*)/';
        }

        if ('' !== $remaining) {
            throw new InvalidPropertyPathException(\sprintf('Could not parse property path "%s". Unexpected token "%s" at position %d.', $propertyPath, $remaining[0], $position));
        }

        $this->length = \count($this->elements);
    }

    public function __toString(): string
    {
        return $this->pathAsString;
    }

    public function getLength(): int
    {
        return $this->length;
    }

    public function getParent(): ?PropertyPathInterface
    {
        if ($this->length <= 1) {
            return null;
        }

        $parent = clone $this;

        --$parent->length;
        $parent->pathAsString = substr($parent->pathAsString, 0, max(strrpos($parent->pathAsString, '.'), strrpos($parent->pathAsString, '[')));
        array_pop($parent->elements);
        array_pop($parent->isIndex);
        array_pop($parent->isNullSafe);

        return $parent;
    }

    /**
     * Returns a new iterator for this path.
     */
    public function getIterator(): PropertyPathIteratorInterface
    {
        return new PropertyPathIterator($this);
    }

    public function getElements(): array
    {
        return $this->elements;
    }

    public function getElement(int $index): string
    {
        if (!isset($this->elements[$index])) {
            throw new OutOfBoundsException(\sprintf('The index "%s" is not within the property path.', $index));
        }

        return $this->elements[$index];
    }

    public function isProperty(int $index): bool
    {
        if (!isset($this->isIndex[$index])) {
            throw new OutOfBoundsException(\sprintf('The index "%s" is not within the property path.', $index));
        }

        return !$this->isIndex[$index];
    }

    public function isIndex(int $index): bool
    {
        if (!isset($this->isIndex[$index])) {
            throw new OutOfBoundsException(\sprintf('The index "%s" is not within the property path.', $index));
        }

        return $this->isIndex[$index];
    }

    public function isNullSafe(int $index): bool
    {
        if (!isset($this->isNullSafe[$index])) {
            throw new OutOfBoundsException(\sprintf('The index "%s" is not within the property path.', $index));
        }

        return $this->isNullSafe[$index];
    }
}
