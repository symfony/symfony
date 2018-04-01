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
use Symphony\Component\Security\Core\Exception\DisabledException;
use Symphony\Component\Security\Core\Authentication\Provider\SimpleAuthenticationProvider;
use Symphony\Component\Security\Core\Exception\LockedException;

class SimpleAuthenticationProviderTest extends TestCase
{
    /**
     * @expectedException \Symphony\Component\Security\Core\Exception\DisabledException
     */
    public function testAuthenticateWhenPreChecksFails()
    {
        $user = $this->getMockBuilder('Symphony\Component\Security\Core\User\UserInterface')->getMock();

        $token = $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock();
        $token->expects($this->any())
            ->method('getUser')
            ->will($this->returnValue($user));

        $userChecker = $this->getMockBuilder('Symphony\Component\Security\Core\User\UserCheckerInterface')->getMock();
        $userChecker->expects($this->once())
            ->method('checkPreAuth')
            ->will($this->throwException(new DisabledException()));

        $authenticator = $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\SimpleAuthenticatorInterface')->getMock();
        $authenticator->expects($this->once())
            ->method('authenticateToken')
            ->will($this->returnValue($token));

        $provider = $this->getProvider($authenticator, null, $userChecker);

        $provider->authenticate($token);
    }

    /**
     * @expectedException \Symphony\Component\Security\Core\Exception\LockedException
     */
    public function testAuthenticateWhenPostChecksFails()
    {
        $user = $this->getMockBuilder('Symphony\Component\Security\Core\User\UserInterface')->getMock();

        $token = $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock();
        $token->expects($this->any())
            ->method('getUser')
            ->will($this->returnValue($user));

        $userChecker = $this->getMockBuilder('Symphony\Component\Security\Core\User\UserCheckerInterface')->getMock();
        $userChecker->expects($this->once())
            ->method('checkPostAuth')
            ->will($this->throwException(new LockedException()));

        $authenticator = $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\SimpleAuthenticatorInterface')->getMock();
        $authenticator->expects($this->once())
            ->method('authenticateToken')
            ->will($this->returnValue($token));

        $provider = $this->getProvider($authenticator, null, $userChecker);

        $provider->authenticate($token);
    }

    protected function getProvider($simpleAuthenticator = null, $userProvider = null, $userChecker = null, $key = 'test')
    {
        if (null === $userChecker) {
            $userChecker = $this->getMockBuilder('Symphony\Component\Security\Core\User\UserCheckerInterface')->getMock();
        }
        if (null === $simpleAuthenticator) {
            $simpleAuthenticator = $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\SimpleAuthenticatorInterface')->getMock();
        }
        if (null === $userProvider) {
            $userProvider = $this->getMockBuilder('Symphony\Component\Security\Core\User\UserProviderInterface')->getMock();
        }

        return new SimpleAuthenticationProvider($simpleAuthenticator, $userProvider, $key, $userChecker);
    }
}
