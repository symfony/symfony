<?php

namespace Symfony\Component\Finder\Tests\Iterator;

use Symfony\Component\Finder\Iterator\FinderIterator;

class FinderIteratorTest extends \PHPUnit_Framework_TestCase
{
    private $iterator;

    public function setup()
    {
        $this->iterator = new FinderIterator();
        $this->iterator->append(new \ArrayIterator(range('a', 'z')));
    }

    public function testFirst()
    {
        $this->assertEquals('a', $this->iterator->first());
    }

    public function testReturnNullAsFirstWhenEmpty()
    {
        $iterator = new FinderIterator();

        $this->assertNull($iterator->first());
    }

    public function testLast()
    {
        $this->assertEquals('z', $this->iterator->last());
    }

    public function testReturnNullAsLastWhenEmpty()
    {
        $iterator = new FinderIterator();

        $this->assertNull($iterator->last());
    }

    public function testReturnLastWhenUsingHashTable()
    {
        $iterator = new FinderIterator();
        $iterator->append(new \ArrayIterator(array('foo' => 'bar')));

        $this->assertEquals('bar', $iterator->last());
    }
}
