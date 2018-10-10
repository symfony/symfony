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
 * Reverse the order of a previous iterator.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class ReverseSortingIterator implements \IteratorAggregate
{
    private $iterator;

    public function __construct(\Traversable $iterator)
    {
        $this->iterator = $iterator;
    }

    public function getIterator()
    {
        return new \ArrayIterator(array_reverse(iterator_to_array($this->iterator, true)));
    }
}
