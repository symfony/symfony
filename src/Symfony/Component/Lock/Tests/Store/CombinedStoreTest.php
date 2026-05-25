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

use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Lock\BlockingStoreInterface;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\SharedLockStoreInterface;
use Symfony\Component\Lock\Store\CombinedStore;
use Symfony\Component\Lock\Store\RedisStore;
use Symfony\Component\Lock\Strategy\StrategyInterface;
use Symfony\Component\Lock\Strategy\UnanimousStrategy;
use Symfony\Component\Lock\Test\AbstractStoreTestCase;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
#[Group('integration')]
class CombinedStoreTest extends AbstractStoreTestCase
{
    use ExpiringStoreTestTrait;
    use SharedLockStoreTestTrait;

    protected function getClockDelay(): int
    {
        return 250000;
    }

    public function getStore(): PersistingStoreInterface
    {
        $redis = new \Predis\Client(
            array_combine(['host', 'port'], explode(':', getenv('REDIS_HOST')) + [1 => 6379]),
            ['exceptions' => false],
        );

        try {
            $redis->connect();
        } catch (\Exception $e) {
            self::markTestSkipped($e->getMessage());
        }

        return new CombinedStore([new RedisStore($redis)], new UnanimousStrategy());
    }

    public function testSaveThrowsExceptionOnFailure()
    {
        $this->expectException(LockConflictedException::class);
        $key = new Key(__METHOD__);

        $store1 = $this->createMock(BlockingStoreInterface::class);
        $store1
            ->expects($this->once())
            ->method('save')
            ->with($key)
            ->willThrowException(new LockConflictedException());
        $store2 = $this->createMock(BlockingStoreInterface::class);
        $store2
            ->expects($this->once())
            ->method('save')
            ->with($key)
            ->willThrowException(new LockConflictedException());

        $strategy = $this->createStub(StrategyInterface::class);
        $strategy
            ->method('canBeMet')
            ->willReturn(true);
        $strategy
            ->method('isMet')
            ->willReturn(false);

        $this->createCombinedStore($store1, $store2, $strategy)->save($key);
    }

    public function testSaveCleanupOnFailure()
    {
        $key = new Key(__METHOD__);

        $store1 = $this->createMock(BlockingStoreInterface::class);
        $store1
            ->expects($this->once())
            ->method('save')
            ->with($key)
            ->willThrowException(new LockConflictedException());
        $store2 = $this->createMock(BlockingStoreInterface::class);
        $store2
            ->expects($this->once())
            ->method('save')
            ->with($key)
            ->willThrowException(new LockConflictedException());

        $store1
            ->expects($this->once())
            ->method('delete');
        $store2
            ->expects($this->once())
            ->method('delete');

        $strategy = $this->createStub(StrategyInterface::class);
        $strategy
            ->method('canBeMet')
            ->willReturn(true);
        $strategy
            ->method('isMet')
            ->willReturn(false);

        try {
            $this->createCombinedStore($store1, $store2, $strategy)->save($key);
        } catch (LockConflictedException $e) {
            // Catch the exception given this is not what we want to assert in this tests
        }
    }

    public function testSaveAbortWhenStrategyCantBeMet()
    {
        $key = new Key(__METHOD__);

        $store1 = $this->createMock(BlockingStoreInterface::class);
        $store1
            ->expects($this->once())
            ->method('save')
            ->with($key)
            ->willThrowException(new LockConflictedException());
        $store2 = $this->createMock(BlockingStoreInterface::class);
        $store2
            ->expects($this->never())
            ->method('save');

        $strategy = $this->createMock(StrategyInterface::class);
        $strategy
            ->expects($this->once())
            ->method('canBeMet')
            ->willReturn(false);
        $strategy
            ->method('isMet')
            ->willReturn(false);

        try {
            $this->createCombinedStore($store1, $store2, $strategy)->save($key);
        } catch (LockConflictedException $e) {
            // Catch the exception given this is not what we want to assert in this tests
        }
    }

    public function testputOffExpirationThrowsExceptionOnFailure()
    {
        $this->expectException(LockConflictedException::class);
        $key = new Key(__METHOD__);
        $ttl = random_int(1, 10);

        $store1 = $this->createMock(BlockingStoreInterface::class);
        $store1
            ->expects($this->once())
            ->method('putOffExpiration')
            ->with($key, $this->lessThanOrEqual($ttl))
            ->willThrowException(new LockConflictedException());
        $store2 = $this->createMock(BlockingStoreInterface::class);
        $store2
            ->expects($this->once())
            ->method('putOffExpiration')
            ->with($key, $this->lessThanOrEqual($ttl))
            ->willThrowException(new LockConflictedException());

        $strategy = $this->createStub(StrategyInterface::class);
        $strategy
            ->method('canBeMet')
            ->willReturn(true);
        $strategy
            ->method('isMet')
            ->willReturn(false);

        $this->createCombinedStore($store1, $store2, $strategy)->putOffExpiration($key, $ttl);
    }

    public function testputOffExpirationCleanupOnFailure()
    {
        $key = new Key(__METHOD__);
        $ttl = random_int(1, 10);

        $store1 = $this->createMock(BlockingStoreInterface::class);
        $store1
            ->expects($this->once())
            ->method('putOffExpiration')
            ->with($key, $this->lessThanOrEqual($ttl))
            ->willThrowException(new LockConflictedException());
        $store2 = $this->createMock(BlockingStoreInterface::class);
        $store2
            ->expects($this->once())
            ->method('putOffExpiration')
            ->with($key, $this->lessThanOrEqual($ttl))
            ->willThrowException(new LockConflictedException());

        $store1
            ->expects($this->once())
            ->method('delete');
        $store2
            ->expects($this->once())
            ->method('delete');

        $strategy = $this->createStub(StrategyInterface::class);
        $strategy
            ->method('canBeMet')
            ->willReturn(true);
        $strategy
            ->method('isMet')
            ->willReturn(false);

        try {
            $this->createCombinedStore($store1, $store2, $strategy)->putOffExpiration($key, $ttl);
        } catch (LockConflictedException $e) {
            // Catch the exception given this is not what we want to assert in this tests
        }
    }

    public function testputOffExpirationAbortWhenStrategyCantBeMet()
    {
        $key = new Key(__METHOD__);
        $ttl = random_int(1, 10);

        $store1 = $this->createMock(BlockingStoreInterface::class);
        $store1
            ->expects($this->once())
            ->method('putOffExpiration')
            ->with($key, $this->lessThanOrEqual($ttl))
            ->willThrowException(new LockConflictedException());
        $store2 = $this->createMock(BlockingStoreInterface::class);
        $store2
            ->expects($this->never())
            ->method('putOffExpiration');

        $strategy = $this->createMock(StrategyInterface::class);
        $strategy
            ->expects($this->once())
            ->method('canBeMet')
            ->willReturn(false);
        $strategy
            ->method('isMet')
            ->willReturn(false);

        try {
            $this->createCombinedStore($store1, $store2, $strategy)->putOffExpiration($key, $ttl);
        } catch (LockConflictedException $e) {
            // Catch the exception given this is not what we want to assert in this tests
        }
    }

    public function testPutOffExpirationIgnoreNonExpiringStorage()
    {
        $store1 = $this->createStub(PersistingStoreInterface::class);
        $store2 = $this->createStub(PersistingStoreInterface::class);
        $strategy = $this->createMock(StrategyInterface::class);

        $store = new CombinedStore([$store1, $store2], $strategy);

        $key = new Key(__METHOD__);
        $ttl = random_int(1, 10);

        $strategy
            ->method('canBeMet')
            ->willReturn(true);
        $strategy
            ->expects($this->once())
            ->method('isMet')
            ->with(2, 2)
            ->willReturn(true);

        $store->putOffExpiration($key, $ttl);
    }

    public function testExistsDontAskToEveryBody()
    {
        $key = new Key(__METHOD__);

        $store1 = $this->createMock(BlockingStoreInterface::class);
        $store1
            ->expects($this->once())
            ->method('exists')
            ->with($key)
            ->willReturn(false);
        $store2 = $this->createMock(BlockingStoreInterface::class);
        $store2
            ->expects($this->never())
            ->method('exists');

        $strategy = $this->createMock(StrategyInterface::class);
        $strategy
            ->method('canBeMet')
            ->willReturn(true);
        $strategy
            ->expects($this->once())
            ->method('isMet')
            ->willReturn(true);

        $this->assertTrue($this->createCombinedStore($store1, $store2, $strategy)->exists($key));
    }

    public function testExistsAbortWhenStrategyCantBeMet()
    {
        $key = new Key(__METHOD__);

        $store1 = $this->createMock(BlockingStoreInterface::class);
        $store1
            ->expects($this->once())
            ->method('exists')
            ->with($key)
            ->willReturn(false);
        $store2 = $this->createMock(BlockingStoreInterface::class);
        $store2
            ->expects($this->never())
            ->method('exists');

        $strategy = $this->createMock(StrategyInterface::class);
        $strategy
            ->expects($this->once())
            ->method('canBeMet')
            ->willReturn(false);
        $strategy
            ->expects($this->once())
            ->method('isMet')
            ->willReturn(false);

        $this->assertFalse($this->createCombinedStore($store1, $store2, $strategy)->exists($key));
    }

    public function testDeleteDontStopOnFailure()
    {
        $key = new Key(__METHOD__);

        $store1 = $this->createMock(BlockingStoreInterface::class);
        $store1
            ->expects($this->once())
            ->method('delete')
            ->with($key)
            ->willThrowException(new \Exception());
        $store2 = $this->createMock(BlockingStoreInterface::class);
        $store2
            ->expects($this->once())
            ->method('delete')
            ->with($key);

        $this->createCombinedStore($store1, $store2)->delete($key);
    }

    public function testExistsDontStopOnFailure()
    {
        $key = new Key(__METHOD__);

        $strategy = $this->createStub(StrategyInterface::class);
        $strategy
            ->method('canBeMet')
            ->willReturn(true);
        $strategy
            ->method('isMet')
            ->willReturn(false);
        $store1 = $this->createMock(BlockingStoreInterface::class);
        $store1
            ->expects($this->once())
            ->method('exists')
            ->willThrowException(new \Exception());
        $store2 = $this->createMock(BlockingStoreInterface::class);
        $store2
            ->expects($this->once())
            ->method('exists')
            ->with($key)
            ->willReturn(false);

        $this->assertFalse($this->createCombinedStore($store1, $store2, $strategy)->exists($key));
    }

    public function testSaveReadWithCompatibleStore()
    {
        $key = new Key(__METHOD__);

        $goodStore = $this->createMock(SharedLockStoreInterface::class);
        $goodStore->expects($this->once())
            ->method('saveRead')
            ->with($key);

        $store = new CombinedStore([$goodStore], new UnanimousStrategy());

        $store->saveRead($key);
    }

    private function createCombinedStore(?BlockingStoreInterface $store1 = null, ?BlockingStoreInterface $store2 = null, ?StrategyInterface $strategy = null): CombinedStore
    {
        return new CombinedStore([$store1 ?? $this->createStub(BlockingStoreInterface::class), $store2 ?? $this->createStub(BlockingStoreInterface::class)], $strategy ?? $this->createStub(StrategyInterface::class));
    }
}
