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

use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Exception\LockReleasingException;
use Symfony\Component\Lock\Key as LockKey;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\PersistingStoreInterface as LockPersistingStoreInterface;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Semaphore\Exception\SemaphoreAcquiringException;
use Symfony\Component\Semaphore\Exception\SemaphoreExpiredException;
use Symfony\Component\Semaphore\Exception\SemaphoreReleasingException;
use Symfony\Component\Semaphore\Key;
use Symfony\Component\Semaphore\PersistingStoreInterface;
use Symfony\Component\Semaphore\Store\LockStore;

class LockStoreTest extends AbstractStoreTestCase
{
    public function getStore(): PersistingStoreInterface
    {
        $lock = new FlockStore();
        $factory = new LockFactory($lock);

        return new LockStore($factory);
    }

    public function testExistsReflectsBackendStateAfterSlotLost()
    {
        $innerStore = new InMemoryStore();
        $factory = new LockFactory($innerStore);
        $store = new LockStore($factory);

        $key = new Key(__METHOD__, 4, 2);
        $store->save($key, 10);
        $this->assertTrue($store->exists($key));

        $innerLocks = $key->getState(LockStore::class);
        $innerLocks[0]->release();

        $this->assertFalse($store->exists($key));
    }

    public function testSaveReacquiresSlotsLostOnTheBackend()
    {
        $store = new LockStore(new LockFactory(new InMemoryStore()));

        $key = new Key(__METHOD__, 2, 2);
        $store->save($key, 10);

        $key->getState(LockStore::class)[0]->release();

        $store->save($key, 10);

        $this->assertTrue($store->exists($key));
    }

    public function testSaveFailsWhenALostSlotWasTakenByAnotherKey()
    {
        $store = new LockStore(new LockFactory(new InMemoryStore()));

        $key1 = new Key(__METHOD__, 1);
        $key2 = new Key(__METHOD__, 1);

        $store->save($key1, 10);
        $key1->getState(LockStore::class)[0]->release();
        $store->save($key2, 10);

        try {
            $store->save($key1, 10);
            $this->fail('The store shouldn\'t save the key when its slot is held by another key');
        } catch (SemaphoreAcquiringException) {
        }

        $this->assertFalse($store->exists($key1));
        $this->assertTrue($store->exists($key2));
    }

    public function testSaveReleasesTheSlotsThatExpiredLocally()
    {
        $innerStore = new class implements LockPersistingStoreInterface {
            public array $stored = [];
            public array $keys = [];

            public function save(LockKey $key): void
            {
                if (isset($this->stored[(string) $key]) && $this->stored[(string) $key] !== $key) {
                    throw new LockConflictedException();
                }
                $this->stored[(string) $key] = $key;
                $this->keys[] = $key;
            }

            public function delete(LockKey $key): void
            {
                if (($this->stored[(string) $key] ?? null) === $key) {
                    unset($this->stored[(string) $key]);
                }
            }

            public function exists(LockKey $key): bool
            {
                return ($this->stored[(string) $key] ?? null) === $key;
            }

            public function putOffExpiration(LockKey $key, float $ttl): void
            {
            }
        };

        $store = new LockStore(new LockFactory($innerStore));

        $key = new Key(__METHOD__, 1);
        $store->save($key, 10);

        foreach ($innerStore->keys as $lockKey) {
            $lockKey->reduceLifetime(-1);
        }

        $store->save($key, 10);

        $this->assertTrue($store->exists($key));
        $this->assertCount(1, $innerStore->stored);
    }

    public function testSaveTwicePutsOffExpiration()
    {
        $innerStore = new class implements LockPersistingStoreInterface {
            public function save(LockKey $key): void
            {
            }

            public function delete(LockKey $key): void
            {
            }

            public function exists(LockKey $key): bool
            {
                return true;
            }

            public function putOffExpiration(LockKey $key, float $ttl): void
            {
                $key->reduceLifetime($ttl);
            }
        };

        $store = new LockStore(new LockFactory($innerStore));

        $key = new Key(__METHOD__, 1);
        $store->save($key, 10);
        $store->save($key, 100);

        $this->assertGreaterThan(90, $key->getState(LockStore::class)[0]->getRemainingLifetime());
    }

    public function testPutOffExpirationReleasesRemainingLocksOnRefreshError()
    {
        $innerStore = new class implements LockPersistingStoreInterface {
            public array $stored = [];
            public int $refreshCalls = 0;
            public bool $failOnce = false;

            public function save(LockKey $key): void
            {
                $this->stored[(string) $key] = true;
            }

            public function delete(LockKey $key): void
            {
                unset($this->stored[(string) $key]);
            }

            public function exists(LockKey $key): bool
            {
                return isset($this->stored[(string) $key]);
            }

            public function putOffExpiration(LockKey $key, float $ttl): void
            {
                ++$this->refreshCalls;
                if ($this->failOnce) {
                    $this->failOnce = false;
                    throw new LockConflictedException('forced failure');
                }
            }
        };

        $factory = new LockFactory($innerStore);
        $store = new LockStore($factory);

        $key = new Key(__METHOD__, 4, 2);
        $store->save($key, 10);
        $this->assertCount(2, $innerStore->stored);

        $innerStore->failOnce = true;

        try {
            $store->putOffExpiration($key, 10);
            $this->fail('Expected SemaphoreExpiredException');
        } catch (SemaphoreExpiredException) {
        }

        $this->assertSame([], $innerStore->stored);
        $this->assertFalse($key->hasState(LockStore::class));
    }

    public function testDeleteAggregatesLockReleasingExceptions()
    {
        $innerStore = new class implements LockPersistingStoreInterface {
            public array $stored = [];
            public int $deleteCalls = 0;

            public function save(LockKey $key): void
            {
                $this->stored[(string) $key] = true;
            }

            public function delete(LockKey $key): void
            {
                ++$this->deleteCalls;
                unset($this->stored[(string) $key]);
                throw new LockReleasingException('forced release failure');
            }

            public function exists(LockKey $key): bool
            {
                return isset($this->stored[(string) $key]);
            }

            public function putOffExpiration(LockKey $key, float $ttl): void
            {
            }
        };

        $factory = new LockFactory($innerStore);
        $store = new LockStore($factory);

        $key = new Key(__METHOD__, 4, 2);
        $store->save($key, 10);

        try {
            $store->delete($key);
            $this->fail('Expected SemaphoreReleasingException');
        } catch (SemaphoreReleasingException) {
        }

        $this->assertSame(2, $innerStore->deleteCalls, 'delete() must continue past the first failing release');
        $this->assertFalse($key->hasState(LockStore::class));
    }

    public function testSaveCapturesAcquireExceptionAndRollsBack()
    {
        $innerStore = new class implements LockPersistingStoreInterface {
            public array $stored = [];
            public int $saveCalls = 0;
            public int $failAtCall = 2;

            public function save(LockKey $key): void
            {
                ++$this->saveCalls;
                if ($this->saveCalls === $this->failAtCall) {
                    throw new \RuntimeException('forced save failure');
                }
                $this->stored[(string) $key] = true;
            }

            public function delete(LockKey $key): void
            {
                unset($this->stored[(string) $key]);
            }

            public function exists(LockKey $key): bool
            {
                return isset($this->stored[(string) $key]);
            }

            public function putOffExpiration(LockKey $key, float $ttl): void
            {
            }
        };

        $factory = new LockFactory($innerStore);
        $store = new LockStore($factory);

        $key = new Key(__METHOD__, 4, 3);

        try {
            $store->save($key, 10);
            $this->fail('Expected LockAcquiringException captured by createLocks()');
        } catch (LockAcquiringException $e) {
            $this->assertStringContainsString('Failed to acquire', $e->getMessage());
        }

        $this->assertSame([], $innerStore->stored, 'partial slots must be rolled back on failure');
        $this->assertFalse($key->hasState(LockStore::class));
    }

    public function testSaveRollsBackWhenNotEnoughSlotsAvailable()
    {
        $innerStore = new class implements LockPersistingStoreInterface {
            public array $stored = [];

            public function save(LockKey $key): void
            {
                throw new LockConflictedException('slot already held');
            }

            public function delete(LockKey $key): void
            {
                unset($this->stored[(string) $key]);
            }

            public function exists(LockKey $key): bool
            {
                return isset($this->stored[(string) $key]);
            }

            public function putOffExpiration(LockKey $key, float $ttl): void
            {
            }
        };

        $factory = new LockFactory($innerStore);
        $store = new LockStore($factory);

        $key = new Key(__METHOD__, 4, 2);

        try {
            $store->save($key, 10);
            $this->fail('Expected SemaphoreAcquiringException');
        } catch (SemaphoreAcquiringException) {
        }

        $this->assertSame([], $innerStore->stored);
        $this->assertFalse($key->hasState(LockStore::class));
    }
}
