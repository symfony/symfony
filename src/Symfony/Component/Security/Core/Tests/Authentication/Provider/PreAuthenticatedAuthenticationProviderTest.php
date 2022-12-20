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

        self::assertTrue($provider->supports($this->getSupportedToken()));
        self::assertFalse($provider->supports(self::createMock(TokenInterface::class)));

        $token = self::createMock(PreAuthenticatedToken::class);
        $token
            ->expects(self::once())
            ->method('getFirewallName')
            ->willReturn('foo')
        ;
        self::assertFalse($provider->supports($token));
    }

    public function testAuthenticateWhenTokenIsNotSupported()
    {
        self::expectException(AuthenticationException::class);
        self::expectExceptionMessage('The token is not supported by this authentication provider.');
        $provider = $this->getProvider();

        $provider->authenticate(self::createMock(TokenInterface::class));
    }

    public function testAuthenticateWhenNoUserIsSet()
    {
        self::expectException(BadCredentialsException::class);
        $provider = $this->getProvider();
        $provider->authenticate($this->getSupportedToken(''));
    }

    public function testAuthenticate()
    {
        $user = self::createMock(UserInterface::class);
        $user
            ->expects(self::once())
            ->method('getRoles')
            ->willReturn([])
        ;
        $provider = $this->getProvider($user);

        $token = $provider->authenticate($this->getSupportedToken('fabien', 'pass'));
        self::assertInstanceOf(PreAuthenticatedToken::class, $token);
        self::assertEquals('pass', $token->getCredentials());
        self::assertEquals('key', $token->getFirewallName());
        self::assertEquals([], $token->getRoleNames());
        self::assertEquals(['foo' => 'bar'], $token->getAttributes(), '->authenticate() copies token attributes');
        self::assertSame($user, $token->getUser());
    }

    public function testAuthenticateWhenUserCheckerThrowsException()
    {
        self::expectException(LockedException::class);
        $user = self::createMock(UserInterface::class);

        $userChecker = self::createMock(UserCheckerInterface::class);
        $userChecker->expects(self::once())
                    ->method('checkPostAuth')
                    ->willThrowException(new LockedException())
        ;

        $provider = $this->getProvider($user, $userChecker);

        $provider->authenticate($this->getSupportedToken('fabien'));
    }

    protected function getSupportedToken($user = false, $credentials = false)
    {
        $token = self::getMockBuilder(PreAuthenticatedToken::class)->setMethods(['getUser', 'getCredentials', 'getFirewallName'])->disableOriginalConstructor()->getMock();
        if (false !== $user) {
            $token->expects(self::once())
                  ->method('getUser')
                  ->willReturn($user)
            ;
        }
        if (false !== $credentials) {
            $token->expects(self::once())
                  ->method('getCredentials')
                  ->willReturn($credentials)
            ;
        }

        $token
            ->expects(self::any())
            ->method('getFirewallName')
            ->willReturn('key')
        ;

        $token->setAttributes(['foo' => 'bar']);

        return $token;
    }

    protected function getProvider($user = null, $userChecker = null)
    {
        $userProvider = self::createMock(InMemoryUserProvider::class);
        if (null !== $user) {
            $userProvider->expects(self::once())
                         ->method('loadUserByIdentifier')
                         ->willReturn($user)
            ;
        }

        if (null === $userChecker) {
            $userChecker = self::createMock(UserCheckerInterface::class);
        }

        return new PreAuthenticatedAuthenticationProvider($userProvider, $userChecker, 'key');
    }
}
