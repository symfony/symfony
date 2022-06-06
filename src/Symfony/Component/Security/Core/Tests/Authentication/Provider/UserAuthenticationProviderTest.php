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

        $this->assertTrue($provider->supports($this->getSupportedToken()));
        $this->assertFalse($provider->supports($this->createMock(TokenInterface::class)));
    }

    public function testAuthenticateWhenTokenIsNotSupported()
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('The token is not supported by this authentication provider.');
        $provider = $this->getProvider();

        $provider->authenticate($this->createMock(TokenInterface::class));
    }

    public function testAuthenticateWhenUsernameIsNotFound()
    {
        $this->expectException(UserNotFoundException::class);
        $provider = $this->getProvider(false, false);
        $provider->expects($this->once())
                 ->method('retrieveUser')
                 ->willThrowException(new UserNotFoundException())
        ;

        $provider->authenticate($this->getSupportedToken());
    }

    public function testAuthenticateWhenUsernameIsNotFoundAndHideIsTrue()
    {
        $this->expectException(BadCredentialsException::class);
        $provider = $this->getProvider(false, true);
        $provider->expects($this->once())
                 ->method('retrieveUser')
                 ->willThrowException(new UserNotFoundException())
        ;

        $provider->authenticate($this->getSupportedToken());
    }

    public function testAuthenticateWhenCredentialsAreInvalidAndHideIsTrue()
    {
        $provider = $this->getProvider();
        $provider->expects($this->once())
            ->method('retrieveUser')
            ->willReturn($this->createMock(UserInterface::class))
        ;
        $provider->expects($this->once())
            ->method('checkAuthentication')
            ->willThrowException(new BadCredentialsException())
        ;

        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('Bad credentials.');

        $provider->authenticate($this->getSupportedToken());
    }

    public function testAuthenticateWhenProviderDoesNotReturnAnUserInterface()
    {
        $this->expectException(AuthenticationServiceException::class);
        $provider = $this->getProvider(false, true);
        $provider->expects($this->once())
                 ->method('retrieveUser')
                 ->willReturn(null)
        ;

        $provider->authenticate($this->getSupportedToken());
    }

    public function testAuthenticateWhenPreChecksFails()
    {
        $this->expectException(BadCredentialsException::class);
        $userChecker = $this->createMock(UserCheckerInterface::class);
        $userChecker->expects($this->once())
                    ->method('checkPreAuth')
                    ->willThrowException(new CredentialsExpiredException())
        ;

        $provider = $this->getProvider($userChecker);
        $provider->expects($this->once())
                 ->method('retrieveUser')
                 ->willReturn($this->createMock(UserInterface::class))
        ;

        $provider->authenticate($this->getSupportedToken());
    }

    public function testAuthenticateWhenPostChecksFails()
    {
        $this->expectException(BadCredentialsException::class);
        $userChecker = $this->createMock(UserCheckerInterface::class);
        $userChecker->expects($this->once())
                    ->method('checkPostAuth')
                    ->willThrowException(new AccountExpiredException())
        ;

        $provider = $this->getProvider($userChecker);
        $provider->expects($this->once())
                 ->method('retrieveUser')
                 ->willReturn($this->createMock(UserInterface::class))
        ;

        $provider->authenticate($this->getSupportedToken());
    }

    public function testAuthenticateWhenPostCheckAuthenticationFails()
    {
        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('Bad credentials');
        $provider = $this->getProvider();
        $provider->expects($this->once())
                 ->method('retrieveUser')
                 ->willReturn($this->createMock(UserInterface::class))
        ;
        $provider->expects($this->once())
                 ->method('checkAuthentication')
                 ->willThrowException(new CredentialsExpiredException())
        ;

        $provider->authenticate($this->getSupportedToken());
    }

    public function testAuthenticateWhenPostCheckAuthenticationFailsWithHideFalse()
    {
        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('Foo');
        $provider = $this->getProvider(false, false);
        $provider->expects($this->once())
                 ->method('retrieveUser')
                 ->willReturn($this->createMock(UserInterface::class))
        ;
        $provider->expects($this->once())
                 ->method('checkAuthentication')
                 ->willThrowException(new BadCredentialsException('Foo'))
        ;

        $provider->authenticate($this->getSupportedToken());
    }

    public function testAuthenticate()
    {
        $user = $this->createMock(UserInterface::class);
        $user->expects($this->once())
             ->method('getRoles')
             ->willReturn(['ROLE_FOO'])
        ;

        $provider = $this->getProvider();
        $provider->expects($this->once())
                 ->method('retrieveUser')
                 ->willReturn($user)
        ;

        $token = $this->getSupportedToken();
        $token->expects($this->once())
              ->method('getCredentials')
              ->willReturn('foo')
        ;

        $authToken = $provider->authenticate($token);

        $this->assertInstanceOf(UsernamePasswordToken::class, $authToken);
        $this->assertSame($user, $authToken->getUser());
        $this->assertEquals(['ROLE_FOO'], $authToken->getRoleNames());
        $this->assertEquals('foo', $authToken->getCredentials());
        $this->assertEquals(['foo' => 'bar'], $authToken->getAttributes(), '->authenticate() copies token attributes');
    }

    public function testAuthenticatePreservesOriginalToken()
    {
        $user = $this->createMock(UserInterface::class);
        $user->expects($this->once())
             ->method('getRoles')
             ->willReturn(['ROLE_FOO'])
        ;

        $provider = $this->getProvider();
        $provider->expects($this->once())
                 ->method('retrieveUser')
                 ->willReturn($user)
        ;

        $originalToken = $this->createMock(TokenInterface::class);
        $token = new SwitchUserToken(new InMemoryUser('wouter', null), 'foo', 'key', [], $originalToken);
        $token->setAttributes(['foo' => 'bar']);

        $authToken = $provider->authenticate($token);

        $this->assertInstanceOf(SwitchUserToken::class, $authToken);
        $this->assertSame($originalToken, $authToken->getOriginalToken());
        $this->assertSame($user, $authToken->getUser());
        $this->assertContains('ROLE_FOO', $authToken->getRoleNames());
        $this->assertEquals('foo', $authToken->getCredentials());
        $this->assertEquals(['foo' => 'bar'], $authToken->getAttributes(), '->authenticate() copies token attributes');
    }

    protected function getSupportedToken()
    {
        $mock = $this->getMockBuilder(UsernamePasswordToken::class)->setMethods(['getCredentials', 'getFirewallName', 'getRoles'])->disableOriginalConstructor()->getMock();
        $mock
            ->expects($this->any())
            ->method('getFirewallName')
            ->willReturn('key')
        ;

        $mock->setAttributes(['foo' => 'bar']);

        return $mock;
    }

    protected function getProvider($userChecker = false, $hide = true)
    {
        if (false === $userChecker) {
            $userChecker = $this->createMock(UserCheckerInterface::class);
        }

        return $this->getMockForAbstractClass(UserAuthenticationProvider::class, [$userChecker, 'key', $hide]);
    }
}
