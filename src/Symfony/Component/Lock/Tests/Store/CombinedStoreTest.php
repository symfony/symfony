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
 * @group integration
 */
class CombinedStoreTest extends AbstractStoreTest
{
    use ExpiringStoreTestTrait;
    use SharedLockStoreTestTrait;

    /**
     * {@inheritdoc}
     */
    protected function getClockDelay()
    {
        return 250000;
    }

    /**
     * {@inheritdoc}
     */
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
        $this->strategy = self::createMock(StrategyInterface::class);
        $this->store1 = self::createMock(BlockingStoreInterface::class);
        $this->store2 = self::createMock(BlockingStoreInterface::class);

        $this->store = new CombinedStore([$this->store1, $this->store2], $this->strategy);
    }

    public function testSaveThrowsExceptionOnFailure()
    {
        self::expectException(LockConflictedException::class);
        $key = new Key(uniqid(__METHOD__, true));

        $this->store1
            ->expects(self::once())
            ->method('save')
            ->with($key)
            ->willThrowException(new LockConflictedException());
        $this->store2
            ->expects(self::once())
            ->method('save')
            ->with($key)
            ->willThrowException(new LockConflictedException());

        $this->strategy
            ->expects(self::any())
            ->method('canBeMet')
            ->willReturn(true);
        $this->strategy
            ->expects(self::any())
            ->method('isMet')
            ->willReturn(false);

        $this->store->save($key);
    }

    public function testSaveCleanupOnFailure()
    {
        $key = new Key(uniqid(__METHOD__, true));

        $this->store1
            ->expects(self::once())
            ->method('save')
            ->with($key)
            ->willThrowException(new LockConflictedException());
        $this->store2
            ->expects(self::once())
            ->method('save')
            ->with($key)
            ->willThrowException(new LockConflictedException());

        $this->store1
            ->expects(self::once())
            ->method('delete');
        $this->store2
            ->expects(self::once())
            ->method('delete');

        $this->strategy
            ->expects(self::any())
            ->method('canBeMet')
            ->willReturn(true);
        $this->strategy
            ->expects(self::any())
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
            ->expects(self::once())
            ->method('save')
            ->with($key)
            ->willThrowException(new LockConflictedException());
        $this->store2
            ->expects(self::never())
            ->method('save');

        $this->strategy
            ->expects(self::once())
            ->method('canBeMet')
            ->willReturn(false);
        $this->strategy
            ->expects(self::any())
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
        self::expectException(LockConflictedException::class);
        $key = new Key(uniqid(__METHOD__, true));
        $ttl = random_int(1, 10);

        $this->store1
            ->expects(self::once())
            ->method('putOffExpiration')
            ->with($key, self::lessThanOrEqual($ttl))
            ->willThrowException(new LockConflictedException());
        $this->store2
            ->expects(self::once())
            ->method('putOffExpiration')
            ->with($key, self::lessThanOrEqual($ttl))
            ->willThrowException(new LockConflictedException());

        $this->strategy
            ->expects(self::any())
            ->method('canBeMet')
            ->willReturn(true);
        $this->strategy
            ->expects(self::any())
            ->method('isMet')
            ->willReturn(false);

        $this->store->putOffExpiration($key, $ttl);
    }

    public function testputOffExpirationCleanupOnFailure()
    {
        $key = new Key(uniqid(__METHOD__, true));
        $ttl = random_int(1, 10);

        $this->store1
            ->expects(self::once())
            ->method('putOffExpiration')
            ->with($key, self::lessThanOrEqual($ttl))
            ->willThrowException(new LockConflictedException());
        $this->store2
            ->expects(self::once())
            ->method('putOffExpiration')
            ->with($key, self::lessThanOrEqual($ttl))
            ->willThrowException(new LockConflictedException());

        $this->store1
            ->expects(self::once())
            ->method('delete');
        $this->store2
            ->expects(self::once())
            ->method('delete');

        $this->strategy
            ->expects(self::any())
            ->method('canBeMet')
            ->willReturn(true);
        $this->strategy
            ->expects(self::any())
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
            ->expects(self::once())
            ->method('putOffExpiration')
            ->with($key, self::lessThanOrEqual($ttl))
            ->willThrowException(new LockConflictedException());
        $this->store2
            ->expects(self::never())
            ->method('putOffExpiration');

        $this->strategy
            ->expects(self::once())
            ->method('canBeMet')
            ->willReturn(false);
        $this->strategy
            ->expects(self::any())
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
        $store1 = self::createMock(PersistingStoreInterface::class);
        $store2 = self::createMock(PersistingStoreInterface::class);

        $store = new CombinedStore([$store1, $store2], $this->strategy);

        $key = new Key(uniqid(__METHOD__, true));
        $ttl = random_int(1, 10);

        $this->strategy
            ->expects(self::any())
            ->method('canBeMet')
            ->willReturn(true);
        $this->strategy
            ->expects(self::once())
            ->method('isMet')
            ->with(2, 2)
            ->willReturn(true);

        $store->putOffExpiration($key, $ttl);
    }

    public function testExistsDontAskToEveryBody()
    {
        $key = new Key(uniqid(__METHOD__, true));

        $this->store1
            ->expects(self::any())
            ->method('exists')
            ->with($key)
            ->willReturn(false);
        $this->store2
            ->expects(self::never())
            ->method('exists');

        $this->strategy
            ->expects(self::any())
            ->method('canBeMet')
            ->willReturn(true);
        $this->strategy
            ->expects(self::once())
            ->method('isMet')
            ->willReturn(true);

        self::assertTrue($this->store->exists($key));
    }

    public function testExistsAbortWhenStrategyCantBeMet()
    {
        $key = new Key(uniqid(__METHOD__, true));

        $this->store1
            ->expects(self::any())
            ->method('exists')
            ->with($key)
            ->willReturn(false);
        $this->store2
            ->expects(self::never())
            ->method('exists');

        $this->strategy
            ->expects(self::once())
            ->method('canBeMet')
            ->willReturn(false);
        $this->strategy
            ->expects(self::once())
            ->method('isMet')
            ->willReturn(false);

        self::assertFalse($this->store->exists($key));
    }

    public function testDeleteDontStopOnFailure()
    {
        $key = new Key(uniqid(__METHOD__, true));

        $this->store1
            ->expects(self::once())
            ->method('delete')
            ->with($key)
            ->willThrowException(new \Exception());
        $this->store2
            ->expects(self::once())
            ->method('delete')
            ->with($key);

        $this->store->delete($key);
    }

    public function testExistsDontStopOnFailure()
    {
        $key = new Key(uniqid(__METHOD__, true));

        $this->strategy
            ->expects(self::any())
            ->method('canBeMet')
            ->willReturn(true);
        $this->strategy
            ->expects(self::any())
            ->method('isMet')
            ->willReturn(false);
        $this->store1
            ->expects(self::once())
            ->method('exists')
            ->willThrowException(new \Exception());
        $this->store2
            ->expects(self::once())
            ->method('exists')
            ->with($key)
            ->willReturn(false);

        self::assertFalse($this->store->exists($key));
    }

    public function testSaveReadWithCompatibleStore()
    {
        $key = new Key(uniqid(__METHOD__, true));

        $goodStore = self::createMock(SharedLockStoreInterface::class);
        $goodStore->expects(self::once())
            ->method('saveRead')
            ->with($key);

        $store = new CombinedStore([$goodStore], new UnanimousStrategy());

        $store->saveRead($key);
    }
}
