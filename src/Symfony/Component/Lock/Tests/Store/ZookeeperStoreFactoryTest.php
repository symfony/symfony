<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Tests\Store;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\Store\StoreFactory;
use Symfony\Component\Lock\Store\ZookeeperStore;

/**
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 *
 * @requires extension zookeeper
 */
class ZookeeperStoreFactoryTest extends TestCase
{
    public function testCreateZooKeeperStore()
    {
        $store = StoreFactory::createStore($this->createMock(\Zookeeper::class));

        $this->assertInstanceOf(ZookeeperStore::class, $store);
    }

    public function testCreateZooKeeperStoreAsDsn()
    {
        $store = StoreFactory::createStore('zookeeper://localhost:2181');

        $this->assertInstanceOf(ZookeeperStore::class, $store);
    }

    public function testCreateZooKeeperStoreWithMultipleHosts()
    {
        $store = StoreFactory::createStore('zookeeper://localhost01,localhost02:2181');

        $this->assertInstanceOf(ZookeeperStore::class, $store);
    }
}
