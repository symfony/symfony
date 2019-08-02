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
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\ChainUserProvider;

class ChainUserProviderTest extends TestCase
{
    public function testLoadUserByUsername()
    {
        $provider1 = $this->getProvider();
        $provider1
            ->expects($this->once())
            ->method('loadUserByUsername')
            ->with($this->equalTo('foo'))
            ->willThrowException(new UsernameNotFoundException('not found'))
        ;

        $provider2 = $this->getProvider();
        $provider2
            ->expects($this->once())
            ->method('loadUserByUsername')
            ->with($this->equalTo('foo'))
            ->willReturn($account = $this->getAccount())
        ;

        $provider = new ChainUserProvider([$provider1, $provider2]);
        $this->assertSame($account, $provider->loadUserByUsername('foo'));
    }

    public function testLoadUserByUsernameThrowsUsernameNotFoundException()
    {
        $this->expectException('Symfony\Component\Security\Core\Exception\UsernameNotFoundException');
        $provider1 = $this->getProvider();
        $provider1
            ->expects($this->once())
            ->method('loadUserByUsername')
            ->with($this->equalTo('foo'))
            ->willThrowException(new UsernameNotFoundException('not found'))
        ;

        $provider2 = $this->getProvider();
        $provider2
            ->expects($this->once())
            ->method('loadUserByUsername')
            ->with($this->equalTo('foo'))
            ->willThrowException(new UsernameNotFoundException('not found'))
        ;

        $provider = new ChainUserProvider([$provider1, $provider2]);
        $provider->loadUserByUsername('foo');
    }

    public function testRefreshUser()
    {
        $provider1 = $this->getProvider();
        $provider1
            ->expects($this->once())
            ->method('refreshUser')
            ->willThrowException(new UnsupportedUserException('unsupported'))
        ;

        $provider2 = $this->getProvider();
        $provider2
            ->expects($this->once())
            ->method('refreshUser')
            ->willReturn($account = $this->getAccount())
        ;

        $provider = new ChainUserProvider([$provider1, $provider2]);
        $this->assertSame($account, $provider->refreshUser($this->getAccount()));
    }

    public function testRefreshUserAgain()
    {
        $provider1 = $this->getProvider();
        $provider1
            ->expects($this->once())
            ->method('refreshUser')
            ->willThrowException(new UsernameNotFoundException('not found'))
        ;

        $provider2 = $this->getProvider();
        $provider2
            ->expects($this->once())
            ->method('refreshUser')
            ->willReturn($account = $this->getAccount())
        ;

        $provider = new ChainUserProvider([$provider1, $provider2]);
        $this->assertSame($account, $provider->refreshUser($this->getAccount()));
    }

    public function testRefreshUserThrowsUnsupportedUserException()
    {
        $this->expectException('Symfony\Component\Security\Core\Exception\UnsupportedUserException');
        $provider1 = $this->getProvider();
        $provider1
            ->expects($this->once())
            ->method('refreshUser')
            ->willThrowException(new UnsupportedUserException('unsupported'))
        ;

        $provider2 = $this->getProvider();
        $provider2
            ->expects($this->once())
            ->method('refreshUser')
            ->willThrowException(new UnsupportedUserException('unsupported'))
        ;

        $provider = new ChainUserProvider([$provider1, $provider2]);
        $provider->refreshUser($this->getAccount());
    }

    public function testSupportsClass()
    {
        $provider1 = $this->getProvider();
        $provider1
            ->expects($this->once())
            ->method('supportsClass')
            ->with($this->equalTo('foo'))
            ->willReturn(false)
        ;

        $provider2 = $this->getProvider();
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
        $provider1 = $this->getProvider();
        $provider1
            ->expects($this->once())
            ->method('supportsClass')
            ->with($this->equalTo('foo'))
            ->willReturn(false)
        ;

        $provider2 = $this->getProvider();
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
        $provider1 = $this->getProvider();
        $provider1
            ->expects($this->once())
            ->method('refreshUser')
            ->willThrowException(new UnsupportedUserException('unsupported'))
        ;

        $provider2 = $this->getProvider();
        $provider2
            ->expects($this->once())
            ->method('refreshUser')
            ->willReturn($account = $this->getAccount())
        ;

        $provider = new ChainUserProvider(new \ArrayObject([$provider1, $provider2]));
        $this->assertSame($account, $provider->refreshUser($this->getAccount()));
    }

    protected function getAccount()
    {
        return $this->getMockBuilder('Symfony\Component\Security\Core\User\UserInterface')->getMock();
    }

    protected function getProvider()
    {
        return $this->getMockBuilder('Symfony\Component\Security\Core\User\UserProviderInterface')->getMock();
    }
}
