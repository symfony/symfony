<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\ResourceBundle\Util;

use Symfony\Component\Intl\Exception\OutOfBoundsException;

/**
 * Implements a ring buffer.
 *
 * A ring buffer is an array-like structure with a fixed size. If the buffer
 * is full, the next written element overwrites the first bucket in the buffer,
 * then the second and so on.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RingBuffer implements \ArrayAccess
{
    private $values = array();

    private $indices = array();

    private $cursor = 0;

    private $size;

    public function __construct($size)
    {
        $this->size = $size;
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($key)
    {
        return isset($this->indices[$key]);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($key)
    {
        if (!isset($this->indices[$key])) {
            throw new OutOfBoundsException(sprintf(
                'The index "%s" does not exist.',
                $key
            ));
        }

        return $this->values[$this->indices[$key]];
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($key, $value)
    {
        if (false !== ($keyToRemove = array_search($this->cursor, $this->indices))) {
            unset($this->indices[$keyToRemove]);
        }

        $this->values[$this->cursor] = $value;
        $this->indices[$key] = $this->cursor;

        $this->cursor = ($this->cursor + 1) % $this->size;
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($key)
    {
        if (isset($this->indices[$key])) {
            $this->values[$this->indices[$key]] = null;
            unset($this->indices[$key]);
        }
    }
}
