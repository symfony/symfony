<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Authentication\Provider;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Provider\PreAuthenticatedAuthenticationProvider;
use Symfony\Component\Security\Core\Exception\LockedException;

class PreAuthenticatedAuthenticationProviderTest extends TestCase
{
    public function testSupports()
    {
        $provider = $this->getProvider();

        $this->assertTrue($provider->supports($this->getSupportedToken()));
        $this->assertFalse($provider->supports($this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock()));

        $token = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken')
                    ->disableOriginalConstructor()
                    ->getMock()
        ;
        $token
            ->expects($this->once())
            ->method('getProviderKey')
            ->willReturn('foo')
        ;
        $this->assertFalse($provider->supports($token));
    }

    public function testAuthenticateWhenTokenIsNotSupported()
    {
        $this->expectException('Symfony\Component\Security\Core\Exception\AuthenticationException');
        $this->expectExceptionMessage('The token is not supported by this authentication provider.');
        $provider = $this->getProvider();

        $provider->authenticate($this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock());
    }

    public function testAuthenticateWhenNoUserIsSet()
    {
        $this->expectException('Symfony\Component\Security\Core\Exception\BadCredentialsException');
        $provider = $this->getProvider();
        $provider->authenticate($this->getSupportedToken(''));
    }

    public function testAuthenticate()
    {
        $user = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserInterface')->getMock();
        $user
            ->expects($this->once())
            ->method('getRoles')
            ->willReturn([])
        ;
        $provider = $this->getProvider($user);

        $token = $provider->authenticate($this->getSupportedToken('fabien', 'pass'));
        $this->assertInstanceOf('Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken', $token);
        $this->assertEquals('pass', $token->getCredentials());
        $this->assertEquals('key', $token->getProviderKey());
        $this->assertEquals([], $token->getRoleNames());
        $this->assertEquals(['foo' => 'bar'], $token->getAttributes(), '->authenticate() copies token attributes');
        $this->assertSame($user, $token->getUser());
    }

    public function testAuthenticateWhenUserCheckerThrowsException()
    {
        $this->expectException('Symfony\Component\Security\Core\Exception\LockedException');
        $user = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserInterface')->getMock();

        $userChecker = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserCheckerInterface')->getMock();
        $userChecker->expects($this->once())
                    ->method('checkPostAuth')
                    ->willThrowException(new LockedException())
        ;

        $provider = $this->getProvider($user, $userChecker);

        $provider->authenticate($this->getSupportedToken('fabien'));
    }

    protected function getSupportedToken($user = false, $credentials = false)
    {
        $token = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken')->setMethods(['getUser', 'getCredentials', 'getProviderKey'])->disableOriginalConstructor()->getMock();
        if (false !== $user) {
            $token->expects($this->once())
                  ->method('getUser')
                  ->willReturn($user)
            ;
        }
        if (false !== $credentials) {
            $token->expects($this->once())
                  ->method('getCredentials')
                  ->willReturn($credentials)
            ;
        }

        $token
            ->expects($this->any())
            ->method('getProviderKey')
            ->willReturn('key')
        ;

        $token->setAttributes(['foo' => 'bar']);

        return $token;
    }

    protected function getProvider($user = null, $userChecker = null)
    {
        $userProvider = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserProviderInterface')->getMock();
        if (null !== $user) {
            $userProvider->expects($this->once())
                         ->method('loadUserByUsername')
                         ->willReturn($user)
            ;
        }

        if (null === $userChecker) {
            $userChecker = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserCheckerInterface')->getMock();
        }

        return new PreAuthenticatedAuthenticationProvider($userProvider, $userChecker, 'key');
    }
}
