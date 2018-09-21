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

use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\Store\StoreFactory;
use Symfony\Component\Lock\Store\ZookeeperStore;

/**
 * @author Ganesh Chandrasekaran <gchandrasekaran@wayfair.com>
 *
 * @requires extension zookeeper
 */
class ZookeeperStoreTest extends AbstractStoreTest
{
    public function getStore(): ZookeeperStore
    {
        $zookeeper_server = getenv('ZOOKEEPER_HOST').':2181';

        $zookeeper = new \Zookeeper(implode(',', array($zookeeper_server)));

        return StoreFactory::createStore($zookeeper);
    }

    public function testSaveSucceedsWhenPathContainsMoreThanOneNode()
    {
        $store = $this->getStore();
        $resource = '/baseNode/lockNode';
        $key = new Key($resource);

        $store->save($key);
        $this->assertTrue($store->exists($key));

        $store->delete($key);
        $this->assertFalse($store->exists($key));
    }

    public function testSaveSucceedsWhenPathContainsOneNode()
    {
        $store = $this->getStore();
        $resource = '/baseNode';
        $key = new Key($resource);

        $store->save($key);
        $this->assertTrue($store->exists($key));

        $store->delete($key);
        $this->assertFalse($store->exists($key));
    }

    public function testSaveSucceedsWhenPathsContainSameFirstNode()
    {
        $store = $this->getStore();
        $resource = 'foo/bar';
        $key = new Key($resource);

        $store->save($key);
        $this->assertTrue($store->exists($key));

        $resource2 = 'foo';
        $key2 = new Key($resource2);

        $this->assertFalse($store->exists($key2));
        $store->save($key2);
        $this->assertTrue($store->exists($key2));

        $store->delete($key2);
        $this->assertFalse($store->exists($key2));

        $store->delete($key);
        $this->assertFalse($store->exists($key));
    }

    public function testRootPathIsLockable()
    {
        $store = $this->getStore();
        $resource = '/';
        $key = new Key($resource);

        $store->save($key);
        $this->assertTrue($store->exists($key));

        $store->delete($key);
        $this->assertFalse($store->exists($key));
    }

    public function testEmptyStringIsLockable()
    {
        $store = $this->getStore();
        $resource = '';
        $key = new Key($resource);

        $store->save($key);
        $this->assertTrue($store->exists($key));

        $store->delete($key);
        $this->assertFalse($store->exists($key));
    }
}
