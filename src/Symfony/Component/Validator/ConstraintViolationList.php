<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator;

/**
 * A list of ConstrainViolation objects.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @api
 */
class ConstraintViolationList implements \IteratorAggregate, \Countable, \ArrayAccess
{
    /**
     * The constraint violations
     *
     * @var array
     */
    protected $violations = array();

    /**
     * Creates a new constraint violation list.
     *
     * @param array $violations The constraint violations to add to the list
     */
    public function __construct(array $violations = array())
    {
        foreach ($violations as $violation) {
            $this->add($violation);
        }
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $string = '';

        foreach ($this->violations as $violation) {
            $string .= $violation . "\n";
        }

        return $string;
    }

    /**
     * Add a ConstraintViolation to this list.
     *
     * @param ConstraintViolation $violation
     *
     * @api
     */
    public function add(ConstraintViolation $violation)
    {
        $this->violations[] = $violation;
    }

    /**
     * Merge an existing ConstraintViolationList into this list.
     *
     * @param ConstraintViolationList $otherList
     *
     * @api
     */
    public function addAll(ConstraintViolationList $otherList)
    {
        foreach ($otherList->violations as $violation) {
            $this->violations[] = $violation;
        }
    }

    /**
     * Returns the violation at a given offset.
     *
     * @param  integer $offset The offset of the violation.
     *
     * @return ConstraintViolation The violation.
     *
     * @throws \OutOfBoundsException If the offset does not exist.
     */
    public function get($offset)
    {
        if (!isset($this->violations[$offset])) {
            throw new \OutOfBoundsException(sprintf('The offset "%s" does not exist.', $offset));
        }

        return $this->violations[$offset];
    }

    /**
     * Returns whether the given offset exists.
     *
     * @param  integer $offset The violation offset.
     *
     * @return Boolean Whether the offset exists.
     */
    public function has($offset)
    {
        return isset($this->violations[$offset]);
    }

    /**
     * Sets a violation at a given offset.
     *
     * @param integer             $offset    The violation offset.
     * @param ConstraintViolation $violation The violation.
     */
    public function set($offset, ConstraintViolation $violation)
    {
        $this->violations[$offset] = $violation;
    }

    /**
     * Removes a violation at a given offset.
     *
     * @param integer $offset The offset to remove.
     */
    public function remove($offset)
    {
        unset($this->violations[$offset]);
    }

    /**
     * @see IteratorAggregate
     *
     * @api
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->violations);
    }

    /**
     * @see Countable
     *
     * @api
     */
    public function count()
    {
        return count($this->violations);
    }

    /**
     * @see ArrayAccess
     *
     * @api
     */
    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    /**
     * @see ArrayAccess
     *
     * @api
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * @see ArrayAccess
     *
     * @api
     */
    public function offsetSet($offset, $violation)
    {
        if (null === $offset) {
            $this->add($violation);
        } else {
            $this->set($offset, $violation);
        }
    }

    /**
     * @see ArrayAccess
     *
     * @api
     */
    public function offsetUnset($offset)
    {
        $this->remove($offset);
    }
}
