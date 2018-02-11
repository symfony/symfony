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
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\Lock;
use Symfony\Component\Lock\StoreInterface;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class LockTest extends TestCase
{
    public function testAcquireNoBlocking()
    {
        $key = new Key(uniqid(__METHOD__, true));
        $store = $this->getMockBuilder(StoreInterface::class)->getMock();
        $lock = new Lock($key, $store);

        $store
            ->expects($this->once())
            ->method('save');

        $this->assertTrue($lock->acquire(false));
    }

    public function testAcquireReturnsFalse()
    {
        $key = new Key(uniqid(__METHOD__, true));
        $store = $this->getMockBuilder(StoreInterface::class)->getMock();
        $lock = new Lock($key, $store);

        $store
            ->expects($this->once())
            ->method('save')
            ->willThrowException(new LockConflictedException());

        $this->assertFalse($lock->acquire(false));
    }

    public function testAcquireBlocking()
    {
        $key = new Key(uniqid(__METHOD__, true));
        $store = $this->getMockBuilder(StoreInterface::class)->getMock();
        $lock = new Lock($key, $store);

        $store
            ->expects($this->never())
            ->method('save');
        $store
            ->expects($this->once())
            ->method('waitAndSave');

        $this->assertTrue($lock->acquire(true));
    }

    public function testAcquireSetsTtl()
    {
        $key = new Key(uniqid(__METHOD__, true));
        $store = $this->getMockBuilder(StoreInterface::class)->getMock();
        $lock = new Lock($key, $store, 10);

        $store
            ->expects($this->once())
            ->method('save');
        $store
            ->expects($this->once())
            ->method('putOffExpiration')
            ->with($key, 10);

        $lock->acquire();
    }

    public function testRefresh()
    {
        $key = new Key(uniqid(__METHOD__, true));
        $store = $this->getMockBuilder(StoreInterface::class)->getMock();
        $lock = new Lock($key, $store, 10);

        $store
            ->expects($this->once())
            ->method('putOffExpiration')
            ->with($key, 10);

        $lock->refresh();
    }

    public function testIsAquired()
    {
        $key = new Key(uniqid(__METHOD__, true));
        $store = $this->getMockBuilder(StoreInterface::class)->getMock();
        $lock = new Lock($key, $store, 10);

        $store
            ->expects($this->any())
            ->method('exists')
            ->with($key)
            ->will($this->onConsecutiveCalls(true, false));

        $this->assertTrue($lock->isAcquired());
    }

    public function testRelease()
    {
        $key = new Key(uniqid(__METHOD__, true));
        $store = $this->getMockBuilder(StoreInterface::class)->getMock();
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
        $store = $this->getMockBuilder(StoreInterface::class)->getMock();
        $lock = new Lock($key, $store, 10);

        $store
            ->method('exists')
            ->willReturnOnConsecutiveCalls(array(true, false))
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
        $store = $this->getMockBuilder(StoreInterface::class)->getMock();
        $lock = new Lock($key, $store, 10, false);

        $store
            ->method('exists')
            ->willReturnOnConsecutiveCalls(array(true, false))
        ;
        $store
            ->expects($this->never())
            ->method('delete')
        ;

        $lock->acquire(false);
        unset($lock);
    }

    /**
     * @expectedException \Symfony\Component\Lock\Exception\LockReleasingException
     */
    public function testReleaseThrowsExceptionIfNotWellDeleted()
    {
        $key = new Key(uniqid(__METHOD__, true));
        $store = $this->getMockBuilder(StoreInterface::class)->getMock();
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

    /**
     * @expectedException \Symfony\Component\Lock\Exception\LockReleasingException
     */
    public function testReleaseThrowsAndLog()
    {
        $key = new Key(uniqid(__METHOD__, true));
        $store = $this->getMockBuilder(StoreInterface::class)->getMock();
        $logger = $this->getMockBuilder(LoggerInterface::class)->getMock();
        $lock = new Lock($key, $store, 10, true);
        $lock->setLogger($logger);

        $logger->expects($this->atLeastOnce())
            ->method('notice')
            ->with('Failed to release the "{resource}" lock.', array('resource' => $key));

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
        $store = $this->getMockBuilder(StoreInterface::class)->getMock();
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
        yield array(array(-0.1), true);
        yield array(array(0.1, -0.1), true);
        yield array(array(-0.1, 0.1), true);

        yield array(array(), false);
        yield array(array(0.1), false);
        yield array(array(-0.1, null), false);
    }
}
