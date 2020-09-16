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

use Symfony\Component\PropertyAccess\Exception\OutOfBoundsException;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PropertyPathBuilder
{
    private $elements = [];
    private $isIndex = [];

    /**
     * Creates a new property path builder.
     *
     * @param PropertyPathInterface|string|null $path The path to initially store
     *                                                in the builder. Optional.
     */
    public function __construct($path = null)
    {
        if (null !== $path) {
            $this->append($path);
        }
    }

    /**
     * Appends a (sub-) path to the current path.
     *
     * @param PropertyPathInterface|string $path   The path to append
     * @param int                          $offset The offset where the appended
     *                                             piece starts in $path
     * @param int                          $length The length of the appended piece
     *                                             If 0, the full path is appended
     */
    public function append($path, int $offset = 0, int $length = 0)
    {
        if (\is_string($path)) {
            $path = new PropertyPath($path);
        }

        if (0 === $length) {
            $end = $path->getLength();
        } else {
            $end = $offset + $length;
        }

        for (; $offset < $end; ++$offset) {
            $this->elements[] = $path->getElement($offset);
            $this->isIndex[] = $path->isIndex($offset);
        }
    }

    /**
     * Appends an index element to the current path.
     *
     * @param string $name The name of the appended index
     */
    public function appendIndex(string $name)
    {
        $this->elements[] = $name;
        $this->isIndex[] = true;
    }

    /**
     * Appends a property element to the current path.
     *
     * @param string $name The name of the appended property
     */
    public function appendProperty(string $name)
    {
        $this->elements[] = $name;
        $this->isIndex[] = false;
    }

    /**
     * Removes elements from the current path.
     *
     * @param int $offset The offset at which to remove
     * @param int $length The length of the removed piece
     *
     * @throws OutOfBoundsException if offset is invalid
     */
    public function remove(int $offset, int $length = 1)
    {
        if (!isset($this->elements[$offset])) {
            throw new OutOfBoundsException(sprintf('The offset "%s" is not within the property path.', $offset));
        }

        $this->resize($offset, $length, 0);
    }

    /**
     * Replaces a sub-path by a different (sub-) path.
     *
     * @param int                          $offset     The offset at which to replace
     * @param int                          $length     The length of the piece to replace
     * @param PropertyPathInterface|string $path       The path to insert
     * @param int                          $pathOffset The offset where the inserted piece
     *                                                 starts in $path
     * @param int                          $pathLength The length of the inserted piece
     *                                                 If 0, the full path is inserted
     *
     * @throws OutOfBoundsException If the offset is invalid
     */
    public function replace(int $offset, int $length, $path, int $pathOffset = 0, int $pathLength = 0)
    {
        if (\is_string($path)) {
            $path = new PropertyPath($path);
        }

        if ($offset < 0 && abs($offset) <= $this->getLength()) {
            $offset = $this->getLength() + $offset;
        } elseif (!isset($this->elements[$offset])) {
            throw new OutOfBoundsException('The offset '.$offset.' is not within the property path');
        }

        if (0 === $pathLength) {
            $pathLength = $path->getLength() - $pathOffset;
        }

        $this->resize($offset, $length, $pathLength);

        for ($i = 0; $i < $pathLength; ++$i) {
            $this->elements[$offset + $i] = $path->getElement($pathOffset + $i);
            $this->isIndex[$offset + $i] = $path->isIndex($pathOffset + $i);
        }
        ksort($this->elements);
    }

    /**
     * Replaces a property element by an index element.
     *
     * @param int    $offset The offset at which to replace
     * @param string $name   The new name of the element. Optional
     *
     * @throws OutOfBoundsException If the offset is invalid
     */
    public function replaceByIndex(int $offset, string $name = null)
    {
        if (!isset($this->elements[$offset])) {
            throw new OutOfBoundsException(sprintf('The offset "%s" is not within the property path.', $offset));
        }

        if (null !== $name) {
            $this->elements[$offset] = $name;
        }

        $this->isIndex[$offset] = true;
    }

    /**
     * Replaces an index element by a property element.
     *
     * @param int    $offset The offset at which to replace
     * @param string $name   The new name of the element. Optional
     *
     * @throws OutOfBoundsException If the offset is invalid
     */
    public function replaceByProperty(int $offset, string $name = null)
    {
        if (!isset($this->elements[$offset])) {
            throw new OutOfBoundsException(sprintf('The offset "%s" is not within the property path.', $offset));
        }

        if (null !== $name) {
            $this->elements[$offset] = $name;
        }

        $this->isIndex[$offset] = false;
    }

    /**
     * Returns the length of the current path.
     *
     * @return int The path length
     */
    public function getLength()
    {
        return \count($this->elements);
    }

    /**
     * Returns the current property path.
     *
     * @return PropertyPathInterface|null The constructed property path
     */
    public function getPropertyPath()
    {
        $pathAsString = $this->__toString();

        return '' !== $pathAsString ? new PropertyPath($pathAsString) : null;
    }

    /**
     * Returns the current property path as string.
     *
     * @return string The property path as string
     */
    public function __toString()
    {
        $string = '';

        foreach ($this->elements as $offset => $element) {
            if ($this->isIndex[$offset]) {
                $element = '['.$element.']';
            } elseif ('' !== $string) {
                $string .= '.';
            }

            $string .= $element;
        }

        return $string;
    }

    /**
     * Resizes the path so that a chunk of length $cutLength is
     * removed at $offset and another chunk of length $insertionLength
     * can be inserted.
     */
    private function resize(int $offset, int $cutLength, int $insertionLength)
    {
        // Nothing else to do in this case
        if ($insertionLength === $cutLength) {
            return;
        }

        $length = \count($this->elements);

        if ($cutLength > $insertionLength) {
            // More elements should be removed than inserted
            $diff = $cutLength - $insertionLength;
            $newLength = $length - $diff;

            // Shift elements to the left (left-to-right until the new end)
            // Max allowed offset to be shifted is such that
            // $offset + $diff < $length (otherwise invalid index access)
            // i.e. $offset < $length - $diff = $newLength
            for ($i = $offset; $i < $newLength; ++$i) {
                $this->elements[$i] = $this->elements[$i + $diff];
                $this->isIndex[$i] = $this->isIndex[$i + $diff];
            }

            // All remaining elements should be removed
            $this->elements = \array_slice($this->elements, 0, $i);
            $this->isIndex = \array_slice($this->isIndex, 0, $i);
        } else {
            $diff = $insertionLength - $cutLength;

            $newLength = $length + $diff;
            $indexAfterInsertion = $offset + $insertionLength;

            // $diff <= $insertionLength
            // $indexAfterInsertion >= $insertionLength
            // => $diff <= $indexAfterInsertion

            // In each of the following loops, $i >= $diff must hold,
            // otherwise ($i - $diff) becomes negative.

            // Shift old elements to the right to make up space for the
            // inserted elements. This needs to be done left-to-right in
            // order to preserve an ascending array index order
            // Since $i = max($length, $indexAfterInsertion) and $indexAfterInsertion >= $diff,
            // $i >= $diff is guaranteed.
            for ($i = max($length, $indexAfterInsertion); $i < $newLength; ++$i) {
                $this->elements[$i] = $this->elements[$i - $diff];
                $this->isIndex[$i] = $this->isIndex[$i - $diff];
            }

            // Shift remaining elements to the right. Do this right-to-left
            // so we don't overwrite elements before copying them
            // The last written index is the immediate index after the inserted
            // string, because the indices before that will be overwritten
            // anyway.
            // Since $i >= $indexAfterInsertion and $indexAfterInsertion >= $diff,
            // $i >= $diff is guaranteed.
            for ($i = $length - 1; $i >= $indexAfterInsertion; --$i) {
                $this->elements[$i] = $this->elements[$i - $diff];
                $this->isIndex[$i] = $this->isIndex[$i - $diff];
            }
        }
    }
}
