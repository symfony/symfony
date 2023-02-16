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

use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Lock\BlockingStoreInterface;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\SharedLockStoreInterface;
use Symfony\Component\Lock\Store\CombinedStore;
use Symfony\Component\Lock\Store\RedisStore;
use Symfony\Component\Lock\Strategy\StrategyInterface;
use Symfony\Component\Lock\Strategy\UnanimousStrategy;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 *
 * @group integration
 */
class CombinedStoreTest extends AbstractStoreTestCase
{
    use ExpiringStoreTestTrait;
    use SharedLockStoreTestTrait;

    protected function getClockDelay()
    {
        return 250000;
    }

    public function getStore(): PersistingStoreInterface
    {
        $redis = new \Predis\Client(array_combine(['host', 'port'], explode(':', getenv('REDIS_HOST')) + [1 => 6379]));

        try {
            $redis->connect();
        } catch (\Exception $e) {
            self::markTestSkipped($e->getMessage());
        }

        return new CombinedStore([new RedisStore($redis)], new UnanimousStrategy());
    }

    /** @var MockObject&StrategyInterface */
    private $strategy;
    /** @var MockObject&BlockingStoreInterface */
    private $store1;
    /** @var MockObject&BlockingStoreInterface */
    private $store2;
    /** @var CombinedStore */
    private $store;

    protected function setUp(): void
    {
        $this->strategy = $this->createMock(StrategyInterface::class);
        $this->store1 = $this->createMock(BlockingStoreInterface::class);
        $this->store2 = $this->createMock(BlockingStoreInterface::class);

        $this->store = new CombinedStore([$this->store1, $this->store2], $this->strategy);
    }

    public function testSaveThrowsExceptionOnFailure()
    {
        $this->expectException(LockConflictedException::class);
        $key = new Key(uniqid(__METHOD__, true));

        $this->store1
            ->expects($this->once())
            ->method('save')
            ->with($key)
            ->willThrowException(new LockConflictedException());
        $this->store2
            ->expects($this->once())
            ->method('save')
            ->with($key)
            ->willThrowException(new LockConflictedException());

        $this->strategy
            ->expects($this->any())
            ->method('canBeMet')
            ->willReturn(true);
        $this->strategy
            ->expects($this->any())
            ->method('isMet')
            ->willReturn(false);

        $this->store->save($key);
    }

    public function testSaveCleanupOnFailure()
    {
        $key = new Key(uniqid(__METHOD__, true));

        $this->store1
            ->expects($this->once())
            ->method('save')
            ->with($key)
            ->willThrowException(new LockConflictedException());
        $this->store2
            ->expects($this->once())
            ->method('save')
            ->with($key)
            ->willThrowException(new LockConflictedException());

        $this->store1
            ->expects($this->once())
            ->method('delete');
        $this->store2
            ->expects($this->once())
            ->method('delete');

        $this->strategy
            ->expects($this->any())
            ->method('canBeMet')
            ->willReturn(true);
        $this->strategy
            ->expects($this->any())
            ->method('isMet')
            ->willReturn(false);

        try {
            $this->store->save($key);
        } catch (LockConflictedException $e) {
            // Catch the exception given this is not what we want to assert in this tests
        }
    }

    public function testSaveAbortWhenStrategyCantBeMet()
    {
        $key = new Key(uniqid(__METHOD__, true));

        $this->store1
            ->expects($this->once())
            ->method('save')
            ->with($key)
            ->willThrowException(new LockConflictedException());
        $this->store2
            ->expects($this->never())
            ->method('save');

        $this->strategy
            ->expects($this->once())
            ->method('canBeMet')
            ->willReturn(false);
        $this->strategy
            ->expects($this->any())
            ->method('isMet')
            ->willReturn(false);

        try {
            $this->store->save($key);
        } catch (LockConflictedException $e) {
            // Catch the exception given this is not what we want to assert in this tests
        }
    }

    public function testputOffExpirationThrowsExceptionOnFailure()
    {
        $this->expectException(LockConflictedException::class);
        $key = new Key(uniqid(__METHOD__, true));
        $ttl = random_int(1, 10);

        $this->store1
            ->expects($this->once())
            ->method('putOffExpiration')
            ->with($key, $this->lessThanOrEqual($ttl))
            ->willThrowException(new LockConflictedException());
        $this->store2
            ->expects($this->once())
            ->method('putOffExpiration')
            ->with($key, $this->lessThanOrEqual($ttl))
            ->willThrowException(new LockConflictedException());

        $this->strategy
            ->expects($this->any())
            ->method('canBeMet')
            ->willReturn(true);
        $this->strategy
            ->expects($this->any())
            ->method('isMet')
            ->willReturn(false);

        $this->store->putOffExpiration($key, $ttl);
    }

    public function testputOffExpirationCleanupOnFailure()
    {
        $key = new Key(uniqid(__METHOD__, true));
        $ttl = random_int(1, 10);

        $this->store1
            ->expects($this->once())
            ->method('putOffExpiration')
            ->with($key, $this->lessThanOrEqual($ttl))
            ->willThrowException(new LockConflictedException());
        $this->store2
            ->expects($this->once())
            ->method('putOffExpiration')
            ->with($key, $this->lessThanOrEqual($ttl))
            ->willThrowException(new LockConflictedException());

        $this->store1
            ->expects($this->once())
            ->method('delete');
        $this->store2
            ->expects($this->once())
            ->method('delete');

        $this->strategy
            ->expects($this->any())
            ->method('canBeMet')
            ->willReturn(true);
        $this->strategy
            ->expects($this->any())
            ->method('isMet')
            ->willReturn(false);

        try {
            $this->store->putOffExpiration($key, $ttl);
        } catch (LockConflictedException $e) {
            // Catch the exception given this is not what we want to assert in this tests
        }
    }

    public function testputOffExpirationAbortWhenStrategyCantBeMet()
    {
        $key = new Key(uniqid(__METHOD__, true));
        $ttl = random_int(1, 10);

        $this->store1
            ->expects($this->once())
            ->method('putOffExpiration')
            ->with($key, $this->lessThanOrEqual($ttl))
            ->willThrowException(new LockConflictedException());
        $this->store2
            ->expects($this->never())
            ->method('putOffExpiration');

        $this->strategy
            ->expects($this->once())
            ->method('canBeMet')
            ->willReturn(false);
        $this->strategy
            ->expects($this->any())
            ->method('isMet')
            ->willReturn(false);

        try {
            $this->store->putOffExpiration($key, $ttl);
        } catch (LockConflictedException $e) {
            // Catch the exception given this is not what we want to assert in this tests
        }
    }

    public function testPutOffExpirationIgnoreNonExpiringStorage()
    {
        $store1 = $this->createMock(PersistingStoreInterface::class);
        $store2 = $this->createMock(PersistingStoreInterface::class);

        $store = new CombinedStore([$store1, $store2], $this->strategy);

        $key = new Key(uniqid(__METHOD__, true));
        $ttl = random_int(1, 10);

        $this->strategy
            ->expects($this->any())
            ->method('canBeMet')
            ->willReturn(true);
        $this->strategy
            ->expects($this->once())
            ->method('isMet')
            ->with(2, 2)
            ->willReturn(true);

        $store->putOffExpiration($key, $ttl);
    }

    public function testExistsDontAskToEveryBody()
    {
        $key = new Key(uniqid(__METHOD__, true));

        $this->store1
            ->expects($this->any())
            ->method('exists')
            ->with($key)
            ->willReturn(false);
        $this->store2
            ->expects($this->never())
            ->method('exists');

        $this->strategy
            ->expects($this->any())
            ->method('canBeMet')
            ->willReturn(true);
        $this->strategy
            ->expects($this->once())
            ->method('isMet')
            ->willReturn(true);

        $this->assertTrue($this->store->exists($key));
    }

    public function testExistsAbortWhenStrategyCantBeMet()
    {
        $key = new Key(uniqid(__METHOD__, true));

        $this->store1
            ->expects($this->any())
            ->method('exists')
            ->with($key)
            ->willReturn(false);
        $this->store2
            ->expects($this->never())
            ->method('exists');

        $this->strategy
            ->expects($this->once())
            ->method('canBeMet')
            ->willReturn(false);
        $this->strategy
            ->expects($this->once())
            ->method('isMet')
            ->willReturn(false);

        $this->assertFalse($this->store->exists($key));
    }

    public function testDeleteDontStopOnFailure()
    {
        $key = new Key(uniqid(__METHOD__, true));

        $this->store1
            ->expects($this->once())
            ->method('delete')
            ->with($key)
            ->willThrowException(new \Exception());
        $this->store2
            ->expects($this->once())
            ->method('delete')
            ->with($key);

        $this->store->delete($key);
    }

    public function testExistsDontStopOnFailure()
    {
        $key = new Key(uniqid(__METHOD__, true));

        $this->strategy
            ->expects($this->any())
            ->method('canBeMet')
            ->willReturn(true);
        $this->strategy
            ->expects($this->any())
            ->method('isMet')
            ->willReturn(false);
        $this->store1
            ->expects($this->once())
            ->method('exists')
            ->willThrowException(new \Exception());
        $this->store2
            ->expects($this->once())
            ->method('exists')
            ->with($key)
            ->willReturn(false);

        $this->assertFalse($this->store->exists($key));
    }

    public function testSaveReadWithCompatibleStore()
    {
        $key = new Key(uniqid(__METHOD__, true));

        $goodStore = $this->createMock(SharedLockStoreInterface::class);
        $goodStore->expects($this->once())
            ->method('saveRead')
            ->with($key);

        $store = new CombinedStore([$goodStore], new UnanimousStrategy());

        $store->saveRead($key);
    }
}
