<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

/**
 * @implements \IteratorAggregate<int, int>
 */
class IteratorAggregateWithPropertiesDummy implements \IteratorAggregate, \JsonSerializable
{
    public int $total = 2;

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator([1, 2]);
    }

    public function jsonSerialize(): array
    {
        return ['serialized' => true];
    }
}
