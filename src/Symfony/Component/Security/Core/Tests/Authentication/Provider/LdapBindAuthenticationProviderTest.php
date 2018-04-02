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
use Symfony\Component\Ldap\LdapInterface;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Adapter\QueryInterface;
use Symfony\Component\Ldap\Adapter\CollectionInterface;
use Symfony\Component\Security\Core\Authentication\Provider\LdapBindAuthenticationProvider;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Ldap\Exception\ConnectionException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @requires extension ldap
 */
class LdapBindAuthenticationProviderTest extends TestCase
{
    /**
     * @expectedException        \Symfony\Component\Security\Core\Exception\BadCredentialsException
     * @expectedExceptionMessage The presented password must not be empty.
     */
    public function testEmptyPasswordShouldThrowAnException()
    {
        $userProvider = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserProviderInterface')->getMock();
        $ldap = $this->getMockBuilder(LdapInterface::class)->getMock();
        $userChecker = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserCheckerInterface')->getMock();

        $provider = new LdapBindAuthenticationProvider($userProvider, $userChecker, 'key', $ldap);
        $reflection = new \ReflectionMethod($provider, 'checkAuthentication');
        $reflection->setAccessible(true);

        $reflection->invoke($provider, new User('foo', null), new UsernamePasswordToken('foo', '', 'key'));
    }

    /**
     * @expectedException        \Symfony\Component\Security\Core\Exception\BadCredentialsException
     * @expectedExceptionMessage The presented password must not be empty.
     */
    public function testNullPasswordShouldThrowAnException()
    {
        $userProvider = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserProviderInterface')->getMock();
        $ldap = $this->getMockBuilder('Symfony\Component\Ldap\LdapInterface')->getMock();
        $userChecker = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserCheckerInterface')->getMock();

        $provider = new LdapBindAuthenticationProvider($userProvider, $userChecker, 'key', $ldap);
        $reflection = new \ReflectionMethod($provider, 'checkAuthentication');
        $reflection->setAccessible(true);

        $reflection->invoke($provider, new User('foo', null), new UsernamePasswordToken('foo', null, 'key'));
    }

    /**
     * @expectedException        \Symfony\Component\Security\Core\Exception\BadCredentialsException
     * @expectedExceptionMessage The presented password is invalid.
     */
    public function testBindFailureShouldThrowAnException()
    {
        $userProvider = $this->getMockBuilder(UserProviderInterface::class)->getMock();
        $ldap = $this->getMockBuilder(LdapInterface::class)->getMock();
        $ldap
            ->expects($this->once())
            ->method('bind')
            ->will($this->throwException(new ConnectionException()))
        ;
        $userChecker = $this->getMockBuilder(UserCheckerInterface::class)->getMock();

        $provider = new LdapBindAuthenticationProvider($userProvider, $userChecker, 'key', $ldap);
        $reflection = new \ReflectionMethod($provider, 'checkAuthentication');
        $reflection->setAccessible(true);

        $reflection->invoke($provider, new User('foo', null), new UsernamePasswordToken('foo', 'bar', 'key'));
    }

    public function testRetrieveUser()
    {
        $userProvider = $this->getMockBuilder(UserProviderInterface::class)->getMock();
        $userProvider
            ->expects($this->once())
            ->method('loadUserByUsername')
            ->with('foo')
        ;
        $ldap = $this->getMockBuilder(LdapInterface::class)->getMock();

        $userChecker = $this->getMockBuilder(UserCheckerInterface::class)->getMock();

        $provider = new LdapBindAuthenticationProvider($userProvider, $userChecker, 'key', $ldap);
        $reflection = new \ReflectionMethod($provider, 'retrieveUser');
        $reflection->setAccessible(true);

        $reflection->invoke($provider, 'foo', new UsernamePasswordToken('foo', 'bar', 'key'));
    }

    public function testQueryForDn()
    {
        $userProvider = $this->getMockBuilder(UserProviderInterface::class)->getMock();

        $collection = new \ArrayIterator(array(new Entry('')));

        $query = $this->getMockBuilder(QueryInterface::class)->getMock();
        $query
            ->expects($this->once())
            ->method('execute')
            ->will($this->returnValue($collection))
        ;

        $ldap = $this->getMockBuilder(LdapInterface::class)->getMock();
        $ldap
            ->expects($this->once())
            ->method('escape')
            ->with('foo', '')
            ->will($this->returnValue('foo'))
        ;
        $ldap
            ->expects($this->once())
            ->method('query')
            ->with('{username}', 'foobar')
            ->will($this->returnValue($query))
        ;
        $userChecker = $this->getMockBuilder(UserCheckerInterface::class)->getMock();

        $provider = new LdapBindAuthenticationProvider($userProvider, $userChecker, 'key', $ldap);
        $provider->setQueryString('{username}bar');
        $reflection = new \ReflectionMethod($provider, 'checkAuthentication');
        $reflection->setAccessible(true);

        $reflection->invoke($provider, new User('foo', null), new UsernamePasswordToken('foo', 'bar', 'key'));
    }

    /**
     * @expectedException        \Symfony\Component\Security\Core\Exception\BadCredentialsException
     * @expectedExceptionMessage The presented username is invalid.
     */
    public function testEmptyQueryResultShouldThrowAnException()
    {
        $userProvider = $this->getMockBuilder(UserProviderInterface::class)->getMock();

        $collection = $this->getMockBuilder(CollectionInterface::class)->getMock();

        $query = $this->getMockBuilder(QueryInterface::class)->getMock();
        $query
            ->expects($this->once())
            ->method('execute')
            ->will($this->returnValue($collection))
        ;

        $ldap = $this->getMockBuilder(LdapInterface::class)->getMock();
        $ldap
            ->expects($this->once())
            ->method('query')
            ->will($this->returnValue($query))
        ;
        $userChecker = $this->getMockBuilder(UserCheckerInterface::class)->getMock();

        $provider = new LdapBindAuthenticationProvider($userProvider, $userChecker, 'key', $ldap);
        $provider->setQueryString('{username}bar');
        $reflection = new \ReflectionMethod($provider, 'checkAuthentication');
        $reflection->setAccessible(true);

        $reflection->invoke($provider, new User('foo', null), new UsernamePasswordToken('foo', 'bar', 'key'));
    }
}
