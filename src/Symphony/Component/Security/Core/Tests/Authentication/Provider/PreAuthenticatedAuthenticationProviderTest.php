<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Core\Tests\Authentication\Provider;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Security\Core\Authentication\Provider\PreAuthenticatedAuthenticationProvider;
use Symphony\Component\Security\Core\Exception\LockedException;

class PreAuthenticatedAuthenticationProviderTest extends TestCase
{
    public function testSupports()
    {
        $provider = $this->getProvider();

        $this->assertTrue($provider->supports($this->getSupportedToken()));
        $this->assertFalse($provider->supports($this->getMockBuilder('Symphony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock()));

        $token = $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken')
                    ->disableOriginalConstructor()
                    ->getMock()
        ;
        $token
            ->expects($this->once())
            ->method('getProviderKey')
            ->will($this->returnValue('foo'))
        ;
        $this->assertFalse($provider->supports($token));
    }

    /**
     * @expectedException \Symphony\Component\Security\Core\Exception\AuthenticationException
     * @expectedExceptionMessage The token is not supported by this authentication provider.
     */
    public function testAuthenticateWhenTokenIsNotSupported()
    {
        $provider = $this->getProvider();

        $provider->authenticate($this->getMockBuilder('Symphony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock());
    }

    /**
     * @expectedException \Symphony\Component\Security\Core\Exception\BadCredentialsException
     */
    public function testAuthenticateWhenNoUserIsSet()
    {
        $provider = $this->getProvider();
        $provider->authenticate($this->getSupportedToken(''));
    }

    public function testAuthenticate()
    {
        $user = $this->getMockBuilder('Symphony\Component\Security\Core\User\UserInterface')->getMock();
        $user
            ->expects($this->once())
            ->method('getRoles')
            ->will($this->returnValue(array()))
        ;
        $provider = $this->getProvider($user);

        $token = $provider->authenticate($this->getSupportedToken('fabien', 'pass'));
        $this->assertInstanceOf('Symphony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken', $token);
        $this->assertEquals('pass', $token->getCredentials());
        $this->assertEquals('key', $token->getProviderKey());
        $this->assertEquals(array(), $token->getRoles());
        $this->assertEquals(array('foo' => 'bar'), $token->getAttributes(), '->authenticate() copies token attributes');
        $this->assertSame($user, $token->getUser());
    }

    /**
     * @expectedException \Symphony\Component\Security\Core\Exception\LockedException
     */
    public function testAuthenticateWhenUserCheckerThrowsException()
    {
        $user = $this->getMockBuilder('Symphony\Component\Security\Core\User\UserInterface')->getMock();

        $userChecker = $this->getMockBuilder('Symphony\Component\Security\Core\User\UserCheckerInterface')->getMock();
        $userChecker->expects($this->once())
                    ->method('checkPostAuth')
                    ->will($this->throwException(new LockedException()))
        ;

        $provider = $this->getProvider($user, $userChecker);

        $provider->authenticate($this->getSupportedToken('fabien'));
    }

    protected function getSupportedToken($user = false, $credentials = false)
    {
        $token = $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken')->setMethods(array('getUser', 'getCredentials', 'getProviderKey'))->disableOriginalConstructor()->getMock();
        if (false !== $user) {
            $token->expects($this->once())
                  ->method('getUser')
                  ->will($this->returnValue($user))
            ;
        }
        if (false !== $credentials) {
            $token->expects($this->once())
                  ->method('getCredentials')
                  ->will($this->returnValue($credentials))
            ;
        }

        $token
            ->expects($this->any())
            ->method('getProviderKey')
            ->will($this->returnValue('key'))
        ;

        $token->setAttributes(array('foo' => 'bar'));

        return $token;
    }

    protected function getProvider($user = null, $userChecker = null)
    {
        $userProvider = $this->getMockBuilder('Symphony\Component\Security\Core\User\UserProviderInterface')->getMock();
        if (null !== $user) {
            $userProvider->expects($this->once())
                         ->method('loadUserByUsername')
                         ->will($this->returnValue($user))
            ;
        }

        if (null === $userChecker) {
            $userChecker = $this->getMockBuilder('Symphony\Component\Security\Core\User\UserCheckerInterface')->getMock();
        }

        return new PreAuthenticatedAuthenticationProvider($userProvider, $userChecker, 'key');
    }
}
