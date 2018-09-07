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
use Symfony\Component\Lock\StoreInterface;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
trait ExpiringStoreTestTrait
{
    /**
     * Amount a microsecond used to order async actions.
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
     * This test is time sensible: the $clockDelay could be adjust.
     */
    public function testExpiration()
    {
        $key = new Key(uniqid(__METHOD__, true));
        $clockDelay = $this->getClockDelay();

        /** @var StoreInterface $store */
        $store = $this->getStore();

        $store->save($key);
        $store->putOffExpiration($key, $clockDelay / 1000000);
        $this->assertTrue($store->exists($key));

        usleep(3 * $clockDelay);
        $this->assertFalse($store->exists($key));
    }

    /**
     * Tests the store thrown exception when TTL expires.
     *
     * @expectedException \Symfony\Component\Lock\Exception\LockExpiredException
     */
    public function testAbortAfterExpiration()
    {
        $key = new Key(uniqid(__METHOD__, true));

        /** @var StoreInterface $store */
        $store = $this->getStore();

        $store->save($key);
        $store->putOffExpiration($key, 1 / 1000000);
    }

    /**
     * Tests the refresh can push the limits to the expiration.
     *
     * This test is time sensible: the $clockDelay could be adjust.
     */
    public function testRefreshLock()
    {
        // Amount a microsecond used to order async actions
        $clockDelay = $this->getClockDelay();

        // Amount a microsecond used to order async actions
        $key = new Key(uniqid(__METHOD__, true));

        /** @var StoreInterface $store */
        $store = $this->getStore();

        $store->save($key);
        $store->putOffExpiration($key, $clockDelay / 1000000);
        $this->assertTrue($store->exists($key));

        usleep(2 * $clockDelay);
        $this->assertFalse($store->exists($key));
    }

    public function testSetExpiration()
    {
        $key = new Key(uniqid(__METHOD__, true));

        /** @var StoreInterface $store */
        $store = $this->getStore();

        $store->save($key);
        $store->putOffExpiration($key, 1);
        $this->assertGreaterThanOrEqual(0, $key->getRemainingLifetime());
        $this->assertLessThanOrEqual(1, $key->getRemainingLifetime());
    }
}
