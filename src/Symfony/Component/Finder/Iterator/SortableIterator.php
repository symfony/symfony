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
class SortableIterator implements \IteratorAggregate
{
    const SORT_BY_NAME = 1;
    const SORT_BY_TYPE = 2;
    const SORT_BY_ACCESSED_TIME = 3;
    const SORT_BY_CHANGED_TIME = 4;
    const SORT_BY_MODIFIED_TIME = 5;

    private $iterator;
    private $sort;

    /**
     * Constructor.
     *
     * @param \Traversable $iterator The Iterator to filter
     * @param int|callback $sort     The sort type (SORT_BY_NAME, SORT_BY_TYPE, or a PHP callback)
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(\Traversable $iterator, $sort)
    {
        $this->iterator = $iterator;

        if (self::SORT_BY_NAME === $sort) {
            $this->sort = function ($a, $b) {

                //strcmp returns < 0 if str1 is less than str2; > 0 if str1 is greater than str2, and 0 if they are equal.
                $result = strcmp($a->getRealpath(), $b->getRealpath());

                if ($result == 0) {
                    return 0;
                }

                return $result < 0 ? -1 : 1;
            };
        } elseif (self::SORT_BY_TYPE === $sort) {
            $this->sort = function ($a, $b) {
                if ($a->isDir() && $b->isFile()) {
                    return -1;
                } elseif ($a->isFile() && $b->isDir()) {
                    return 1;
                }

                //strcmp returns < 0 if str1 is less than str2; > 0 if str1 is greater than str2, and 0 if they are equal.
                $result = strcmp($a->getRealpath(), $b->getRealpath()) < 0 ? -1 : 1;

                if ($result == 0) {
                    return 0;
                }

                return $result < 0 ? -1 : 1;
            };
        } elseif (self::SORT_BY_ACCESSED_TIME === $sort) {
            $this->sort = function ($a, $b) {
                if ($a->getATime() === $b->getATime()) {
                    return 0;
                };

                return ($a->getATime() > $b->getATime())  < 0 ? -1 : 1;
            };
        } elseif (self::SORT_BY_CHANGED_TIME === $sort) {
            $this->sort = function ($a, $b) {
                if (($a->getCTime() === $b->getCTime())) {
                    return 0;
                }

                return ($a->getCTime() > $b->getCTime()) < 0 ? -1 : 1;
            };
        } elseif (self::SORT_BY_MODIFIED_TIME === $sort) {
            $this->sort = function ($a, $b) {
                if ($a->getMTime() === $b->getMTime()) {
                    return 0;
                }

                return ($a->getMTime() > $b->getMTime())  < 0 ? -1 : 1;
            };
        } elseif (is_callable($sort)) {
            $this->sort = $sort;
        } else {
            throw new \InvalidArgumentException('The SortableIterator takes a PHP callback or a valid built-in sort algorithm as an argument.');
        }
    }

    public function getIterator()
    {
        $array = iterator_to_array($this->iterator, true);
        uasort($array, $this->sort);

        return new \ArrayIterator($array);
    }
}
