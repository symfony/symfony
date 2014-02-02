<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Util;

use Symfony\Component\Form\Util\OrderedHashMap;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class OrderedHashMapTest extends \PHPUnit_Framework_TestCase
{
    public function testGet()
    {
        $map = new OrderedHashMap();
        $map['first'] = 1;

        $this->assertSame(1, $map['first']);
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testGetNonExistingFails()
    {
        $map = new OrderedHashMap();

        $map['first'];
    }

    public function testInsertStringKeys()
    {
        $map = new OrderedHashMap();
        $map['first'] = 1;
        $map['second'] = 2;

        $this->assertSame(array('first' => 1, 'second' => 2), iterator_to_array($map));
    }

    public function testInsertNullKeys()
    {
        $map = new OrderedHashMap();
        $map[] = 1;
        $map['foo'] = 2;
        $map[] = 3;

        $this->assertSame(array(0 => 1, 'foo' => 2, 1 => 3), iterator_to_array($map));
    }

    /**
     * Updates should not change the position of an element, otherwise we could
     * turn foreach loops into endless loops if they change the current
     * element:
     *
     *     foreach ($map as $index => $value) {
     *         $map[$index] = $value + 1;
     *     }
     *
     * And we don't want this, right? :)
     */
    public function testUpdateDoesNotChangeElementPosition()
    {
        $map = new OrderedHashMap();
        $map['first'] = 1;
        $map['second'] = 2;
        $map['first'] = 1;

        $this->assertSame(array('first' => 1, 'second' => 2), iterator_to_array($map));
    }

    public function testIsset()
    {
        $map = new OrderedHashMap();
        $map['first'] = 1;

        $this->assertTrue(isset($map['first']));
    }

    public function testIssetReturnsFalseForNonExisting()
    {
        $map = new OrderedHashMap();

        $this->assertFalse(isset($map['first']));
    }

    public function testIssetReturnsFalseForNull()
    {
        $map = new OrderedHashMap();
        $map['first'] = null;

        $this->assertFalse(isset($map['first']));
    }

    public function testUnset()
    {
        $map = new OrderedHashMap();
        $map['first'] = 1;
        $map['second'] = 2;

        unset($map['first']);

        $this->assertSame(array('second' => 2), iterator_to_array($map));
    }

    public function testUnsetNonExistingSucceeds()
    {
        $map = new OrderedHashMap();

        unset($map['first']);
    }

    public function testEmptyIteration()
    {
        $map = new OrderedHashMap();
        $it = $map->getIterator();

        $it->rewind();
        $this->assertFalse($it->valid());
        $this->assertNull($it->key());
        $this->assertNull($it->current());
    }

    public function testIterationSupportsInsertion()
    {
        $map = new OrderedHashMap(array('first' => 1));
        $it = $map->getIterator();

        $it->rewind();
        $this->assertTrue($it->valid());
        $this->assertSame('first', $it->key());
        $this->assertSame(1, $it->current());

        // dynamic modification
        $map['added'] = 2;

        // iterator is unchanged
        $this->assertTrue($it->valid());
        $this->assertSame('first', $it->key());
        $this->assertSame(1, $it->current());

        // continue iteration
        $it->next();
        $this->assertTrue($it->valid());
        $this->assertSame('added', $it->key());
        $this->assertSame(2, $it->current());

        // end of map
        $it->next();
        $this->assertFalse($it->valid());
        $this->assertNull($it->key());
        $this->assertNull($it->current());
    }

    public function testIterationSupportsDeletionAndInsertion()
    {
        $map = new OrderedHashMap(array('first' => 1, 'removed' => 2));
        $it = $map->getIterator();

        $it->rewind();
        $this->assertTrue($it->valid());
        $this->assertSame('first', $it->key());
        $this->assertSame(1, $it->current());

        // dynamic modification
        unset($map['removed']);
        $map['added'] = 3;

        // iterator is unchanged
        $this->assertTrue($it->valid());
        $this->assertSame('first', $it->key());
        $this->assertSame(1, $it->current());

        // continue iteration
        $it->next();
        $this->assertTrue($it->valid());
        $this->assertSame('added', $it->key());
        $this->assertSame(3, $it->current());

        // end of map
        $it->next();
        $this->assertFalse($it->valid());
        $this->assertNull($it->key());
        $this->assertNull($it->current());
    }

    public function testIterationSupportsDeletionOfCurrentElement()
    {
        $map = new OrderedHashMap(array('removed' => 1, 'next' => 2));
        $it = $map->getIterator();

        $it->rewind();
        $this->assertTrue($it->valid());
        $this->assertSame('removed', $it->key());
        $this->assertSame(1, $it->current());

        unset($map['removed']);

        // iterator is unchanged
        $this->assertTrue($it->valid());
        $this->assertSame('removed', $it->key());
        $this->assertSame(1, $it->current());

        // continue iteration
        $it->next();
        $this->assertTrue($it->valid());
        $this->assertSame('next', $it->key());
        $this->assertSame(2, $it->current());

        // end of map
        $it->next();
        $this->assertFalse($it->valid());
        $this->assertNull($it->key());
        $this->assertNull($it->current());
    }

    public function testIterationIgnoresReplacementOfCurrentElement()
    {
        $map = new OrderedHashMap(array('replaced' => 1, 'next' => 2));
        $it = $map->getIterator();

        $it->rewind();
        $this->assertTrue($it->valid());
        $this->assertSame('replaced', $it->key());
        $this->assertSame(1, $it->current());

        $map['replaced'] = 3;

        // iterator is unchanged
        $this->assertTrue($it->valid());
        $this->assertSame('replaced', $it->key());
        $this->assertSame(1, $it->current());

        // continue iteration
        $it->next();
        $this->assertTrue($it->valid());
        $this->assertSame('next', $it->key());
        $this->assertSame(2, $it->current());

        // end of map
        $it->next();
        $this->assertFalse($it->valid());
        $this->assertNull($it->key());
        $this->assertNull($it->current());
    }

    public function testIterationSupportsDeletionOfCurrentAndLastElement()
    {
        $map = new OrderedHashMap(array('removed' => 1));
        $it = $map->getIterator();

        $it->rewind();
        $this->assertTrue($it->valid());
        $this->assertSame('removed', $it->key());
        $this->assertSame(1, $it->current());

        unset($map['removed']);

        // iterator is unchanged
        $this->assertTrue($it->valid());
        $this->assertSame('removed', $it->key());
        $this->assertSame(1, $it->current());

        // end of map
        $it->next();
        $this->assertFalse($it->valid());
        $this->assertNull($it->key());
        $this->assertNull($it->current());
    }

    public function testIterationIgnoresReplacementOfCurrentAndLastElement()
    {
        $map = new OrderedHashMap(array('replaced' => 1));
        $it = $map->getIterator();

        $it->rewind();
        $this->assertTrue($it->valid());
        $this->assertSame('replaced', $it->key());
        $this->assertSame(1, $it->current());

        $map['replaced'] = 2;

        // iterator is unchanged
        $this->assertTrue($it->valid());
        $this->assertSame('replaced', $it->key());
        $this->assertSame(1, $it->current());

        // end of map
        $it->next();
        $this->assertFalse($it->valid());
        $this->assertNull($it->key());
        $this->assertNull($it->current());
    }

    public function testIterationSupportsDeletionOfPreviousElement()
    {
        $map = new OrderedHashMap(array('removed' => 1, 'next' => 2, 'onemore' => 3));
        $it = $map->getIterator();

        $it->rewind();
        $this->assertTrue($it->valid());
        $this->assertSame('removed', $it->key());
        $this->assertSame(1, $it->current());

        // continue iteration
        $it->next();
        $this->assertTrue($it->valid());
        $this->assertSame('next', $it->key());
        $this->assertSame(2, $it->current());

        unset($map['removed']);

        // iterator is unchanged
        $this->assertTrue($it->valid());
        $this->assertSame('next', $it->key());
        $this->assertSame(2, $it->current());

        // continue iteration
        $it->next();
        $this->assertTrue($it->valid());
        $this->assertSame('onemore', $it->key());
        $this->assertSame(3, $it->current());

        // end of map
        $it->next();
        $this->assertFalse($it->valid());
        $this->assertNull($it->key());
        $this->assertNull($it->current());
    }

    public function testIterationIgnoresReplacementOfPreviousElement()
    {
        $map = new OrderedHashMap(array('replaced' => 1, 'next' => 2, 'onemore' => 3));
        $it = $map->getIterator();

        $it->rewind();
        $this->assertTrue($it->valid());
        $this->assertSame('replaced', $it->key());
        $this->assertSame(1, $it->current());

        // continue iteration
        $it->next();
        $this->assertTrue($it->valid());
        $this->assertSame('next', $it->key());
        $this->assertSame(2, $it->current());

        $map['replaced'] = 4;

        // iterator is unchanged
        $this->assertTrue($it->valid());
        $this->assertSame('next', $it->key());
        $this->assertSame(2, $it->current());

        // continue iteration
        $it->next();
        $this->assertTrue($it->valid());
        $this->assertSame('onemore', $it->key());
        $this->assertSame(3, $it->current());

        // end of map
        $it->next();
        $this->assertFalse($it->valid());
        $this->assertNull($it->key());
        $this->assertNull($it->current());
    }

    public function testIterationSupportsDeletionOfMultiplePreviousElements()
    {
        $map = new OrderedHashMap(array('removed' => 1, 'alsoremoved' => 2, 'next' => 3, 'onemore' => 4));
        $it = $map->getIterator();

        $it->rewind();
        $this->assertTrue($it->valid());
        $this->assertSame('removed', $it->key());
        $this->assertSame(1, $it->current());

        // continue iteration
        $it->next();
        $this->assertTrue($it->valid());
        $this->assertSame('alsoremoved', $it->key());
        $this->assertSame(2, $it->current());

        // continue iteration
        $it->next();
        $this->assertTrue($it->valid());
        $this->assertSame('next', $it->key());
        $this->assertSame(3, $it->current());

        unset($map['removed'], $map['alsoremoved']);

        // iterator is unchanged
        $this->assertTrue($it->valid());
        $this->assertSame('next', $it->key());
        $this->assertSame(3, $it->current());

        // continue iteration
        $it->next();
        $this->assertTrue($it->valid());
        $this->assertSame('onemore', $it->key());
        $this->assertSame(4, $it->current());

        // end of map
        $it->next();
        $this->assertFalse($it->valid());
        $this->assertNull($it->key());
        $this->assertNull($it->current());
    }

    public function testParallelIteration()
    {
        $map = new OrderedHashMap(array('first' => 1, 'second' => 2));
        $it1 = $map->getIterator();
        $it2 = $map->getIterator();

        $it1->rewind();
        $this->assertTrue($it1->valid());
        $this->assertSame('first', $it1->key());
        $this->assertSame(1, $it1->current());

        $it2->rewind();
        $this->assertTrue($it2->valid());
        $this->assertSame('first', $it2->key());
        $this->assertSame(1, $it2->current());

        // 1: continue iteration
        $it1->next();
        $this->assertTrue($it1->valid());
        $this->assertSame('second', $it1->key());
        $this->assertSame(2, $it1->current());

        // 2: remains unchanged
        $this->assertTrue($it2->valid());
        $this->assertSame('first', $it2->key());
        $this->assertSame(1, $it2->current());

        // 1: advance to end of map
        $it1->next();
        $this->assertFalse($it1->valid());
        $this->assertNull($it1->key());
        $this->assertNull($it1->current());

        // 2: remains unchanged
        $this->assertTrue($it2->valid());
        $this->assertSame('first', $it2->key());
        $this->assertSame(1, $it2->current());

        // 2: continue iteration
        $it2->next();
        $this->assertTrue($it2->valid());
        $this->assertSame('second', $it2->key());
        $this->assertSame(2, $it2->current());

        // 1: remains unchanged
        $this->assertFalse($it1->valid());
        $this->assertNull($it1->key());
        $this->assertNull($it1->current());

        // 2: advance to end of map
        $it2->next();
        $this->assertFalse($it2->valid());
        $this->assertNull($it2->key());
        $this->assertNull($it2->current());

        // 1: remains unchanged
        $this->assertFalse($it1->valid());
        $this->assertNull($it1->key());
        $this->assertNull($it1->current());
    }

    public function testCount()
    {
        $map = new OrderedHashMap();
        $map[] = 1;
        $map['foo'] = 2;
        unset($map[0]);
        $map[] = 3;

        $this->assertSame(2, count($map));
    }
}
