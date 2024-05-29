<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Util;

/**
 * A hash map which keeps track of deletions and additions.
 *
 * Like in associative arrays, elements can be mapped to integer or string keys.
 * Unlike associative arrays, the map keeps track of the order in which keys
 * were added and removed. This order is reflected during iteration.
 *
 * The map supports concurrent modification during iteration. That means that
 * you can insert and remove elements from within a foreach loop and the
 * iterator will reflect those changes accordingly.
 *
 * While elements that are added during the loop are recognized by the iterator,
 * changed elements are not. Otherwise the loop could be infinite if each loop
 * changes the current element:
 *
 *     $map = new OrderedHashMap();
 *     $map[1] = 1;
 *     $map[2] = 2;
 *     $map[3] = 3;
 *
 *     foreach ($map as $index => $value) {
 *         echo "$index: $value\n"
 *         if (1 === $index) {
 *             $map[1] = 4;
 *             $map[] = 5;
 *         }
 *     }
 *
 *     print_r(iterator_to_array($map));
 *
 *     // => 1: 1
 *     //    2: 2
 *     //    3: 3
 *     //    4: 5
 *     //    Array
 *     //    (
 *     //        [1] => 4
 *     //        [2] => 2
 *     //        [3] => 3
 *     //        [4] => 5
 *     //    )
 *
 * The map also supports multiple parallel iterators. That means that you can
 * nest foreach loops without affecting each other's iteration:
 *
 *     foreach ($map as $index => $value) {
 *         foreach ($map as $index2 => $value2) {
 *             // ...
 *         }
 *     }
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @template TValue
 *
 * @implements \ArrayAccess<string, TValue>
 * @implements \IteratorAggregate<string, TValue>
 */
class OrderedHashMap implements \ArrayAccess, \IteratorAggregate, \Countable
{
    /**
     * The keys of the map in the order in which they were inserted or changed.
     *
     * @var list<string>
     */
    private array $orderedKeys = [];

    /**
     * References to the cursors of all open iterators.
     *
     * @var array<int, int>
     */
    private array $managedCursors = [];

    /**
     * Creates a new map.
     *
     * @param TValue[] $elements The initial elements of the map, indexed by their keys
     */
    public function __construct(
        private array $elements = [],
    ) {
        // the explicit string type-cast is necessary as digit-only keys would be returned as integers otherwise
        $this->orderedKeys = array_map(strval(...), array_keys($elements));
    }

    public function offsetExists(mixed $key): bool
    {
        return isset($this->elements[$key]);
    }

    public function offsetGet(mixed $key): mixed
    {
        if (!isset($this->elements[$key])) {
            throw new \OutOfBoundsException(sprintf('The offset "%s" does not exist.', $key));
        }

        return $this->elements[$key];
    }

    public function offsetSet(mixed $key, mixed $value): void
    {
        if (null === $key || !isset($this->elements[$key])) {
            if (null === $key) {
                $key = [] === $this->orderedKeys
                    // If the array is empty, use 0 as key
                    ? 0
                    // Imitate PHP behavior of generating a key that equals
                    // the highest existing integer key + 1
                    : 1 + (int) max($this->orderedKeys);
            }

            $this->orderedKeys[] = (string) $key;
        }

        $this->elements[$key] = $value;
    }

    public function offsetUnset(mixed $key): void
    {
        if (false !== ($position = array_search((string) $key, $this->orderedKeys))) {
            array_splice($this->orderedKeys, $position, 1);
            unset($this->elements[$key]);

            foreach ($this->managedCursors as $i => $cursor) {
                if ($cursor >= $position) {
                    --$this->managedCursors[$i];
                }
            }
        }
    }

    public function getIterator(): \Traversable
    {
        return new OrderedHashMapIterator($this->elements, $this->orderedKeys, $this->managedCursors);
    }

    public function count(): int
    {
        return \count($this->elements);
    }
}
