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
use Symfony\Component\Security\Core\Authentication\Provider\UserAuthenticationProvider;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AccountExpiredException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\AuthenticationServiceException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\CredentialsExpiredException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @group legacy
 */
class UserAuthenticationProviderTest extends TestCase
{
    public function testSupports()
    {
        $provider = $this->getProvider();

        self::assertTrue($provider->supports($this->getSupportedToken()));
        self::assertFalse($provider->supports(self::createMock(TokenInterface::class)));
    }

    public function testAuthenticateWhenTokenIsNotSupported()
    {
        self::expectException(AuthenticationException::class);
        self::expectExceptionMessage('The token is not supported by this authentication provider.');
        $provider = $this->getProvider();

        $provider->authenticate(self::createMock(TokenInterface::class));
    }

    public function testAuthenticateWhenUsernameIsNotFound()
    {
        self::expectException(UserNotFoundException::class);
        $provider = $this->getProvider(false, false);
        $provider->expects(self::once())
                 ->method('retrieveUser')
                 ->willThrowException(new UserNotFoundException())
        ;

        $provider->authenticate($this->getSupportedToken());
    }

    public function testAuthenticateWhenUsernameIsNotFoundAndHideIsTrue()
    {
        self::expectException(BadCredentialsException::class);
        $provider = $this->getProvider(false, true);
        $provider->expects(self::once())
                 ->method('retrieveUser')
                 ->willThrowException(new UserNotFoundException())
        ;

        $provider->authenticate($this->getSupportedToken());
    }

    public function testAuthenticateWhenCredentialsAreInvalidAndHideIsTrue()
    {
        $provider = $this->getProvider();
        $provider->expects(self::once())
            ->method('retrieveUser')
            ->willReturn(self::createMock(UserInterface::class))
        ;
        $provider->expects(self::once())
            ->method('checkAuthentication')
            ->willThrowException(new BadCredentialsException())
        ;

        self::expectException(BadCredentialsException::class);
        self::expectExceptionMessage('Bad credentials.');

        $provider->authenticate($this->getSupportedToken());
    }

    public function testAuthenticateWhenProviderDoesNotReturnAnUserInterface()
    {
        self::expectException(AuthenticationServiceException::class);
        $provider = $this->getProvider(false, true);
        $provider->expects(self::once())
                 ->method('retrieveUser')
                 ->willReturn(null)
        ;

        $provider->authenticate($this->getSupportedToken());
    }

    public function testAuthenticateWhenPreChecksFails()
    {
        self::expectException(BadCredentialsException::class);
        $userChecker = self::createMock(UserCheckerInterface::class);
        $userChecker->expects(self::once())
                    ->method('checkPreAuth')
                    ->willThrowException(new CredentialsExpiredException())
        ;

        $provider = $this->getProvider($userChecker);
        $provider->expects(self::once())
                 ->method('retrieveUser')
                 ->willReturn(self::createMock(UserInterface::class))
        ;

        $provider->authenticate($this->getSupportedToken());
    }

    public function testAuthenticateWhenPostChecksFails()
    {
        self::expectException(BadCredentialsException::class);
        $userChecker = self::createMock(UserCheckerInterface::class);
        $userChecker->expects(self::once())
                    ->method('checkPostAuth')
                    ->willThrowException(new AccountExpiredException())
        ;

        $provider = $this->getProvider($userChecker);
        $provider->expects(self::once())
                 ->method('retrieveUser')
                 ->willReturn(self::createMock(UserInterface::class))
        ;

        $provider->authenticate($this->getSupportedToken());
    }

    public function testAuthenticateWhenPostCheckAuthenticationFails()
    {
        self::expectException(BadCredentialsException::class);
        self::expectExceptionMessage('Bad credentials');
        $provider = $this->getProvider();
        $provider->expects(self::once())
                 ->method('retrieveUser')
                 ->willReturn(self::createMock(UserInterface::class))
        ;
        $provider->expects(self::once())
                 ->method('checkAuthentication')
                 ->willThrowException(new CredentialsExpiredException())
        ;

        $provider->authenticate($this->getSupportedToken());
    }

    public function testAuthenticateWhenPostCheckAuthenticationFailsWithHideFalse()
    {
        self::expectException(BadCredentialsException::class);
        self::expectExceptionMessage('Foo');
        $provider = $this->getProvider(false, false);
        $provider->expects(self::once())
                 ->method('retrieveUser')
                 ->willReturn(self::createMock(UserInterface::class))
        ;
        $provider->expects(self::once())
                 ->method('checkAuthentication')
                 ->willThrowException(new BadCredentialsException('Foo'))
        ;

        $provider->authenticate($this->getSupportedToken());
    }

    public function testAuthenticate()
    {
        $user = self::createMock(UserInterface::class);
        $user->expects(self::once())
             ->method('getRoles')
             ->willReturn(['ROLE_FOO'])
        ;

        $provider = $this->getProvider();
        $provider->expects(self::once())
                 ->method('retrieveUser')
                 ->willReturn($user)
        ;

        $token = $this->getSupportedToken();
        $token->expects(self::once())
              ->method('getCredentials')
              ->willReturn('foo')
        ;

        $authToken = $provider->authenticate($token);

        self::assertInstanceOf(UsernamePasswordToken::class, $authToken);
        self::assertSame($user, $authToken->getUser());
        self::assertEquals(['ROLE_FOO'], $authToken->getRoleNames());
        self::assertEquals('foo', $authToken->getCredentials());
        self::assertEquals(['foo' => 'bar'], $authToken->getAttributes(), '->authenticate() copies token attributes');
    }

    public function testAuthenticatePreservesOriginalToken()
    {
        $user = self::createMock(UserInterface::class);
        $user->expects(self::once())
             ->method('getRoles')
             ->willReturn(['ROLE_FOO'])
        ;

        $provider = $this->getProvider();
        $provider->expects(self::once())
                 ->method('retrieveUser')
                 ->willReturn($user)
        ;

        $originalToken = self::createMock(TokenInterface::class);
        $token = new SwitchUserToken(new InMemoryUser('wouter', null), 'foo', 'key', [], $originalToken);
        $token->setAttributes(['foo' => 'bar']);

        $authToken = $provider->authenticate($token);

        self::assertInstanceOf(SwitchUserToken::class, $authToken);
        self::assertSame($originalToken, $authToken->getOriginalToken());
        self::assertSame($user, $authToken->getUser());
        self::assertContains('ROLE_FOO', $authToken->getRoleNames());
        self::assertContains('ROLE_PREVIOUS_ADMIN', $authToken->getRoleNames());
        self::assertEquals('foo', $authToken->getCredentials());
        self::assertEquals(['foo' => 'bar'], $authToken->getAttributes(), '->authenticate() copies token attributes');
    }

    protected function getSupportedToken()
    {
        $mock = self::getMockBuilder(UsernamePasswordToken::class)->setMethods(['getCredentials', 'getFirewallName', 'getRoles'])->disableOriginalConstructor()->getMock();
        $mock
            ->expects(self::any())
            ->method('getFirewallName')
            ->willReturn('key')
        ;

        $mock->setAttributes(['foo' => 'bar']);

        return $mock;
    }

    protected function getProvider($userChecker = false, $hide = true)
    {
        if (false === $userChecker) {
            $userChecker = self::createMock(UserCheckerInterface::class);
        }

        return self::getMockForAbstractClass(UserAuthenticationProvider::class, [$userChecker, 'key', $hide]);
    }
}
