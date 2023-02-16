<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Semaphore\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Semaphore\Exception\SemaphoreAcquiringException;
use Symfony\Component\Semaphore\Exception\SemaphoreExpiredException;
use Symfony\Component\Semaphore\Exception\SemaphoreReleasingException;
use Symfony\Component\Semaphore\Key;
use Symfony\Component\Semaphore\PersistingStoreInterface;
use Symfony\Component\Semaphore\Semaphore;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class SemaphoreTest extends TestCase
{
    public function testAcquireReturnsTrue()
    {
        $key = new Key('key', 1);
        $store = $this->createMock(PersistingStoreInterface::class);
        $semaphore = new Semaphore($key, $store);

        $store
            ->expects($this->once())
            ->method('save')
            ->with($key, 300.0)
        ;

        $this->assertTrue($semaphore->acquire());
        $this->assertGreaterThanOrEqual(299.0, $key->getRemainingLifetime());
    }

    public function testAcquireReturnsFalse()
    {
        $key = new Key('key', 1);
        $store = $this->createMock(PersistingStoreInterface::class);
        $semaphore = new Semaphore($key, $store);

        $store
            ->expects($this->once())
            ->method('save')
            ->with($key, 300.0)
            ->willThrowException(new SemaphoreAcquiringException($key, 'message'))
        ;

        $this->assertFalse($semaphore->acquire());
        $this->assertNull($key->getRemainingLifetime());
    }

    public function testAcquireThrowException()
    {
        $key = new Key('key', 1);
        $store = $this->createMock(PersistingStoreInterface::class);
        $semaphore = new Semaphore($key, $store);

        $store
            ->expects($this->once())
            ->method('save')
            ->with($key, 300.0)
            ->willThrowException(new \RuntimeException())
        ;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to acquire the "key" semaphore.');

        $semaphore->acquire();
    }

    public function testRefresh()
    {
        $key = new Key('key', 1);
        $store = $this->createMock(PersistingStoreInterface::class);
        $semaphore = new Semaphore($key, $store, 10.0);

        $store
            ->expects($this->once())
            ->method('putOffExpiration')
            ->with($key, 10.0)
        ;

        $semaphore->refresh();
        $this->assertGreaterThanOrEqual(9.0, $key->getRemainingLifetime());
    }

    public function testRefreshWithCustomTtl()
    {
        $key = new Key('key', 1);
        $store = $this->createMock(PersistingStoreInterface::class);
        $semaphore = new Semaphore($key, $store, 10.0);

        $store
            ->expects($this->once())
            ->method('putOffExpiration')
            ->with($key, 40.0)
        ;

        $semaphore->refresh(40.0);
        $this->assertGreaterThanOrEqual(39.0, $key->getRemainingLifetime());
    }

    public function testRefreshWhenItFails()
    {
        $key = new Key('key', 1);
        $store = $this->createMock(PersistingStoreInterface::class);
        $semaphore = new Semaphore($key, $store);

        $store
            ->expects($this->once())
            ->method('putOffExpiration')
            ->with($key, 300.0)
            ->willThrowException(new SemaphoreExpiredException($key, 'message'))
        ;

        $this->expectException(SemaphoreExpiredException::class);
        $this->expectExceptionMessage('The semaphore "key" has expired: message.');

        $semaphore->refresh();
    }

    public function testRefreshWhenItFailsHard()
    {
        $key = new Key('key', 1);
        $store = $this->createMock(PersistingStoreInterface::class);
        $semaphore = new Semaphore($key, $store);

        $store
            ->expects($this->once())
            ->method('putOffExpiration')
            ->with($key, 300.0)
            ->willThrowException(new \RuntimeException())
        ;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to define an expiration for the "key" semaphore.');

        $semaphore->refresh();
    }

    public function testRelease()
    {
        $key = new Key('key', 1);
        $store = $this->createMock(PersistingStoreInterface::class);
        $semaphore = new Semaphore($key, $store);

        $store
            ->expects($this->once())
            ->method('delete')
            ->with($key)
        ;

        $semaphore->release();
    }

    public function testReleaseWhenItFails()
    {
        $key = new Key('key', 1);
        $store = $this->createMock(PersistingStoreInterface::class);
        $semaphore = new Semaphore($key, $store);

        $store
            ->expects($this->once())
            ->method('delete')
            ->with($key)
            ->willThrowException(new SemaphoreReleasingException($key, 'message'))
        ;

        $this->expectException(SemaphoreReleasingException::class);
        $this->expectExceptionMessage('The semaphore "key" could not be released: message.');

        $semaphore->release();
    }

    public function testReleaseWhenItFailsHard()
    {
        $key = new Key('key', 1);
        $store = $this->createMock(PersistingStoreInterface::class);
        $semaphore = new Semaphore($key, $store);

        $store
            ->expects($this->once())
            ->method('delete')
            ->with($key)
            ->willThrowException(new \RuntimeException())
        ;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to release the "key" semaphore.');

        $semaphore->release();
    }

    public function testReleaseOnDestruction()
    {
        $key = new Key('key', 1);
        $store = $this->createMock(PersistingStoreInterface::class);
        $semaphore = new Semaphore($key, $store);

        $store
            ->method('exists')
            ->willReturn(true)
        ;
        $store
            ->expects($this->once())
            ->method('delete')
        ;

        $semaphore->acquire();
        unset($semaphore);
    }

    public function testNoAutoReleaseWhenNotConfigured()
    {
        $key = new Key('key', 1);
        $store = $this->createMock(PersistingStoreInterface::class);
        $semaphore = new Semaphore($key, $store, 10.0, false);

        $store
            ->method('exists')
            ->willReturn(true)
        ;
        $store
            ->expects($this->never())
            ->method('delete')
        ;

        $semaphore->acquire();
        unset($semaphore);
    }

    public function testExpiration()
    {
        $store = $this->createMock(PersistingStoreInterface::class);

        $key = new Key('key', 1);
        $semaphore = new Semaphore($key, $store);
        $this->assertFalse($semaphore->isExpired());

        $key = new Key('key', 1);
        $key->reduceLifetime(0.0);
        $semaphore = new Semaphore($key, $store);
        $this->assertTrue($semaphore->isExpired());
    }

    /**
     * @group time-sensitive
     */
    public function testExpirationResetAfter()
    {
        $store = $this->createMock(PersistingStoreInterface::class);

        $key = new Key('key', 1);
        $semaphore = new Semaphore($key, $store, 1);

        $semaphore->acquire();
        $this->assertFalse($semaphore->isExpired());
        $semaphore->release();

        sleep(2);

        $semaphore->acquire();
        $this->assertFalse($semaphore->isExpired());
    }
}
