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
            ->expects($this->once())
            ->method('exists')
            ->with($key)
            ->willReturn(true);

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
}
