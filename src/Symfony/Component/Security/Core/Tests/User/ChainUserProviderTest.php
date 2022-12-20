<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\User;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\ChainUserProvider;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\InMemoryUserProvider;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class ChainUserProviderTest extends TestCase
{
    public function testLoadUserByUsername()
    {
        $provider1 = self::createMock(InMemoryUserProvider::class);
        $provider1
            ->expects(self::once())
            ->method('loadUserByIdentifier')
            ->with(self::equalTo('foo'))
            ->willThrowException(new UserNotFoundException('not found'))
        ;

        $provider2 = self::createMock(InMemoryUserProvider::class);
        $provider2
            ->expects(self::once())
            ->method('loadUserByIdentifier')
            ->with(self::equalTo('foo'))
            ->willReturn($account = self::createMock(UserInterface::class))
        ;

        $provider = new ChainUserProvider([$provider1, $provider2]);
        self::assertSame($account, $provider->loadUserByIdentifier('foo'));
    }

    public function testLoadUserByUsernameThrowsUserNotFoundException()
    {
        self::expectException(UserNotFoundException::class);
        $provider1 = self::createMock(InMemoryUserProvider::class);
        $provider1
            ->expects(self::once())
            ->method('loadUserByIdentifier')
            ->with(self::equalTo('foo'))
            ->willThrowException(new UserNotFoundException('not found'))
        ;

        $provider2 = self::createMock(InMemoryUserProvider::class);
        $provider2
            ->expects(self::once())
            ->method('loadUserByIdentifier')
            ->with(self::equalTo('foo'))
            ->willThrowException(new UserNotFoundException('not found'))
        ;

        $provider = new ChainUserProvider([$provider1, $provider2]);
        $provider->loadUserByIdentifier('foo');
    }

    public function testRefreshUser()
    {
        $provider1 = self::createMock(InMemoryUserProvider::class);
        $provider1
            ->expects(self::once())
            ->method('supportsClass')
            ->willReturn(false)
        ;

        $provider2 = self::createMock(InMemoryUserProvider::class);
        $provider2
            ->expects(self::once())
            ->method('supportsClass')
            ->willReturn(true)
        ;

        $provider2
            ->expects(self::once())
            ->method('refreshUser')
            ->willThrowException(new UnsupportedUserException('unsupported'))
        ;

        $provider3 = self::createMock(InMemoryUserProvider::class);
        $provider3
            ->expects(self::once())
            ->method('supportsClass')
            ->willReturn(true)
        ;

        $provider3
            ->expects(self::once())
            ->method('refreshUser')
            ->willReturn($account = self::createMock(UserInterface::class))
        ;

        $provider = new ChainUserProvider([$provider1, $provider2, $provider3]);
        self::assertSame($account, $provider->refreshUser(self::createMock(UserInterface::class)));
    }

    public function testRefreshUserAgain()
    {
        $provider1 = self::createMock(InMemoryUserProvider::class);
        $provider1
            ->expects(self::once())
            ->method('supportsClass')
            ->willReturn(true)
        ;

        $provider1
            ->expects(self::once())
            ->method('refreshUser')
            ->willThrowException(new UserNotFoundException('not found'))
        ;

        $provider2 = self::createMock(InMemoryUserProvider::class);
        $provider2
            ->expects(self::once())
            ->method('supportsClass')
            ->willReturn(true)
        ;

        $provider2
            ->expects(self::once())
            ->method('refreshUser')
            ->willReturn($account = self::createMock(UserInterface::class))
        ;

        $provider = new ChainUserProvider([$provider1, $provider2]);
        self::assertSame($account, $provider->refreshUser(self::createMock(UserInterface::class)));
    }

    public function testRefreshUserThrowsUnsupportedUserException()
    {
        self::expectException(UnsupportedUserException::class);
        $provider1 = self::createMock(InMemoryUserProvider::class);
        $provider1
            ->expects(self::once())
            ->method('supportsClass')
            ->willReturn(true)
        ;

        $provider1
            ->expects(self::once())
            ->method('refreshUser')
            ->willThrowException(new UnsupportedUserException('unsupported'))
        ;

        $provider2 = self::createMock(InMemoryUserProvider::class);
        $provider2
            ->expects(self::once())
            ->method('supportsClass')
            ->willReturn(true)
        ;

        $provider2
            ->expects(self::once())
            ->method('refreshUser')
            ->willThrowException(new UnsupportedUserException('unsupported'))
        ;

        $provider = new ChainUserProvider([$provider1, $provider2]);
        $provider->refreshUser(self::createMock(UserInterface::class));
    }

    public function testSupportsClass()
    {
        $provider1 = self::createMock(InMemoryUserProvider::class);
        $provider1
            ->expects(self::once())
            ->method('supportsClass')
            ->with(self::equalTo('foo'))
            ->willReturn(false)
        ;

        $provider2 = self::createMock(InMemoryUserProvider::class);
        $provider2
            ->expects(self::once())
            ->method('supportsClass')
            ->with(self::equalTo('foo'))
            ->willReturn(true)
        ;

        $provider = new ChainUserProvider([$provider1, $provider2]);
        self::assertTrue($provider->supportsClass('foo'));
    }

    public function testSupportsClassWhenNotSupported()
    {
        $provider1 = self::createMock(InMemoryUserProvider::class);
        $provider1
            ->expects(self::once())
            ->method('supportsClass')
            ->with(self::equalTo('foo'))
            ->willReturn(false)
        ;

        $provider2 = self::createMock(InMemoryUserProvider::class);
        $provider2
            ->expects(self::once())
            ->method('supportsClass')
            ->with(self::equalTo('foo'))
            ->willReturn(false)
        ;

        $provider = new ChainUserProvider([$provider1, $provider2]);
        self::assertFalse($provider->supportsClass('foo'));
    }

    public function testAcceptsTraversable()
    {
        $provider1 = self::createMock(InMemoryUserProvider::class);
        $provider1
            ->expects(self::once())
            ->method('supportsClass')
            ->willReturn(true)
        ;

        $provider1
            ->expects(self::once())
            ->method('refreshUser')
            ->willThrowException(new UnsupportedUserException('unsupported'))
        ;

        $provider2 = self::createMock(InMemoryUserProvider::class);
        $provider2
            ->expects(self::once())
            ->method('supportsClass')
            ->willReturn(true)
        ;

        $provider2
            ->expects(self::once())
            ->method('refreshUser')
            ->willReturn($account = self::createMock(UserInterface::class))
        ;

        $provider = new ChainUserProvider(new \ArrayObject([$provider1, $provider2]));
        self::assertSame($account, $provider->refreshUser(self::createMock(UserInterface::class)));
    }

    public function testPasswordUpgrades()
    {
        $user = new InMemoryUser('user', 'pwd');

        $provider1 = self::getMockForAbstractClass(MigratingProvider::class);
        $provider1
            ->expects(self::once())
            ->method('upgradePassword')
            ->willThrowException(new UnsupportedUserException('unsupported'))
        ;

        $provider2 = self::getMockForAbstractClass(MigratingProvider::class);
        $provider2
            ->expects(self::once())
            ->method('upgradePassword')
            ->with($user, 'foobar')
        ;

        $provider = new ChainUserProvider([$provider1, $provider2]);
        $provider->upgradePassword($user, 'foobar');
    }
}

abstract class MigratingProvider implements PasswordUpgraderInterface
{
    abstract public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void;
}
