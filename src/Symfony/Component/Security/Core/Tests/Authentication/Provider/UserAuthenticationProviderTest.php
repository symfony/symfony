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
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Exception\AccountExpiredException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\CredentialsExpiredException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\Role\SwitchUserRole;

class UserAuthenticationProviderTest extends TestCase
{
    public function testSupports()
    {
        $provider = $this->getProvider();

        $this->assertTrue($provider->supports($this->getSupportedToken()));
        $this->assertFalse($provider->supports($this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock()));
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\AuthenticationException
     * @expectedExceptionMessage The token is not supported by this authentication provider.
     */
    public function testAuthenticateWhenTokenIsNotSupported()
    {
        $provider = $this->getProvider();

        $provider->authenticate($this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock());
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\UsernameNotFoundException
     */
    public function testAuthenticateWhenUsernameIsNotFound()
    {
        $provider = $this->getProvider(false, false);
        $provider->expects($this->once())
                 ->method('retrieveUser')
                 ->willThrowException(new UsernameNotFoundException())
        ;

        $provider->authenticate($this->getSupportedToken());
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\BadCredentialsException
     */
    public function testAuthenticateWhenUsernameIsNotFoundAndHideIsTrue()
    {
        $provider = $this->getProvider(false, true);
        $provider->expects($this->once())
                 ->method('retrieveUser')
                 ->willThrowException(new UsernameNotFoundException())
        ;

        $provider->authenticate($this->getSupportedToken());
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\AuthenticationServiceException
     */
    public function testAuthenticateWhenProviderDoesNotReturnAnUserInterface()
    {
        $provider = $this->getProvider(false, true);
        $provider->expects($this->once())
                 ->method('retrieveUser')
                 ->willReturn(null)
        ;

        $provider->authenticate($this->getSupportedToken());
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\CredentialsExpiredException
     */
    public function testAuthenticateWhenPreChecksFails()
    {
        $userChecker = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserCheckerInterface')->getMock();
        $userChecker->expects($this->once())
                    ->method('checkPreAuth')
                    ->willThrowException(new CredentialsExpiredException())
        ;

        $provider = $this->getProvider($userChecker);
        $provider->expects($this->once())
                 ->method('retrieveUser')
                 ->willReturn($this->getMockBuilder('Symfony\Component\Security\Core\User\UserInterface')->getMock())
        ;

        $provider->authenticate($this->getSupportedToken());
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\AccountExpiredException
     */
    public function testAuthenticateWhenPostChecksFails()
    {
        $userChecker = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserCheckerInterface')->getMock();
        $userChecker->expects($this->once())
                    ->method('checkPostAuth')
                    ->willThrowException(new AccountExpiredException())
        ;

        $provider = $this->getProvider($userChecker);
        $provider->expects($this->once())
                 ->method('retrieveUser')
                 ->willReturn($this->getMockBuilder('Symfony\Component\Security\Core\User\UserInterface')->getMock())
        ;

        $provider->authenticate($this->getSupportedToken());
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\BadCredentialsException
     * @expectedExceptionMessage Bad credentials
     */
    public function testAuthenticateWhenPostCheckAuthenticationFails()
    {
        $provider = $this->getProvider();
        $provider->expects($this->once())
                 ->method('retrieveUser')
                 ->willReturn($this->getMockBuilder('Symfony\Component\Security\Core\User\UserInterface')->getMock())
        ;
        $provider->expects($this->once())
                 ->method('checkAuthentication')
                 ->willThrowException(new BadCredentialsException())
        ;

        $provider->authenticate($this->getSupportedToken());
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\BadCredentialsException
     * @expectedExceptionMessage Foo
     */
    public function testAuthenticateWhenPostCheckAuthenticationFailsWithHideFalse()
    {
        $provider = $this->getProvider(false, false);
        $provider->expects($this->once())
                 ->method('retrieveUser')
                 ->willReturn($this->getMockBuilder('Symfony\Component\Security\Core\User\UserInterface')->getMock())
        ;
        $provider->expects($this->once())
                 ->method('checkAuthentication')
                 ->willThrowException(new BadCredentialsException('Foo'))
        ;

        $provider->authenticate($this->getSupportedToken());
    }

    public function testAuthenticate()
    {
        $user = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserInterface')->getMock();
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

        $token->expects($this->once())
              ->method('getRoles')
              ->willReturn([])
        ;

        $authToken = $provider->authenticate($token);

        $this->assertInstanceOf('Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken', $authToken);
        $this->assertSame($user, $authToken->getUser());
        $this->assertEquals(['ROLE_FOO'], $authToken->getRoleNames());
        $this->assertEquals('foo', $authToken->getCredentials());
        $this->assertEquals(['foo' => 'bar'], $authToken->getAttributes(), '->authenticate() copies token attributes');
    }

    /**
     * @group legacy
     */
    public function testAuthenticateWithPreservingRoleSwitchUserRole()
    {
        $user = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserInterface')->getMock();
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

        $switchUserRole = new SwitchUserRole('foo', $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock());
        $token->expects($this->once())
              ->method('getRoles')
              ->willReturn([$switchUserRole])
        ;

        $authToken = $provider->authenticate($token);

        $this->assertInstanceOf('Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken', $authToken);
        $this->assertSame($user, $authToken->getUser());
        $this->assertContains('ROLE_FOO', $authToken->getRoleNames(), '', false, false);
        $this->assertContains($switchUserRole, $authToken->getRoles(), '', false, false);
        $this->assertEquals('foo', $authToken->getCredentials());
        $this->assertEquals(['foo' => 'bar'], $authToken->getAttributes(), '->authenticate() copies token attributes');
    }

    public function testAuthenticatePreservesOriginalToken()
    {
        $user = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserInterface')->getMock();
        $user->expects($this->once())
             ->method('getRoles')
             ->willReturn(['ROLE_FOO'])
        ;

        $provider = $this->getProvider();
        $provider->expects($this->once())
                 ->method('retrieveUser')
                 ->willReturn($user)
        ;

        $originalToken = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock();
        $token = new SwitchUserToken($this->getMockBuilder('Symfony\Component\Security\Core\User\UserInterface')->getMock(), 'foo', 'key', [], $originalToken);
        $token->setAttributes(['foo' => 'bar']);

        $authToken = $provider->authenticate($token);

        $this->assertInstanceOf(SwitchUserToken::class, $authToken);
        $this->assertSame($originalToken, $authToken->getOriginalToken());
        $this->assertSame($user, $authToken->getUser());
        $this->assertContains('ROLE_FOO', $authToken->getRoleNames(), '', false, false);
        $this->assertEquals('foo', $authToken->getCredentials());
        $this->assertEquals(['foo' => 'bar'], $authToken->getAttributes(), '->authenticate() copies token attributes');
    }

    protected function getSupportedToken()
    {
        $mock = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken')->setMethods(['getCredentials', 'getProviderKey', 'getRoles'])->disableOriginalConstructor()->getMock();
        $mock
            ->expects($this->any())
            ->method('getProviderKey')
            ->willReturn('key')
        ;

        $mock->setAttributes(['foo' => 'bar']);

        return $mock;
    }

    protected function getProvider($userChecker = false, $hide = true)
    {
        if (false === $userChecker) {
            $userChecker = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserCheckerInterface')->getMock();
        }

        return $this->getMockForAbstractClass('Symfony\Component\Security\Core\Authentication\Provider\UserAuthenticationProvider', [$userChecker, 'key', $hide]);
    }
}
