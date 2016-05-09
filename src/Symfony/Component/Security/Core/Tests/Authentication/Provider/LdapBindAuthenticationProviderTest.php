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

use Symfony\Component\Security\Core\Authentication\Provider\LdapBindAuthenticationProvider;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Ldap\Exception\ConnectionException;

/**
 * @requires extension ldap
 */
class LdapBindAuthenticationProviderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException        \Symfony\Component\Security\Core\Exception\BadCredentialsException
     * @expectedExceptionMessage The presented password must not be empty.
     */
    public function testEmptyPasswordShouldThrowAnException()
    {
        $userProvider = $this->getMock('Symfony\Component\Security\Core\User\UserProviderInterface');
        $ldap = $this->getMock('Symfony\Component\Ldap\LdapClientInterface');
        $userChecker = $this->getMock('Symfony\Component\Security\Core\User\UserCheckerInterface');

        $provider = new LdapBindAuthenticationProvider($userProvider, $userChecker, 'key', $ldap);
        $reflection = new \ReflectionMethod($provider, 'checkAuthentication');
        $reflection->setAccessible(true);

        $reflection->invoke($provider, new User('foo', null), new UsernamePasswordToken('foo', '', 'key'));
    }

    /**
     * @expectedException        \Symfony\Component\Security\Core\Exception\BadCredentialsException
     * @expectedExceptionMessage The presented password is invalid.
     */
    public function testBindFailureShouldThrowAnException()
    {
        $userProvider = $this->getMock('Symfony\Component\Security\Core\User\UserProviderInterface');
        $ldap = $this->getMock('Symfony\Component\Ldap\LdapClientInterface');
        $ldap
            ->expects($this->once())
            ->method('bind')
            ->will($this->throwException(new ConnectionException()))
        ;
        $userChecker = $this->getMock('Symfony\Component\Security\Core\User\UserCheckerInterface');

        $provider = new LdapBindAuthenticationProvider($userProvider, $userChecker, 'key', $ldap);
        $reflection = new \ReflectionMethod($provider, 'checkAuthentication');
        $reflection->setAccessible(true);

        $reflection->invoke($provider, new User('foo', null), new UsernamePasswordToken('foo', 'bar', 'key'));
    }

    public function testRetrieveUser()
    {
        $userProvider = $this->getMock('Symfony\Component\Security\Core\User\UserProviderInterface');
        $userProvider
            ->expects($this->once())
            ->method('loadUserByUsername')
            ->with('foo')
        ;
        $ldap = $this->getMock('Symfony\Component\Ldap\LdapClientInterface');

        $userChecker = $this->getMock('Symfony\Component\Security\Core\User\UserCheckerInterface');

        $provider = new LdapBindAuthenticationProvider($userProvider, $userChecker, 'key', $ldap);
        $reflection = new \ReflectionMethod($provider, 'retrieveUser');
        $reflection->setAccessible(true);

        $reflection->invoke($provider, 'foo', new UsernamePasswordToken('foo', 'bar', 'key'));
    }
}
