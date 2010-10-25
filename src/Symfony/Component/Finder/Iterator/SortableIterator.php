<?php

namespace Symfony\Component\Finder\Iterator;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * SortableIterator applies a sort on a given Iterator.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
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
        if (!$sort instanceof \Closure && self::SORT_BY_NAME == $sort) {
            $sort = function ($a, $b)
            {
                return strcmp($a->getRealpath(), $b->getRealpath());
            };
        } elseif (!$sort instanceof \Closure && self::SORT_BY_TYPE == $sort) {
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
