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
 * Default implementation of {@ConstraintViolationListInterface}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @since v2.2.0
 */
class ConstraintViolationList implements \IteratorAggregate, ConstraintViolationListInterface
{
    /**
     * @var ConstraintViolationInterface[]
     */
    private $violations = array();

    /**
     * Creates a new constraint violation list.
     *
     * @param ConstraintViolationInterface[] $violations The constraint violations to add to the list
     *
     * @since v2.1.0
     */
    public function __construct(array $violations = array())
    {
        foreach ($violations as $violation) {
            $this->add($violation);
        }
    }

    /**
     * Converts the violation into a string for debugging purposes.
     *
     * @return string The violation as string.
     *
     * @since v2.0.0
     */
    public function __toString()
    {
        $string = '';

        foreach ($this->violations as $violation) {
            $string .= $violation."\n";
        }

        return $string;
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.2.0
     */
    public function add(ConstraintViolationInterface $violation)
    {
        $this->violations[] = $violation;
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.2.0
     */
    public function addAll(ConstraintViolationListInterface $otherList)
    {
        foreach ($otherList as $violation) {
            $this->violations[] = $violation;
        }
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.1.0
     */
    public function get($offset)
    {
        if (!isset($this->violations[$offset])) {
            throw new \OutOfBoundsException(sprintf('The offset "%s" does not exist.', $offset));
        }

        return $this->violations[$offset];
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.1.0
     */
    public function has($offset)
    {
        return isset($this->violations[$offset]);
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.2.0
     */
    public function set($offset, ConstraintViolationInterface $violation)
    {
        $this->violations[$offset] = $violation;
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.1.0
     */
    public function remove($offset)
    {
        unset($this->violations[$offset]);
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.0.0
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->violations);
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.0.0
     */
    public function count()
    {
        return count($this->violations);
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.0.0
     */
    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.0.0
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.1.0
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
     * {@inheritDoc}
     *
     * @since v2.0.0
     */
    public function offsetUnset($offset)
    {
        $this->remove($offset);
    }
}
