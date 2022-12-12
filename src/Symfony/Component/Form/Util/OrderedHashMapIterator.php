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

/**
 * Iterator for {@link OrderedHashMap} objects.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 *
 * @template-covariant TValue
 *
 * @implements \Iterator<string, TValue>
 */
class OrderedHashMapIterator implements \Iterator
{
    /** @var TValue[] */
    private array $elements;
    /** @var list<string> */
    private array $orderedKeys;
    private int $cursor = 0;
    private int $cursorId;
    /** @var array<int, int> */
    private array $managedCursors;
    private string|null $key = null;
    /** @var TValue|null */
    private mixed $current = null;

    /**
     * @param TValue[]        $elements       The elements of the map, indexed by their
     *                                        keys
     * @param list<string>    $orderedKeys    The keys of the map in the order in which
     *                                        they should be iterated
     * @param array<int, int> $managedCursors An array from which to reference the
     *                                        iterator's cursor as long as it is alive.
     *                                        This array is managed by the corresponding
     *                                        {@link OrderedHashMap} instance to support
     *                                        recognizing the deletion of elements.
     */
    public function __construct(array &$elements, array &$orderedKeys, array &$managedCursors)
    {
        $this->elements = &$elements;
        $this->orderedKeys = &$orderedKeys;
        $this->managedCursors = &$managedCursors;
        $this->cursorId = \count($managedCursors);

        $this->managedCursors[$this->cursorId] = &$this->cursor;
    }

    public function __sleep(): array
    {
        throw new \BadMethodCallException('Cannot serialize '.__CLASS__);
    }

    public function __wakeup()
    {
        throw new \BadMethodCallException('Cannot unserialize '.__CLASS__);
    }

    /**
     * Removes the iterator's cursors from the managed cursors of the
     * corresponding {@link OrderedHashMap} instance.
     */
    public function __destruct()
    {
        // Use array_splice() instead of unset() to prevent holes in the
        // array indices, which would break the initialization of $cursorId
        array_splice($this->managedCursors, $this->cursorId, 1);
    }

    public function current(): mixed
    {
        return $this->current;
    }

    public function next(): void
    {
        ++$this->cursor;

        if (isset($this->orderedKeys[$this->cursor])) {
            $this->key = $this->orderedKeys[$this->cursor];
            $this->current = $this->elements[$this->key];
        } else {
            $this->key = null;
            $this->current = null;
        }
    }

    public function key(): mixed
    {
        if (null === $this->key) {
            return null;
        }

        return $this->key;
    }

    public function valid(): bool
    {
        return null !== $this->key;
    }

    public function rewind(): void
    {
        $this->cursor = 0;

        if (isset($this->orderedKeys[0])) {
            $this->key = $this->orderedKeys[0];
            $this->current = $this->elements[$this->key];
        } else {
            $this->key = null;
            $this->current = null;
        }
    }
}
