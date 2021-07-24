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
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\LockedException;
use Symfony\Component\Security\Core\User\InMemoryUserProvider;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @group legacy
 */
class PreAuthenticatedAuthenticationProviderTest extends TestCase
{
    public function testSupports()
    {
        $provider = $this->getProvider();

        $this->assertTrue($provider->supports($this->getSupportedToken()));
        $this->assertFalse($provider->supports($this->createMock(TokenInterface::class)));

        $token = $this->createMock(PreAuthenticatedToken::class);
        $token
            ->expects($this->once())
            ->method('getFirewallName')
            ->willReturn('foo')
        ;
        $this->assertFalse($provider->supports($token));
    }

    public function testAuthenticateWhenTokenIsNotSupported()
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('The token is not supported by this authentication provider.');
        $provider = $this->getProvider();

        $provider->authenticate($this->createMock(TokenInterface::class));
    }

    public function testAuthenticateWhenNoUserIsSet()
    {
        $this->expectException(BadCredentialsException::class);
        $provider = $this->getProvider();
        $provider->authenticate($this->getSupportedToken(''));
    }

    public function testAuthenticate()
    {
        $user = $this->createMock(UserInterface::class);
        $user
            ->expects($this->once())
            ->method('getRoles')
            ->willReturn([])
        ;
        $provider = $this->getProvider($user);

        $token = $provider->authenticate($this->getSupportedToken('fabien', 'pass'));
        $this->assertInstanceOf(PreAuthenticatedToken::class, $token);
        $this->assertEquals('pass', $token->getCredentials());
        $this->assertEquals('key', $token->getFirewallName());
        $this->assertEquals([], $token->getRoleNames());
        $this->assertEquals(['foo' => 'bar'], $token->getAttributes(), '->authenticate() copies token attributes');
        $this->assertSame($user, $token->getUser());
    }

    public function testAuthenticateWhenUserCheckerThrowsException()
    {
        $this->expectException(LockedException::class);
        $user = $this->createMock(UserInterface::class);

        $userChecker = $this->createMock(UserCheckerInterface::class);
        $userChecker->expects($this->once())
                    ->method('checkPostAuth')
                    ->willThrowException(new LockedException())
        ;

        $provider = $this->getProvider($user, $userChecker);

        $provider->authenticate($this->getSupportedToken('fabien'));
    }

    protected function getSupportedToken($user = false, $credentials = false)
    {
        $token = $this->getMockBuilder(PreAuthenticatedToken::class)->setMethods(['getUser', 'getCredentials', 'getFirewallName'])->disableOriginalConstructor()->getMock();
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
            ->method('getFirewallName')
            ->willReturn('key')
        ;

        $token->setAttributes(['foo' => 'bar']);

        return $token;
    }

    protected function getProvider($user = null, $userChecker = null)
    {
        $userProvider = $this->createMock(InMemoryUserProvider::class);
        if (null !== $user) {
            $userProvider->expects($this->once())
                         ->method('loadUserByIdentifier')
                         ->willReturn($user)
            ;
        }

        if (null === $userChecker) {
            $userChecker = $this->createMock(UserCheckerInterface::class);
        }

        return new PreAuthenticatedAuthenticationProvider($userProvider, $userChecker, 'key');
    }
}
