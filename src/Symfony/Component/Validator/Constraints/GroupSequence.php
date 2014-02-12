<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

/**
 * Annotation for group sequences
 *
 * @Annotation
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @api
 */
class GroupSequence implements \ArrayAccess, \IteratorAggregate, \Countable
{
    /**
     * The members of the sequence
     * @var array
     */
    public $groups;

    public function __construct(array $groups)
    {
        // Support for Doctrine annotations
        $this->groups = isset($groups['value']) ? $groups['value'] : $groups;
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->groups);
    }

    public function offsetExists($offset)
    {
        return isset($this->groups[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->groups[$offset];
    }

    public function offsetSet($offset, $value)
    {
        if (null !== $offset) {
            $this->groups[$offset] = $value;

            return;
        }

        $this->groups[] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->groups[$offset]);
    }

    public function count()
    {
        return count($this->groups);
    }
}
