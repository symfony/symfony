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
    private array $violations = [];

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

    public function __toString(): string
    {
        $string = '';

        foreach ($this->violations as $violation) {
            $string .= $violation."\n";
        }

        return $string;
    }

    public function add(ConstraintViolationInterface $violation)
    {
        $this->violations[] = $violation;
    }

    public function addAll(ConstraintViolationListInterface $otherList)
    {
        foreach ($otherList as $violation) {
            $this->violations[] = $violation;
        }
    }

    public function get(int $offset): ConstraintViolationInterface
    {
        if (!isset($this->violations[$offset])) {
            throw new \OutOfBoundsException(sprintf('The offset "%s" does not exist.', $offset));
        }

        return $this->violations[$offset];
    }

    public function has(int $offset): bool
    {
        return isset($this->violations[$offset]);
    }

    public function set(int $offset, ConstraintViolationInterface $violation)
    {
        $this->violations[$offset] = $violation;
    }

    public function remove(int $offset)
    {
        unset($this->violations[$offset]);
    }

    /**
     * @return \ArrayIterator<int, ConstraintViolationInterface>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->violations);
    }

    public function count(): int
    {
        return \count($this->violations);
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    public function offsetGet(mixed $offset): ConstraintViolationInterface
    {
        return $this->get($offset);
    }

    public function offsetSet(mixed $offset, mixed $violation): void
    {
        if (null === $offset) {
            $this->add($violation);
        } else {
            $this->set($offset, $violation);
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->remove($offset);
    }

    /**
     * Creates iterator for errors with specific codes.
     *
     * @param string|string[] $codes The codes to find
     */
    public function findByCodes(string|array $codes): static
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
