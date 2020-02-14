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
use Symfony\Component\Semaphore\Key;
use Symfony\Component\Semaphore\PersistingStoreInterface;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
abstract class AbstractStoreTest extends TestCase
{
    abstract protected function getStore(): PersistingStoreInterface;

    public function testSaveExistAndDelete()
    {
        $store = $this->getStore();

        $key = new Key('key', 1);

        $this->assertFalse($store->exists($key));
        $store->save($key, 10);
        $this->assertTrue($store->exists($key));
        $store->delete($key);
        $this->assertFalse($store->exists($key));
    }

    public function testSaveWithDifferentResources()
    {
        $store = $this->getStore();

        $key1 = new Key('key1', 1);
        $key2 = new Key('key2', 1);

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

        $resource = 'resource';
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

        $resource = 'resource';
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

        $resource = 'resource';
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

    public function testSaveTwice()
    {
        $store = $this->getStore();

        $resource = 'resource';
        $key = new Key($resource, 1);

        $store->save($key, 10);
        $store->save($key, 10);

        // just asserts it don't throw an exception
        $this->addToAssertionCount(1);

        $store->delete($key);
    }
}
