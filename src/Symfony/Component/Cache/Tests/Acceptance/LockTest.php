<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Acceptance;

use Symfony\Component\Cache\Data\Collection;
use Symfony\Component\Cache\Cache;
use Symfony\Component\Cache\Data\FreshItem;
use Symfony\Component\Cache\Data\NullResult;
use Symfony\Component\Cache\Extension\Lock\LockFactory;

class LockTest extends \PHPUnit_Framework_TestCase
{
    /** @dataProvider Symfony\Component\Cache\Tests\Acceptance\DataProvider::provideCaches */
    public function testWhenAnItemIsLockedItCantBeSet(Cache $cache)
    {
        $collection = new Collection(array(
            new FreshItem('key1', 'value1'),
            new FreshItem('key2', 'value2'),
        ));

        $factory = new LockFactory(50, 1);
        $factory->create(array('key1'))->acquire($cache);
        $storedItem = $cache->set($collection);

        $this->assertTrue($storedItem instanceof Collection);
        $this->assertEquals(array('key2'), $storedItem->getKeys());
        $this->assertTrue($storedItem->get('key2')->isHit());

        $fetchedItem = $cache->get('key1');

        $this->assertTrue($fetchedItem instanceof NullResult);
    }
}
