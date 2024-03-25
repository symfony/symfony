<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AccessToken\Tests\Unit\Manager;

use PHPUnit\Framework\TestCase;
use Symfony\Component\AccessToken\AccessToken;
use Symfony\Component\AccessToken\AccessTokenManagerInterface;
use Symfony\Component\AccessToken\Credentials\BasicAuthCredentials;
use Symfony\Component\AccessToken\Manager\LockAccessTokenManagerDecorator;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;

/**
 * @author Pierre Rineau <pierre.rineau@processus.org>
 */
class LockAccessTokenManagerDecoratorTest extends TestCase
{
    public function testGetAccessTokenNoConflict(): void
    {
        $accessToken = new AccessToken('foo');

        $accessManager = $this->createMock(AccessTokenManagerInterface::class);
        $accessManager->expects($this->never())->method('refreshAccessToken');
        $accessManager->expects($this->once())->method('getAccessToken')->willReturn($accessToken);

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquire')->willReturn(true);

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->once())->method('createLock')->willReturn($lock);

        $tested = new LockAccessTokenManagerDecorator($accessManager, $lockFactory);

        $fetchedAccessToken = $tested->getAccessToken(new BasicAuthCredentials('foo'));
        self::assertSame($accessToken, $fetchedAccessToken);
    }

    public function testGetAccessTokenRetriesWhenTimeout(): void
    {
        $accessToken = new AccessToken('foo');

        $accessManager = $this->createMock(AccessTokenManagerInterface::class);
        $accessManager->expects($this->never())->method('refreshAccessToken');
        $accessManager->expects($this->once())->method('getAccessToken')->willReturn($accessToken);

        $invocationCount = 0;
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->exactly(2))->method('acquire')
            ->willReturnCallback(function () use (&$invocationCount) {
                if (1 === ++$invocationCount) {
                    throw new LockAcquiringException();
                }
                return true;
            })
        ;

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->once())->method('createLock')->willReturn($lock);

        $tested = new LockAccessTokenManagerDecorator($accessManager, $lockFactory);

        $fetchedAccessToken = $tested->getAccessToken(new BasicAuthCredentials('foo'));
        self::assertSame($accessToken, $fetchedAccessToken);
    }

    public function testRefreshAccessTokenNoConflict(): void
    {
        $accessToken = new AccessToken('foo');

        $accessManager = $this->createMock(AccessTokenManagerInterface::class);
        $accessManager->expects($this->never())->method('getAccessToken');
        $accessManager->expects($this->once())->method('refreshAccessToken')->willReturn($accessToken);

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquire')->willReturn(true);

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->once())->method('createLock')->willReturn($lock);

        $tested = new LockAccessTokenManagerDecorator($accessManager, $lockFactory);

        $fetchedAccessToken = $tested->refreshAccessToken(new BasicAuthCredentials('foo'));
        self::assertSame($accessToken, $fetchedAccessToken);
    }

    public function testRefreshAccessTokenFallsBackWhenConflict(): void
    {
        $accessToken = new AccessToken('foo');

        $accessManager = $this->createMock(AccessTokenManagerInterface::class);
        $accessManager->expects($this->never())->method('refreshAccessToken');
        $accessManager->expects($this->once())->method('getAccessToken')->willReturn($accessToken);

        $lock1 = $this->createMock(SharedLockInterface::class);
        $lock1->expects($this->once())->method('acquire')->willThrowException(new LockAcquiringException());

        $lock2 = $this->createMock(SharedLockInterface::class);
        $lock2->expects($this->once())->method('acquire')->willReturn(true);

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->exactly(2))->method('createLock')->willReturn($lock1, $lock2);

        $tested = new LockAccessTokenManagerDecorator($accessManager, $lockFactory);

        $fetchedAccessToken = $tested->refreshAccessToken(new BasicAuthCredentials('foo'));
        self::assertSame($accessToken, $fetchedAccessToken);
    }

    public function testDeleteAccessTokenNoConflict(): void
    {
        $accessManager = $this->createMock(AccessTokenManagerInterface::class);
        $accessManager->expects($this->once())->method('deleteAccessToken');

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquire')->willReturn(true);

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->once())->method('createLock')->willReturn($lock);

        $tested = new LockAccessTokenManagerDecorator($accessManager, $lockFactory);

        $tested->deleteAccessToken(new BasicAuthCredentials('foo'));
    }

    public function testDeleteAccessTokenDoNothingWhenConflict(): void
    {
        $accessManager = $this->createMock(AccessTokenManagerInterface::class);
        $accessManager->expects($this->never())->method('deleteAccessToken');

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquire')->willThrowException(new LockAcquiringException());

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->once())->method('createLock')->willReturn($lock);

        $tested = new LockAccessTokenManagerDecorator($accessManager, $lockFactory);

        $tested->deleteAccessToken(new BasicAuthCredentials('foo'));
    }
}
