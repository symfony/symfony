<?php

namespace Symfony\Component\OutputEscaper;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Output escaping iterator decorator.
 *
 * This takes an object that implements the Traversable interface and turns it
 * into an iterator with each value escaped.
 *
 * @see    Escaper
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author Mike Squire <mike@somosis.co.uk>
 */
class IteratorDecorator extends ObjectDecorator implements \Iterator
{
    /**
     * The iterator to be used.
     *
     * @var \IteratorIterator
     */
    private $iterator;

    /**
     * Constructs a new escaping iterator using the escaping method and value supplied.
     *
     * @param string       $escaper The escaping method to use
     * @param \Traversable $value   The iterator to escape
     */
    public function __construct($escaper, \Traversable $value)
    {
        // Set the original value for __call(). Set our own iterator because passing
        // it to IteratorIterator will lose any other method calls.

        parent::__construct($escaper, $value);

        $this->iterator = new \IteratorIterator($value);
    }

    /**
     * Resets the iterator (as required by the Iterator interface).
     *
     * @return bool true, if the iterator rewinds successfully otherwise false
     */
    public function rewind()
    {
        return $this->iterator->rewind();
    }

    /**
     * Escapes and gets the current element (as required by the Iterator interface).
     *
     * @return mixed The escaped value
     */
    public function current()
    {
        return Escaper::escape($this->escaper, $this->iterator->current());
    }

    /**
     * Gets the current key (as required by the Iterator interface).
     *
     * @return string Iterator key
     */
    public function key()
    {
        return $this->iterator->key();
    }

    /**
     * Moves to the next element in the iterator (as required by the Iterator interface).
     */
    public function next()
    {
        return $this->iterator->next();
    }

    /**
     * Returns whether the current element is valid or not (as required by the
     * Iterator interface).
     *
     * @return bool true if the current element is valid; false otherwise
     */
    public function valid()
    {
        return $this->iterator->valid();
    }
}
