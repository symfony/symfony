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
 * An array-acting object that holds many ConstrainViolation instances.
 *
 * @api
 */
class ConstraintViolationList implements \IteratorAggregate, \Countable, \ArrayAccess
{
    protected $violations = array();

    /**
     * @return string
     */
    public function __toString()
    {
        $string = '';

        foreach ($this->violations as $violation) {
            $root = $violation->getRoot();
            $class = is_object($root) ? get_class($root) : $root;
            $string .= <<<EOF
{$class}.{$violation->getPropertyPath()}:
    {$violation->getMessage()}

EOF;
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
     * @param ConstraintViolationList $violations
     *
     * @api
     */
    public function addAll(ConstraintViolationList $violations)
    {
        foreach ($violations->violations as $violation) {
            $this->violations[] = $violation;
        }
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
        return isset($this->violations[$offset]);
    }

    /**
     * @see ArrayAccess
     *
     * @api
     */
    public function offsetGet($offset)
    {
        return isset($this->violations[$offset]) ? $this->violations[$offset] : null;
    }

    /**
     * @see ArrayAccess
     *
     * @api
     */
    public function offsetSet($offset, $value)
    {
        if (null === $offset) {
            $this->violations[] = $value;
        } else {
            $this->violations[$offset] = $value;
        }
    }

    /**
     * @see ArrayAccess
     *
     * @api
     */
    public function offsetUnset($offset)
    {
        unset($this->violations[$offset]);
    }

}
