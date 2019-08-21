<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\BlockingStoreInterface;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\Lock;
use Symfony\Component\Lock\PersistingStoreInterface;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class LockTest extends TestCase
{
    public function testAcquireNoBlocking()
    {
        $key = new Key(uniqid(__METHOD__, true));
        $store = $this->getMockBuilder(PersistingStoreInterface::class)->getMock();
        $lock = new Lock($key, $store);

        $store
            ->expects($this->once())
            ->method('save');
        $store
            ->method('exists')
            ->willReturnOnConsecutiveCalls(true, false);

        $this->assertTrue($lock->acquire(false));
    }

    public function testAcquireNoBlockingStoreInterface()
    {
        $key = new Key(uniqid(__METHOD__, true));
        $store = $this->getMockBuilder(PersistingStoreInterface::class)->getMock();
        $lock = new Lock($key, $store);

        $store
            ->expects($this->once())
            ->method('save');
        $store
            ->method('exists')
            ->willReturnOnConsecutiveCalls(true, false);

        $this->assertTrue($lock->acquire(false));
    }

    public function testAcquireReturnsFalse()
    {
        $key = new Key(uniqid(__METHOD__, true));
        $store = $this->getMockBuilder(PersistingStoreInterface::class)->getMock();
        $lock = new Lock($key, $store);

        $store
            ->expects($this->once())
            ->method('save')
            ->willThrowException(new LockConflictedException());
        $store
            ->method('exists')
            ->willReturnOnConsecutiveCalls(true, false);

        $this->assertFalse($lock->acquire(false));
    }

    public function testAcquireReturnsFalseStoreInterface()
    {
        $key = new Key(uniqid(__METHOD__, true));
        $store = $this->getMockBuilder(PersistingStoreInterface::class)->getMock();
        $lock = new Lock($key, $store);

        $store
            ->expects($this->once())
            ->method('save')
            ->willThrowException(new LockConflictedException());
        $store
            ->method('exists')
            ->willReturnOnConsecutiveCalls(true, false);

        $this->assertFalse($lock->acquire(false));
    }

    public function testAcquireBlocking()
    {
        $key = new Key(uniqid(__METHOD__, true));
        $store = $this->createMock(BlockingStoreInterface::class);
        $lock = new Lock($key, $store);

        $store
            ->expects($this->never())
            ->method('save');
        $store
            ->expects($this->once())
            ->method('waitAndSave');
        $store
            ->method('exists')
            ->willReturnOnConsecutiveCalls(true, false);

        $this->assertTrue($lock->acquire(true));
    }

    public function testAcquireSetsTtl()
    {
        $key = new Key(uniqid(__METHOD__, true));
        $store = $this->getMockBuilder(PersistingStoreInterface::class)->getMock();
        $lock = new Lock($key, $store, 10);

        $store
            ->expects($this->once())
            ->method('save');
        $store
            ->expects($this->once())
            ->method('putOffExpiration')
            ->with($key, 10);
        $store
            ->method('exists')
            ->willReturnOnConsecutiveCalls(true, false);

        $lock->acquire();
    }

    public function testRefresh()
    {
        $key = new Key(uniqid(__METHOD__, true));
        $store = $this->getMockBuilder(PersistingStoreInterface::class)->getMock();
        $lock = new Lock($key, $store, 10);

        $store
            ->expects($this->once())
            ->method('putOffExpiration')
            ->with($key, 10);
        $store
            ->method('exists')
            ->willReturnOnConsecutiveCalls(true, false);

        $lock->refresh();
    }

    public function testRefreshCustom()
    {
        $key = new Key(uniqid(__METHOD__, true));
        $store = $this->getMockBuilder(PersistingStoreInterface::class)->getMock();
        $lock = new Lock($key, $store, 10);

        $store
            ->expects($this->once())
            ->method('putOffExpiration')
            ->with($key, 20);
        $store
            ->method('exists')
            ->willReturnOnConsecutiveCalls(true, false);

        $lock->refresh(20);
    }

    public function testIsAquired()
    {
        $key = new Key(uniqid(__METHOD__, true));
        $store = $this->getMockBuilder(PersistingStoreInterface::class)->getMock();
        $lock = new Lock($key, $store, 10);

        $store
            ->method('exists')
            ->with($key)
            ->willReturnOnConsecutiveCalls(true, false);

        $this->assertTrue($lock->isAcquired());
    }

    public function testRelease()
    {
        $key = new Key(uniqid(__METHOD__, true));
        $store = $this->getMockBuilder(PersistingStoreInterface::class)->getMock();
        $lock = new Lock($key, $store, 10);

        $store
            ->expects($this->once())
            ->method('delete')
            ->with($key);

        $store
            ->expects($this->once())
            ->method('exists')
            ->with($key)
            ->willReturn(false);

        $lock->release();
    }

    public function testReleaseStoreInterface()
    {
        $key = new Key(uniqid(__METHOD__, true));
        $store = $this->getMockBuilder(PersistingStoreInterface::class)->getMock();
        $lock = new Lock($key, $store, 10);

        $store
            ->expects($this->once())
            ->method('delete')
            ->with($key);

        $store
            ->expects($this->once())
            ->method('exists')
            ->with($key)
            ->willReturn(false);

        $lock->release();
    }

    public function testReleaseOnDestruction()
    {
        $key = new Key(uniqid(__METHOD__, true));
        $store = $this->createMock(BlockingStoreInterface::class);
        $lock = new Lock($key, $store, 10);

        $store
            ->method('exists')
            ->willReturnOnConsecutiveCalls(true, false)
        ;
        $store
            ->expects($this->once())
            ->method('delete')
        ;

        $lock->acquire(false);
        unset($lock);
    }

    public function testNoAutoReleaseWhenNotConfigured()
    {
        $key = new Key(uniqid(__METHOD__, true));
        $store = $this->createMock(BlockingStoreInterface::class);
        $lock = new Lock($key, $store, 10, false);

        $store
            ->method('exists')
            ->willReturnOnConsecutiveCalls(true, false)
        ;
        $store
            ->expects($this->never())
            ->method('delete')
        ;

        $lock->acquire(false);
        unset($lock);
    }

    public function testReleaseThrowsExceptionWhenDeletionFail()
    {
        $this->expectException('Symfony\Component\Lock\Exception\LockReleasingException');
        $key = new Key(uniqid(__METHOD__, true));
        $store = $this->getMockBuilder(PersistingStoreInterface::class)->getMock();
        $lock = new Lock($key, $store, 10);

        $store
            ->expects($this->once())
            ->method('delete')
            ->with($key)
            ->willThrowException(new \RuntimeException('Boom'));

        $store
            ->expects($this->never())
            ->method('exists')
            ->with($key);

        $lock->release();
    }

    public function testReleaseThrowsExceptionIfNotWellDeleted()
    {
        $this->expectException('Symfony\Component\Lock\Exception\LockReleasingException');
        $key = new Key(uniqid(__METHOD__, true));
        $store = $this->getMockBuilder(PersistingStoreInterface::class)->getMock();
        $lock = new Lock($key, $store, 10);

        $store
            ->expects($this->once())
            ->method('delete')
            ->with($key);

        $store
            ->expects($this->once())
            ->method('exists')
            ->with($key)
            ->willReturn(true);

        $lock->release();
    }

    public function testReleaseThrowsAndLog()
    {
        $this->expectException('Symfony\Component\Lock\Exception\LockReleasingException');
        $key = new Key(uniqid(__METHOD__, true));
        $store = $this->getMockBuilder(PersistingStoreInterface::class)->getMock();
        $logger = $this->getMockBuilder(LoggerInterface::class)->getMock();
        $lock = new Lock($key, $store, 10, true);
        $lock->setLogger($logger);

        $logger->expects($this->atLeastOnce())
            ->method('notice')
            ->with('Failed to release the "{resource}" lock.', ['resource' => $key]);

        $store
            ->expects($this->once())
            ->method('delete')
            ->with($key);

        $store
            ->expects($this->once())
            ->method('exists')
            ->with($key)
            ->willReturn(true);

        $lock->release();
    }

    /**
     * @dataProvider provideExpiredDates
     */
    public function testExpiration($ttls, $expected)
    {
        $key = new Key(uniqid(__METHOD__, true));
        $store = $this->getMockBuilder(PersistingStoreInterface::class)->getMock();
        $lock = new Lock($key, $store, 10);

        foreach ($ttls as $ttl) {
            if (null === $ttl) {
                $key->resetLifetime();
            } else {
                $key->reduceLifetime($ttl);
            }
        }
        $this->assertSame($expected, $lock->isExpired());
    }

    /**
     * @dataProvider provideExpiredDates
     */
    public function testExpirationStoreInterface($ttls, $expected)
    {
        $key = new Key(uniqid(__METHOD__, true));
        $store = $this->getMockBuilder(PersistingStoreInterface::class)->getMock();
        $lock = new Lock($key, $store, 10);

        foreach ($ttls as $ttl) {
            if (null === $ttl) {
                $key->resetLifetime();
            } else {
                $key->reduceLifetime($ttl);
            }
        }
        $this->assertSame($expected, $lock->isExpired());
    }

    public function provideExpiredDates()
    {
        yield [[-0.1], true];
        yield [[0.1, -0.1], true];
        yield [[-0.1, 0.1], true];

        yield [[], false];
        yield [[0.1], false];
        yield [[-0.1, null], false];
    }
}
