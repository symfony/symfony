<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

/**
 * @implements \IteratorAggregate<int, int>
 */
class IteratorAggregateDummy implements \IteratorAggregate
{
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator([1, 2]);
    }
}
