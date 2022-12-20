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
use Symfony\Component\Lock\PersistingStoreInterface;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
abstract class AbstractStoreTest extends TestCase
{
    abstract protected function getStore(): PersistingStoreInterface;

    public function testSave()
    {
        $store = $this->getStore();

        $key = new Key(uniqid(__METHOD__, true));

        self::assertFalse($store->exists($key));
        $store->save($key);
        self::assertTrue($store->exists($key));
        $store->delete($key);
        self::assertFalse($store->exists($key));
    }

    public function testSaveWithDifferentResources()
    {
        $store = $this->getStore();

        $key1 = new Key(uniqid(__METHOD__, true));
        $key2 = new Key(uniqid(__METHOD__, true));

        $store->save($key1);
        self::assertTrue($store->exists($key1));
        self::assertFalse($store->exists($key2));

        $store->save($key2);
        self::assertTrue($store->exists($key1));
        self::assertTrue($store->exists($key2));

        $store->delete($key1);
        self::assertFalse($store->exists($key1));
        self::assertTrue($store->exists($key2));

        $store->delete($key2);
        self::assertFalse($store->exists($key1));
        self::assertFalse($store->exists($key2));
    }

    public function testSaveWithDifferentKeysOnSameResources()
    {
        $store = $this->getStore();

        $resource = uniqid(__METHOD__, true);
        $key1 = new Key($resource);
        $key2 = new Key($resource);

        $store->save($key1);
        self::assertTrue($store->exists($key1));
        self::assertFalse($store->exists($key2));

        try {
            $store->save($key2);
            self::fail('The store shouldn\'t save the second key');
        } catch (LockConflictedException $e) {
        }

        // The failure of previous attempt should not impact the state of current locks
        self::assertTrue($store->exists($key1));
        self::assertFalse($store->exists($key2));

        $store->delete($key1);
        self::assertFalse($store->exists($key1));
        self::assertFalse($store->exists($key2));

        $store->save($key2);
        self::assertFalse($store->exists($key1));
        self::assertTrue($store->exists($key2));

        $store->delete($key2);
        self::assertFalse($store->exists($key1));
        self::assertFalse($store->exists($key2));
    }

    public function testSaveTwice()
    {
        $store = $this->getStore();

        $resource = uniqid(__METHOD__, true);
        $key = new Key($resource);

        $store->save($key);
        $store->save($key);
        // just asserts it don't throw an exception
        self::addToAssertionCount(1);

        $store->delete($key);
    }

    public function testDeleteIsolated()
    {
        $store = $this->getStore();

        $key1 = new Key(uniqid(__METHOD__, true));
        $key2 = new Key(uniqid(__METHOD__, true));

        $store->save($key1);
        self::assertTrue($store->exists($key1));
        self::assertFalse($store->exists($key2));

        $store->delete($key2);
        self::assertTrue($store->exists($key1));
        self::assertFalse($store->exists($key2));
    }
}
