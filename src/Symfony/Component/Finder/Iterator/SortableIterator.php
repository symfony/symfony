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

/**
 * SortableIterator applies a sort on a given Iterator.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SortableIterator extends \ArrayIterator
{
    const SORT_BY_NAME = 1;
    const SORT_BY_TYPE = 2;

    /**
     * Constructor.
     *
     * @param \Iterator        $iterator The Iterator to filter
     * @param integer|\Closure $sort     The sort type (SORT_BY_NAME, SORT_BY_TYPE, or a \Closure instance)
     */
    public function __construct(\Iterator $iterator, $sort)
    {
        if (self::SORT_BY_NAME === $sort) {
            $sort = function ($a, $b)
            {
                return strcmp($a->getRealpath(), $b->getRealpath());
            };
        } elseif (self::SORT_BY_TYPE === $sort) {
            $sort = function ($a, $b)
            {
                if ($a->isDir() && $b->isFile()) {
                    return -1;
                } elseif ($a->isFile() && $b->isDir()) {
                    return 1;
                }

                return strcmp($a->getRealpath(), $b->getRealpath());
            };
        } elseif (!$sort instanceof \Closure) {
            throw new \InvalidArgumentException(sprintf('The SortableIterator takes a \Closure or a valid built-in sort algorithm as an argument (%s given).', $sort));
        }

        $array = new \ArrayObject(iterator_to_array($iterator));
        $array->uasort($sort);

        parent::__construct($array);
    }
}
