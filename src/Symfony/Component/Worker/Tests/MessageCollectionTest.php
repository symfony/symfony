<?php

namespace Symfony\Component\Worker\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Worker\MessageCollection;

class MessageCollectionTest extends TestCase
{
    public function test()
    {
        $col = new MessageCollection('A');
        $col->add('B');

        $this->assertCount(2, $col);
        $this->assertEquals(array('A', 'B'), $col->all());

        $this->assertCount(0, $col);
        $this->assertEquals(array(), $col->all());

        $col->add('D');
        $col->add('E');

        $this->assertCount(2, $col);
        $this->assertSame(array('D', 'E'), iterator_to_array($col));
        $this->assertEquals('D', $col->pop());
        $this->assertEquals('E', $col->pop());
        $this->assertEquals(null, $col->pop());
        $this->assertEquals(array(), $col->all());
        $this->assertCount(0, $col);
    }
}
