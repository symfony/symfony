<?php

namespace Symfony\Tests\Component\Serializer\Fixtures;

class TraversableDummy implements \IteratorAggregate
{
    public $foo = 'foo';
    public $bar = 'bar';

    public function getIterator()
    {
        return new \ArrayIterator(get_object_vars($this));
    }
}
