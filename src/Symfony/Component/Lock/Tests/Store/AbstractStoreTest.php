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
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\StoreInterface;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
abstract class AbstractStoreTest extends TestCase
{
    /**
     * @return StoreInterface
     */
    abstract protected function getStore();

    public function testSave()
    {
        $store = $this->getStore();

        $key = new Key(uniqid(__METHOD__, true));

        $this->assertFalse($store->exists($key));
        $store->save($key);
        $this->assertTrue($store->exists($key));
        $store->delete($key);
        $this->assertFalse($store->exists($key));
    }

    public function testSaveWithDifferentResources()
    {
        $store = $this->getStore();

        $key1 = new Key(uniqid(__METHOD__, true));
        $key2 = new Key(uniqid(__METHOD__, true));

        $store->save($key1);
        $this->assertTrue($store->exists($key1));
        $this->assertFalse($store->exists($key2));

        $store->save($key2);
        $this->assertTrue($store->exists($key1));
        $this->assertTrue($store->exists($key2));

        $store->delete($key1);
        $this->assertFalse($store->exists($key1));
        $this->assertTrue($store->exists($key2));

        $store->delete($key2);
        $this->assertFalse($store->exists($key1));
        $this->assertFalse($store->exists($key2));
    }

    public function testSaveWithDifferentKeysOnSameResources()
    {
        $store = $this->getStore();

        $resource = uniqid(__METHOD__, true);
        $key1 = new Key($resource);
        $key2 = new Key($resource);

        $store->save($key1);
        $this->assertTrue($store->exists($key1));
        $this->assertFalse($store->exists($key2));

        try {
            $store->save($key2);
            $this->fail('The store shouldn\'t save the second key');
        } catch (LockConflictedException $e) {
        }

        // The failure of previous attempt should not impact the state of current locks
        $this->assertTrue($store->exists($key1));
        $this->assertFalse($store->exists($key2));

        $store->delete($key1);
        $this->assertFalse($store->exists($key1));
        $this->assertFalse($store->exists($key2));

        $store->save($key2);
        $this->assertFalse($store->exists($key1));
        $this->assertTrue($store->exists($key2));

        $store->delete($key2);
        $this->assertFalse($store->exists($key1));
        $this->assertFalse($store->exists($key2));
    }

    public function testSaveTwice()
    {
        $store = $this->getStore();

        $resource = uniqid(__METHOD__, true);
        $key = new Key($resource);

        $store->save($key);
        $store->save($key);
        // just asserts it don't throw an exception
        $this->addToAssertionCount(1);

        $store->delete($key);
    }
}
