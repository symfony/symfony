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
 * Iterator that traverses an array.
 *
 * Contrary to {@link \ArrayIterator}, this iterator recognizes changes in the
 * original array during iteration.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ReferencingArrayIterator implements \Iterator
{
    /**
     * @var array
     */
    private $array;

    /**
     * Creates a new iterator.
     *
     * @param array $array An array
     */
    public function __construct(array &$array)
    {
        $this->array = &$array;
    }

    /**
     *{@inheritdoc}
     */
    public function current()
    {
        return current($this->array);
    }

    /**
     *{@inheritdoc}
     */
    public function next()
    {
        next($this->array);
    }

    /**
     *{@inheritdoc}
     */
    public function key()
    {
        return key($this->array);
    }

    /**
     *{@inheritdoc}
     */
    public function valid()
    {
        return null !== key($this->array);
    }

    /**
     *{@inheritdoc}
     */
    public function rewind()
    {
        reset($this->array);
    }
}
