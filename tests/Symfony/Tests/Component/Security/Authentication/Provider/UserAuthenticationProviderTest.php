<?php

/*
 * This file is part of the Symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Security\Authentication\Provider;

use Symfony\Component\Security\Authentication\Provider\UserAuthenticationProvider;
use Symfony\Component\Security\Role\Role;

class UserAuthenticationProviderTest extends \PHPUnit_Framework_TestCase
{
    public function testSupports()
    {
        $provider = $this->getProvider();

        $this->assertTrue($provider->supports($this->getSupportedToken()));
        $this->assertFalse($provider->supports($this->getMock('Symfony\Component\Security\Authentication\Token\TokenInterface')));
    }

    public function testAuthenticateWhenTokenIsNotSupported()
    {
        $provider = $this->getProvider();

        $this->assertNull($provider->authenticate($this->getMock('Symfony\Component\Security\Authentication\Token\TokenInterface')));
    }

    /**
     * @expectedException Symfony\Component\Security\Exception\UsernameNotFoundException
     */
    public function testAuthenticateWhenUsernameIsNotFound()
    {
        $provider = $this->getProvider(false, false);
        $provider->expects($this->once())
                 ->method('retrieveUser')
                 ->will($this->throwException($this->getMock('Symfony\Component\Security\Exception\UsernameNotFoundException', null, array(), '', false)))
        ;

        $provider->authenticate($this->getSupportedToken());
    }

    /**
     * @expectedException Symfony\Component\Security\Exception\BadCredentialsException
     */
    public function testAuthenticateWhenUsernameIsNotFoundAndHideIsTrue()
    {
        $provider = $this->getProvider(false, true);
        $provider->expects($this->once())
                 ->method('retrieveUser')
                 ->will($this->throwException($this->getMock('Symfony\Component\Security\Exception\UsernameNotFoundException', null, array(), '', false)))
        ;

        $provider->authenticate($this->getSupportedToken());
    }

    /**
     * @expectedException Symfony\Component\Security\Exception\AuthenticationServiceException
     */
    public function testAuthenticateWhenProviderDoesNotReturnAnAccountInterface()
    {
        $provider = $this->getProvider(false, true);
        $provider->expects($this->once())
                 ->method('retrieveUser')
                 ->will($this->returnValue(null))
        ;

        $provider->authenticate($this->getSupportedToken());
    }

    /**
     * @expectedException Symfony\Component\Security\Exception\CredentialsExpiredException
     */
    public function testAuthenticateWhenPreChecksFails()
    {
        $userChecker = $this->getMock('Symfony\Component\Security\User\AccountCheckerInterface');
        $userChecker->expects($this->once())
                    ->method('checkPreAuth')
                    ->will($this->throwException($this->getMock('Symfony\Component\Security\Exception\CredentialsExpiredException', null, array(), '', false)))
        ;

        $provider = $this->getProvider($userChecker);
        $provider->expects($this->once())
                 ->method('retrieveUser')
                 ->will($this->returnValue(array($this->getMock('Symfony\Component\Security\User\AccountInterface'), 'foo')))
        ;

        $provider->authenticate($this->getSupportedToken());
    }

    /**
     * @expectedException Symfony\Component\Security\Exception\AccountExpiredException
     */
    public function testAuthenticateWhenPostChecksFails()
    {
        $userChecker = $this->getMock('Symfony\Component\Security\User\AccountCheckerInterface');
        $userChecker->expects($this->once())
                    ->method('checkPostAuth')
                    ->will($this->throwException($this->getMock('Symfony\Component\Security\Exception\AccountExpiredException', null, array(), '', false)))
        ;

        $provider = $this->getProvider($userChecker);
        $provider->expects($this->once())
                 ->method('retrieveUser')
                 ->will($this->returnValue(array($this->getMock('Symfony\Component\Security\User\AccountInterface'), 'foo')))
        ;

        $provider->authenticate($this->getSupportedToken());
    }

    /**
     * @expectedException Symfony\Component\Security\Exception\BadCredentialsException
     */
    public function testAuthenticateWhenPostCheckAuthenticationFails()
    {
        $provider = $this->getProvider();
        $provider->expects($this->once())
                 ->method('retrieveUser')
                 ->will($this->returnValue(array($this->getMock('Symfony\Component\Security\User\AccountInterface'), 'foo')))
        ;
        $provider->expects($this->once())
                 ->method('checkAuthentication')
                 ->will($this->throwException($this->getMock('Symfony\Component\Security\Exception\BadCredentialsException', null, array(), '', false)))
        ;

        $provider->authenticate($this->getSupportedToken());
    }

    public function testAuthenticate()
    {
        $user = $this->getMock('Symfony\Component\Security\User\AccountInterface');
        $user->expects($this->once())
             ->method('getRoles')
             ->will($this->returnValue(array('ROLE_FOO')))
        ;

        $provider = $this->getProvider();
        $provider->expects($this->once())
                 ->method('retrieveUser')
                 ->will($this->returnValue(array($user, 'foo')))
        ;

        $token = $this->getSupportedToken();
        $token->expects($this->once())
              ->method('getCredentials')
              ->will($this->returnValue('foo'))
        ;

        $authToken = $provider->authenticate($token);

        $this->assertInstanceOf('Symfony\Component\Security\Authentication\Token\UsernamePasswordToken', $authToken);
        $this->assertSame($user, $authToken->getUser());
        $this->assertSame('foo', $authToken->getUserProviderName());
        $this->assertEquals(array(new Role('ROLE_FOO')), $authToken->getRoles());
        $this->assertEquals('foo', $authToken->getCredentials());
    }

    protected function getSupportedToken()
    {
        return $this->getMock('Symfony\Component\Security\Authentication\Token\UsernamePasswordToken', array('getCredentials'), array(), '', false);
    }

    protected function getProvider($userChecker = false, $hide = true)
    {
        if (false === $userChecker) {
            $userChecker = $this->getMock('Symfony\Component\Security\User\AccountCheckerInterface');
        }

        return $this->getMockForAbstractClass('Symfony\Component\Security\Authentication\Provider\UserAuthenticationProvider', array($userChecker, $hide));
    }
}
