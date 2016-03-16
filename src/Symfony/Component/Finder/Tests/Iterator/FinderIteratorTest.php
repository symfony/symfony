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

    public function testLast()
    {
        $this->assertEquals('z', $this->iterator->last());
    }
}
