<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder\Iterator;

use Symfony\Component\Finder\Comparator\File;

/**
 * SortableIterator applies a sort on a given Iterator.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SortableIterator implements \IteratorAggregate
{
    const SORT_BY_NAME          = 1;
    const SORT_BY_TYPE          = 2;
    const SORT_BY_ACCESSED_TIME = 3;
    const SORT_BY_CHANGED_TIME  = 4;
    const SORT_BY_MODIFIED_TIME = 5;

    private $iterator;
    private $sortComparator;

    /**
     * Constructor.
     *
     * @param \Traversable $iterator The Iterator to filter
     * @param int|callable $sort     The sort type (SORT_BY_NAME, SORT_BY_TYPE, or a PHP callback)
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(\Traversable $iterator, $sort)
    {
        $this->iterator = $iterator;
        $this->sortComparator = $this->getSortComparator($sort);
    }

    /**
     * Returns the iterator that sorts a list of file
     * with the chosen sorting algorithm.
     *
     * @return \ArrayIterator|\Traversable
     */
    public function getIterator()
    {
        $array = iterator_to_array($this->iterator, true);

        uasort($array, $this->sortComparator);

        return new \ArrayIterator($array);
    }

    /**
     * Returns the corresponding sort comparator algorithm.
     *
     * @param int|callback     $sort     The sort type (SORT_BY_NAME, SORT_BY_TYPE, or a PHP callback)
     *
     * @return callable
     *
     * @throws \InvalidArgumentException
     */
    private function getSortComparator($sort)
    {
        if (is_callable($sort)) {
            return $sort;
        }

        if (self::SORT_BY_NAME === $sort) {
            return new File\NameComparator();
        }

        if (self::SORT_BY_TYPE === $sort) {
            return new File\TypeComparator();
        }

        if (self::SORT_BY_ACCESSED_TIME === $sort) {
            return new File\AccessTimeComparator();
        }

        if (self::SORT_BY_CHANGED_TIME === $sort) {
            return new File\ChangedTimeComparator();
        }

        if (self::SORT_BY_MODIFIED_TIME === $sort) {
            return new File\ModifiedTimeComparator();
        }

        throw new \InvalidArgumentException('The SortableIterator takes a PHP callback or a valid built-in sort algorithm as an argument.');
    }
}
