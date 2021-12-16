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
 * @implements \IteratorAggregate<int, ConstraintViolationInterface>
 */
class ConstraintViolationList implements \IteratorAggregate, ConstraintViolationListInterface
{
    /**
     * @var list<ConstraintViolationInterface>
     */
    private $violations = [];

    /**
     * Creates a new constraint violation list.
     *
     * @param iterable<mixed, ConstraintViolationInterface> $violations The constraint violations to add to the list
     */
    public function __construct(iterable $violations = [])
    {
        foreach ($violations as $violation) {
            $this->add($violation);
        }
    }

    public static function createFromMessage(string $message): self
    {
        $self = new self();
        $self->add(new ConstraintViolation($message, '', [], null, '', null));

        return $self;
    }

    /**
     * Converts the violation into a string for debugging purposes.
     *
     * @return string
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
     * {@inheritdoc}
     */
    public function add(ConstraintViolationInterface $violation)
    {
        $this->violations[] = $violation;
    }

    /**
     * {@inheritdoc}
     */
    public function addAll(ConstraintViolationListInterface $otherList)
    {
        foreach ($otherList as $violation) {
            $this->violations[] = $violation;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get(int $offset)
    {
        if (!isset($this->violations[$offset])) {
            throw new \OutOfBoundsException(sprintf('The offset "%s" does not exist.', $offset));
        }

        return $this->violations[$offset];
    }

    /**
     * {@inheritdoc}
     */
    public function has(int $offset)
    {
        return isset($this->violations[$offset]);
    }

    /**
     * {@inheritdoc}
     */
    public function set(int $offset, ConstraintViolationInterface $violation)
    {
        $this->violations[$offset] = $violation;
    }

    /**
     * {@inheritdoc}
     */
    public function remove(int $offset)
    {
        unset($this->violations[$offset]);
    }

    /**
     * {@inheritdoc}
     *
     * @return \ArrayIterator<int, ConstraintViolationInterface>
     */
    #[\ReturnTypeWillChange]
    public function getIterator()
    {
        return new \ArrayIterator($this->violations);
    }

    /**
     * @return int
     */
    #[\ReturnTypeWillChange]
    public function count()
    {
        return \count($this->violations);
    }

    /**
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    /**
     * {@inheritdoc}
     *
     * @return ConstraintViolationInterface
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $violation)
    {
        if (null === $offset) {
            $this->add($violation);
        } else {
            $this->set($offset, $violation);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        $this->remove($offset);
    }

    /**
     * Creates iterator for errors with specific codes.
     *
     * @param string|string[] $codes The codes to find
     *
     * @return static
     */
    public function findByCodes($codes)
    {
        $codes = (array) $codes;
        $violations = [];
        foreach ($this as $violation) {
            if (\in_array($violation->getCode(), $codes, true)) {
                $violations[] = $violation;
            }
        }

        return new static($violations);
    }
}
