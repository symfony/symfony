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

use Symfony\Component\Lock\Exception\LockExpiredException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
trait ExpiringStoreTestTrait
{
    /**
     * Amount of microseconds used as a delay to test expiration. Should be
     * small enough not to slow the test suite too much, and high enough not to
     * fail because of race conditions.
     *
     * @return int
     */
    abstract protected function getClockDelay();

    /**
     * @see AbstractStoreTest::getStore()
     */
    abstract protected function getStore();

    /**
     * Tests the store automatically delete the key when it expire.
     *
     * This test is time-sensitive: the $clockDelay could be adjusted.
     */
    public function testExpiration()
    {
        $key = new Key(uniqid(__METHOD__, true));
        $clockDelay = $this->getClockDelay();

        /** @var PersistingStoreInterface $store */
        $store = $this->getStore();

        $store->save($key);
        $store->putOffExpiration($key, 2 * $clockDelay / 1000000);
        $this->assertTrue($store->exists($key));

        usleep(3 * $clockDelay);
        $this->assertFalse($store->exists($key));
    }

    /**
     * Tests the store thrown exception when TTL expires.
     */
    public function testAbortAfterExpiration()
    {
        $this->expectException('\Symfony\Component\Lock\Exception\LockExpiredException');
        $key = new Key(uniqid(__METHOD__, true));

        /** @var PersistingStoreInterface $store */
        $store = $this->getStore();

        $store->save($key);
        $store->putOffExpiration($key, 1 / 1000000);
    }

    /**
     * Tests the refresh can push the limits to the expiration.
     *
     * This test is time-sensitive: the $clockDelay could be adjusted.
     */
    public function testRefreshLock()
    {
        // Amount of microseconds we should wait without slowing things down too much
        $clockDelay = $this->getClockDelay();

        $key = new Key(uniqid(__METHOD__, true));

        /** @var PersistingStoreInterface $store */
        $store = $this->getStore();

        $store->save($key);
        $store->putOffExpiration($key, 2 * $clockDelay / 1000000);
        $this->assertTrue($store->exists($key));

        usleep(3 * $clockDelay);
        $this->assertFalse($store->exists($key));
    }

    public function testSetExpiration()
    {
        $key = new Key(uniqid(__METHOD__, true));

        /** @var PersistingStoreInterface $store */
        $store = $this->getStore();

        $store->save($key);
        $store->putOffExpiration($key, 1);
        $this->assertGreaterThanOrEqual(0, $key->getRemainingLifetime());
        $this->assertLessThanOrEqual(1, $key->getRemainingLifetime());
    }

    public function testExpiredLockCleaned()
    {
        $resource = uniqid(__METHOD__, true);

        $key1 = new Key($resource);
        $key2 = new Key($resource);

        /** @var PersistingStoreInterface $store */
        $store = $this->getStore();
        $key1->reduceLifetime(0);

        $this->assertTrue($key1->isExpired());
        try {
            $store->save($key1);
            $this->fail('The store shouldn\'t have save an expired key');
        } catch (LockExpiredException $e) {
        }

        $this->assertFalse($store->exists($key1));

        $store->save($key2);
        $this->assertTrue($store->exists($key2));
    }
}
