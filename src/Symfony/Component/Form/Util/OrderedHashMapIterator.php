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
 */
class OrderedHashMapIterator implements \Iterator
{
    /**
     * @var array
     */
    private $elements;

    /**
     * @var array
     */
    private $orderedKeys;

    /**
     * @var int
     */
    private $cursor;

    /**
     * @var int
     */
    private $cursorId;

    /**
     * @var array
     */
    private $managedCursors;

    /**
     * @var string|int|null
     */
    private $key;

    /**
     * @var mixed
     */
    private $current;

    /**
     * Creates a new iterator.
     *
     * @param array $elements       The elements of the map, indexed by their
     *                              keys
     * @param array $orderedKeys    The keys of the map in the order in which
     *                              they should be iterated
     * @param array $managedCursors An array from which to reference the
     *                              iterator's cursor as long as it is alive.
     *                              This array is managed by the corresponding
     *                              {@link OrderedHashMap} instance to support
     *                              recognizing the deletion of elements.
     */
    public function __construct(array &$elements, array &$orderedKeys, array &$managedCursors)
    {
        $this->elements = &$elements;
        $this->orderedKeys = &$orderedKeys;
        $this->managedCursors = &$managedCursors;
        $this->cursorId = \count($managedCursors);

        $this->managedCursors[$this->cursorId] = &$this->cursor;
    }

    /**
     * Removes the iterator's cursors from the managed cursors of the
     * corresponding {@link OrderedHashMap} instance.
     */
    public function __destruct()
    {
        // Use array_splice() instead of isset() to prevent holes in the
        // array indices, which would break the initialization of $cursorId
        array_splice($this->managedCursors, $this->cursorId, 1);
    }

    /**
     * {@inheritdoc}
     */
    public function current()
    {
        return $this->current;
    }

    /**
     * {@inheritdoc}
     */
    public function next()
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

    /**
     * {@inheritdoc}
     */
    public function key()
    {
        if (null === $this->key) {
            return null;
        }

        $array = array($this->key => null);

        return key($array);
    }

    /**
     * {@inheritdoc}
     */
    public function valid()
    {
        return null !== $this->key;
    }

    /**
     * {@inheritdoc}
     */
    public function rewind()
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
