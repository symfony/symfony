<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Tests\Fixtures;

/**
 * An iterable, mutable collection without any adder/remover on the owning
 * object: the PropertyAccessor is expected to populate it via its own
 * add()/removeElement() methods.
 */
class TestReadOnlyCollection implements \IteratorAggregate, \Countable
{
    private array $elements = [];

    public function add(mixed $element): void
    {
        $this->elements[] = $element;
    }

    public function removeElement(mixed $element): bool
    {
        $key = array_search($element, $this->elements, true);

        if (false === $key) {
            return false;
        }

        unset($this->elements[$key]);

        return true;
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->elements);
    }

    public function count(): int
    {
        return \count($this->elements);
    }
}
