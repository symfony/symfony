<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Semaphore\Tests\Store;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Semaphore\Exception\SemaphoreAcquiringException;
use Symfony\Component\Semaphore\Exception\SemaphoreExpiredException;
use Symfony\Component\Semaphore\Key;
use Symfony\Component\Semaphore\PersistingStoreInterface;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
abstract class AbstractStoreTestCase extends TestCase
{
    abstract protected function getStore(): PersistingStoreInterface;

    public function testSaveExistAndDelete()
    {
        $store = $this->getStore();

        $key = new Key(__METHOD__, 1);

        $this->assertFalse($store->exists($key));
        $store->save($key, 10);
        $this->assertTrue($store->exists($key));
        $store->delete($key);
        $this->assertFalse($store->exists($key));
    }

    public function testSaveWithDifferentResources()
    {
        $store = $this->getStore();

        $key1 = new Key(__METHOD__.'1', 1);
        $key2 = new Key(__METHOD__.'2', 1);

        $store->save($key1, 10);
        $this->assertTrue($store->exists($key1));
        $this->assertFalse($store->exists($key2));

        $store->save($key2, 10);
        $this->assertTrue($store->exists($key1));
        $this->assertTrue($store->exists($key2));

        $store->delete($key1);
        $this->assertFalse($store->exists($key1));
        $this->assertTrue($store->exists($key2));

        $store->delete($key2);
        $this->assertFalse($store->exists($key1));
        $this->assertFalse($store->exists($key2));
    }

    public function testSaveWithDifferentKeysOnSameResource()
    {
        $store = $this->getStore();

        $resource = __METHOD__;
        $key1 = new Key($resource, 1);
        $key2 = new Key($resource, 1);

        $store->save($key1, 10);
        $this->assertTrue($store->exists($key1));
        $this->assertFalse($store->exists($key2));

        try {
            $store->save($key2, 10);
            $this->fail('The store shouldn\'t save the second key');
        } catch (SemaphoreAcquiringException $e) {
        }

        // The failure of previous attempt should not impact the state of current semaphores
        $this->assertTrue($store->exists($key1));
        $this->assertFalse($store->exists($key2));

        $store->delete($key1);
        $this->assertFalse($store->exists($key1));
        $this->assertFalse($store->exists($key2));

        $store->save($key2, 10);
        $this->assertFalse($store->exists($key1));
        $this->assertTrue($store->exists($key2));

        $store->delete($key2);
        $this->assertFalse($store->exists($key1));
        $this->assertFalse($store->exists($key2));
    }

    public function testSaveWithLimitAt2()
    {
        $store = $this->getStore();

        $resource = __METHOD__;
        $key1 = new Key($resource, 2);
        $key2 = new Key($resource, 2);
        $key3 = new Key($resource, 2);

        $store->save($key1, 10);
        $this->assertTrue($store->exists($key1));
        $this->assertFalse($store->exists($key2));
        $this->assertFalse($store->exists($key3));

        $store->save($key2, 10);
        $this->assertTrue($store->exists($key1));
        $this->assertTrue($store->exists($key2));
        $this->assertFalse($store->exists($key3));

        try {
            $store->save($key3, 10);
            $this->fail('The store shouldn\'t save the third key');
        } catch (SemaphoreAcquiringException $e) {
        }

        // The failure of previous attempt should not impact the state of current semaphores
        $this->assertTrue($store->exists($key1));
        $this->assertTrue($store->exists($key2));
        $this->assertFalse($store->exists($key3));

        $store->delete($key1);
        $this->assertFalse($store->exists($key1));
        $this->assertTrue($store->exists($key2));
        $this->assertFalse($store->exists($key3));

        $store->save($key3, 10);
        $this->assertFalse($store->exists($key1));
        $this->assertTrue($store->exists($key2));
        $this->assertTrue($store->exists($key3));

        $store->delete($key2);
        $store->delete($key3);
    }

    public function testSaveWithWeightAndLimitAt3()
    {
        $store = $this->getStore();

        $resource = __METHOD__;
        $key1 = new Key($resource, 4, 2);
        $key2 = new Key($resource, 4, 2);
        $key3 = new Key($resource, 4, 2);

        $store->save($key1, 10);
        $this->assertTrue($store->exists($key1));
        $this->assertFalse($store->exists($key2));
        $this->assertFalse($store->exists($key3));

        $store->save($key2, 10);
        $this->assertTrue($store->exists($key1));
        $this->assertTrue($store->exists($key2));
        $this->assertFalse($store->exists($key3));

        try {
            $store->save($key3, 10);
            $this->fail('The store shouldn\'t save the third key');
        } catch (SemaphoreAcquiringException $e) {
        }

        // The failure of previous attempt should not impact the state of current semaphores
        $this->assertTrue($store->exists($key1));
        $this->assertTrue($store->exists($key2));
        $this->assertFalse($store->exists($key3));

        $store->delete($key1);
        $this->assertFalse($store->exists($key1));
        $this->assertTrue($store->exists($key2));
        $this->assertFalse($store->exists($key3));

        $store->save($key3, 10);
        $this->assertFalse($store->exists($key1));
        $this->assertTrue($store->exists($key2));
        $this->assertTrue($store->exists($key3));

        $store->delete($key2);
        $store->delete($key3);
    }

    public function testPutOffExpiration()
    {
        $store = $this->getStore();
        $key = new Key(__METHOD__, 4, 2);
        $store->save($key, 20);

        $store->putOffExpiration($key, 20);

        // just asserts it doesn't throw an exception
        $this->addToAssertionCount(1);
    }

    public function testPutOffExpirationWhenSaveHasNotBeenCalled()
    {
        // This test simulate the key has expired since it does not exist
        $store = $this->getStore();
        $key1 = new Key(__METHOD__, 4, 2);

        $this->expectException(SemaphoreExpiredException::class);
        $this->expectExceptionMessage('The semaphore "Symfony\Component\Semaphore\Tests\Store\AbstractStoreTestCase::testPutOffExpirationWhenSaveHasNotBeenCalled" has expired: the script returns a positive number.');

        $store->putOffExpiration($key1, 20);
    }

    public function testSaveTwice()
    {
        $store = $this->getStore();

        $key = new Key(__METHOD__, 1);

        $store->save($key, 10);
        $store->save($key, 10);

        // just asserts it doesn't throw an exception
        $this->addToAssertionCount(1);

        $store->delete($key);
    }
}
