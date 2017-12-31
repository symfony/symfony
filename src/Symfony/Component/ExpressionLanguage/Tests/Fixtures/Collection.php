<?php

namespace Symfony\Component\ExpressionLanguage\Tests\Fixtures;

class Collection implements \IteratorAggregate
{
    public function getIterator()
    {
        return new \ArrayIterator(array('a', 'b'));
    }
}
