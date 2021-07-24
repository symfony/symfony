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
        $provider1 = $this->createMock(InMemoryUserProvider::class);
        $provider1
            ->expects($this->once())
            ->method('loadUserByIdentifier')
            ->with($this->equalTo('foo'))
            ->willThrowException(new UserNotFoundException('not found'))
        ;

        $provider2 = $this->createMock(InMemoryUserProvider::class);
        $provider2
            ->expects($this->once())
            ->method('loadUserByIdentifier')
            ->with($this->equalTo('foo'))
            ->willReturn($account = $this->createMock(UserInterface::class))
        ;

        $provider = new ChainUserProvider([$provider1, $provider2]);
        $this->assertSame($account, $provider->loadUserByIdentifier('foo'));
    }

    public function testLoadUserByUsernameThrowsUserNotFoundException()
    {
        $this->expectException(UserNotFoundException::class);
        $provider1 = $this->createMock(InMemoryUserProvider::class);
        $provider1
            ->expects($this->once())
            ->method('loadUserByIdentifier')
            ->with($this->equalTo('foo'))
            ->willThrowException(new UserNotFoundException('not found'))
        ;

        $provider2 = $this->createMock(InMemoryUserProvider::class);
        $provider2
            ->expects($this->once())
            ->method('loadUserByIdentifier')
            ->with($this->equalTo('foo'))
            ->willThrowException(new UserNotFoundException('not found'))
        ;

        $provider = new ChainUserProvider([$provider1, $provider2]);
        $provider->loadUserByIdentifier('foo');
    }

    public function testRefreshUser()
    {
        $provider1 = $this->createMock(InMemoryUserProvider::class);
        $provider1
            ->expects($this->once())
            ->method('supportsClass')
            ->willReturn(false)
        ;

        $provider2 = $this->createMock(InMemoryUserProvider::class);
        $provider2
            ->expects($this->once())
            ->method('supportsClass')
            ->willReturn(true)
        ;

        $provider2
            ->expects($this->once())
            ->method('refreshUser')
            ->willThrowException(new UnsupportedUserException('unsupported'))
        ;

        $provider3 = $this->createMock(InMemoryUserProvider::class);
        $provider3
            ->expects($this->once())
            ->method('supportsClass')
            ->willReturn(true)
        ;

        $provider3
            ->expects($this->once())
            ->method('refreshUser')
            ->willReturn($account = $this->createMock(UserInterface::class))
        ;

        $provider = new ChainUserProvider([$provider1, $provider2, $provider3]);
        $this->assertSame($account, $provider->refreshUser($this->createMock(UserInterface::class)));
    }

    public function testRefreshUserAgain()
    {
        $provider1 = $this->createMock(InMemoryUserProvider::class);
        $provider1
            ->expects($this->once())
            ->method('supportsClass')
            ->willReturn(true)
        ;

        $provider1
            ->expects($this->once())
            ->method('refreshUser')
            ->willThrowException(new UserNotFoundException('not found'))
        ;

        $provider2 = $this->createMock(InMemoryUserProvider::class);
        $provider2
            ->expects($this->once())
            ->method('supportsClass')
            ->willReturn(true)
        ;

        $provider2
            ->expects($this->once())
            ->method('refreshUser')
            ->willReturn($account = $this->createMock(UserInterface::class))
        ;

        $provider = new ChainUserProvider([$provider1, $provider2]);
        $this->assertSame($account, $provider->refreshUser($this->createMock(UserInterface::class)));
    }

    public function testRefreshUserThrowsUnsupportedUserException()
    {
        $this->expectException(UnsupportedUserException::class);
        $provider1 = $this->createMock(InMemoryUserProvider::class);
        $provider1
            ->expects($this->once())
            ->method('supportsClass')
            ->willReturn(true)
        ;

        $provider1
            ->expects($this->once())
            ->method('refreshUser')
            ->willThrowException(new UnsupportedUserException('unsupported'))
        ;

        $provider2 = $this->createMock(InMemoryUserProvider::class);
        $provider2
            ->expects($this->once())
            ->method('supportsClass')
            ->willReturn(true)
        ;

        $provider2
            ->expects($this->once())
            ->method('refreshUser')
            ->willThrowException(new UnsupportedUserException('unsupported'))
        ;

        $provider = new ChainUserProvider([$provider1, $provider2]);
        $provider->refreshUser($this->createMock(UserInterface::class));
    }

    public function testSupportsClass()
    {
        $provider1 = $this->createMock(InMemoryUserProvider::class);
        $provider1
            ->expects($this->once())
            ->method('supportsClass')
            ->with($this->equalTo('foo'))
            ->willReturn(false)
        ;

        $provider2 = $this->createMock(InMemoryUserProvider::class);
        $provider2
            ->expects($this->once())
            ->method('supportsClass')
            ->with($this->equalTo('foo'))
            ->willReturn(true)
        ;

        $provider = new ChainUserProvider([$provider1, $provider2]);
        $this->assertTrue($provider->supportsClass('foo'));
    }

    public function testSupportsClassWhenNotSupported()
    {
        $provider1 = $this->createMock(InMemoryUserProvider::class);
        $provider1
            ->expects($this->once())
            ->method('supportsClass')
            ->with($this->equalTo('foo'))
            ->willReturn(false)
        ;

        $provider2 = $this->createMock(InMemoryUserProvider::class);
        $provider2
            ->expects($this->once())
            ->method('supportsClass')
            ->with($this->equalTo('foo'))
            ->willReturn(false)
        ;

        $provider = new ChainUserProvider([$provider1, $provider2]);
        $this->assertFalse($provider->supportsClass('foo'));
    }

    public function testAcceptsTraversable()
    {
        $provider1 = $this->createMock(InMemoryUserProvider::class);
        $provider1
            ->expects($this->once())
            ->method('supportsClass')
            ->willReturn(true)
        ;

        $provider1
            ->expects($this->once())
            ->method('refreshUser')
            ->willThrowException(new UnsupportedUserException('unsupported'))
        ;

        $provider2 = $this->createMock(InMemoryUserProvider::class);
        $provider2
            ->expects($this->once())
            ->method('supportsClass')
            ->willReturn(true)
        ;

        $provider2
            ->expects($this->once())
            ->method('refreshUser')
            ->willReturn($account = $this->createMock(UserInterface::class))
        ;

        $provider = new ChainUserProvider(new \ArrayObject([$provider1, $provider2]));
        $this->assertSame($account, $provider->refreshUser($this->createMock(UserInterface::class)));
    }

    public function testPasswordUpgrades()
    {
        $user = new InMemoryUser('user', 'pwd');

        $provider1 = $this->getMockForAbstractClass(MigratingProvider::class);
        $provider1
            ->expects($this->once())
            ->method('upgradePassword')
            ->willThrowException(new UnsupportedUserException('unsupported'))
        ;

        $provider2 = $this->getMockForAbstractClass(MigratingProvider::class);
        $provider2
            ->expects($this->once())
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
