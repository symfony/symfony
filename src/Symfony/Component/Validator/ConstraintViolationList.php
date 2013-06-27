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
     * Converts the violation list into an associative array as follow:
     * <code>
     *     array(
     *         'root1' => array (
     *                        'property1.1' => array('message1.1', 'message1.2'),
     *                        'property1.2' => array('message2')
     *                    ),
     *         ........
     *     )
     * </code>
     * with the possibility to filter a given root or a given property.
     *
     * @param string $property The name of the property to filter
     * @param string $root The name of the root to filter
     *
     * @return array
     */
    public function toArray($property = null, $root = null)
    {
        $output = array();

        foreach ($this->violations as $violation) {
            $output[$violation->getRoot()][$violation->getPropertyPath()][] = $violation->getMessage();
        }

        if (null !== $root) {
            if (array_key_exists($root, $output)) {
                $output = array_intersect_key($output, array($root => 0));
            } else {
                return array();
            }
        }

        if (null !== $property) {
            foreach ($output as $key => $value) {
                if (array_key_exists($property, $value)) {
                    $output[$key] = array_intersect_key($output[$key], array($property => 0));
                } else {
                    unset($output[$key]);
                }
            }
        }

        return $output;
    }

    /**
     * {@inheritDoc}
     */
    public function add(ConstraintViolationInterface $violation)
    {
        $this->violations[] = $violation;
    }

    /**
     * {@inheritDoc}
     */
    public function addAll(ConstraintViolationListInterface $otherList)
    {
        foreach ($otherList as $violation) {
            $this->violations[] = $violation;
        }
    }

    /**
     * {@inheritDoc}
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
     */
    public function has($offset)
    {
        return isset($this->violations[$offset]);
    }

    /**
     * {@inheritDoc}
     */
    public function set($offset, ConstraintViolationInterface $violation)
    {
        $this->violations[$offset] = $violation;
    }

    /**
     * {@inheritDoc}
     */
    public function remove($offset)
    {
        unset($this->violations[$offset]);
    }

    /**
     * {@inheritDoc}
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->violations);
    }

    /**
     * {@inheritDoc}
     */
    public function count()
    {
        return count($this->violations);
    }

    /**
     * {@inheritDoc}
     */
    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    /**
     * {@inheritDoc}
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * {@inheritDoc}
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
     */
    public function offsetUnset($offset)
    {
        $this->remove($offset);
    }
}
