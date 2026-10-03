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

use Symfony\Component\PropertyInfo\Attribute\WithCollectionAccessors;

/**
 * An iterable, mutable collection that uses custom mutator names instead of
 * the default add()/removeElement(). The names are declared directly on the
 * collection class via WithCollectionAccessors, so any owner property typed
 * with this class is handled without further annotation.
 */
#[WithCollectionAccessors(adder: 'push', remover: 'shift')]
class TestCollectionWithCustomMutators implements \IteratorAggregate, \Countable
{
    private array $elements = [];

    public function push(mixed $element): void
    {
        $this->elements[] = $element;
    }

    public function shift(mixed $element): bool
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
