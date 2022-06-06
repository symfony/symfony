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
use Symfony\Component\Ldap\Adapter\CollectionInterface;
use Symfony\Component\Ldap\Adapter\QueryInterface;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Exception\ConnectionException;
use Symfony\Component\Ldap\LdapInterface;
use Symfony\Component\Security\Core\Authentication\Provider\LdapBindAuthenticationProvider;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\InMemoryUserProvider;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @requires extension ldap
 * @group legacy
 */
class LdapBindAuthenticationProviderTest extends TestCase
{
    public function testEmptyPasswordShouldThrowAnException()
    {
        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('The presented password must not be empty.');
        $userProvider = $this->createMock(UserProviderInterface::class);
        $ldap = $this->createMock(LdapInterface::class);
        $userChecker = $this->createMock(UserCheckerInterface::class);

        $provider = new LdapBindAuthenticationProvider($userProvider, $userChecker, 'key', $ldap);
        $reflection = new \ReflectionMethod($provider, 'checkAuthentication');
        $reflection->setAccessible(true);

        $reflection->invoke($provider, new InMemoryUser('foo', null), new UsernamePasswordToken('foo', '', 'key'));
    }

    public function testNullPasswordShouldThrowAnException()
    {
        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('The presented password must not be empty.');
        $userProvider = $this->createMock(UserProviderInterface::class);
        $ldap = $this->createMock(LdapInterface::class);
        $userChecker = $this->createMock(UserCheckerInterface::class);

        $provider = new LdapBindAuthenticationProvider($userProvider, $userChecker, 'key', $ldap);
        $reflection = new \ReflectionMethod($provider, 'checkAuthentication');
        $reflection->setAccessible(true);

        $reflection->invoke($provider, new InMemoryUser('foo', null), new UsernamePasswordToken('foo', null, 'key'));
    }

    public function testBindFailureShouldThrowAnException()
    {
        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('The presented password is invalid.');
        $userProvider = $this->createMock(UserProviderInterface::class);
        $ldap = $this->createMock(LdapInterface::class);
        $ldap
            ->expects($this->once())
            ->method('bind')
            ->willThrowException(new ConnectionException())
        ;
        $ldap->method('escape')->willReturnArgument(0);
        $userChecker = $this->createMock(UserCheckerInterface::class);

        $provider = new LdapBindAuthenticationProvider($userProvider, $userChecker, 'key', $ldap);
        $reflection = new \ReflectionMethod($provider, 'checkAuthentication');
        $reflection->setAccessible(true);

        $reflection->invoke($provider, new InMemoryUser('foo', null), new UsernamePasswordToken('foo', 'bar', 'key'));
    }

    public function testRetrieveUser()
    {
        $userProvider = $this->createMock(InMemoryUserProvider::class);
        $userProvider
            ->expects($this->once())
            ->method('loadUserByIdentifier')
            ->with('foo')
        ;
        $ldap = $this->createMock(LdapInterface::class);

        $userChecker = $this->createMock(UserCheckerInterface::class);

        $provider = new LdapBindAuthenticationProvider($userProvider, $userChecker, 'key', $ldap);
        $reflection = new \ReflectionMethod($provider, 'retrieveUser');
        $reflection->setAccessible(true);

        $reflection->invoke($provider, 'foo', new UsernamePasswordToken('foo', 'bar', 'key'));
    }

    public function testQueryForDn()
    {
        $userProvider = $this->createMock(UserProviderInterface::class);

        $collection = new class([new Entry('')]) extends \ArrayObject implements CollectionInterface {
            public function toArray(): array
            {
                return $this->getArrayCopy();
            }
        };

        $query = $this->createMock(QueryInterface::class);
        $query
            ->expects($this->once())
            ->method('execute')
            ->willReturn($collection)
        ;

        $ldap = $this->createMock(LdapInterface::class);
        $ldap
            ->method('bind')
            ->withConsecutive(
                ['elsa', 'test1234A$']
            );
        $ldap
            ->expects($this->once())
            ->method('escape')
            ->with('foo', '')
            ->willReturn('foo')
        ;
        $ldap
            ->expects($this->once())
            ->method('query')
            ->with('{username}', 'foobar')
            ->willReturn($query)
        ;
        $userChecker = $this->createMock(UserCheckerInterface::class);

        $provider = new LdapBindAuthenticationProvider($userProvider, $userChecker, 'key', $ldap, '{username}', true, 'elsa', 'test1234A$');
        $provider->setQueryString('{username}bar');
        $reflection = new \ReflectionMethod($provider, 'checkAuthentication');
        $reflection->setAccessible(true);

        $reflection->invoke($provider, new InMemoryUser('foo', null), new UsernamePasswordToken('foo', 'bar', 'key'));
    }

    public function testQueryWithUserForDn()
    {
        $userProvider = $this->createMock(UserProviderInterface::class);

        $collection = new class([new Entry('')]) extends \ArrayObject implements CollectionInterface {
            public function toArray(): array
            {
                return $this->getArrayCopy();
            }
        };

        $query = $this->createMock(QueryInterface::class);
        $query
            ->expects($this->once())
            ->method('execute')
            ->willReturn($collection)
        ;

        $ldap = $this->createMock(LdapInterface::class);
        $ldap
            ->method('bind')
            ->withConsecutive(
                ['elsa', 'test1234A$']
            );
        $ldap
            ->expects($this->once())
            ->method('escape')
            ->with('foo', '')
            ->willReturn('foo')
        ;
        $ldap
            ->expects($this->once())
            ->method('query')
            ->with('{username}', 'foobar')
            ->willReturn($query)
        ;

        $userChecker = $this->createMock(UserCheckerInterface::class);

        $provider = new LdapBindAuthenticationProvider($userProvider, $userChecker, 'key', $ldap, '{username}', true, 'elsa', 'test1234A$');
        $provider->setQueryString('{username}bar');
        $reflection = new \ReflectionMethod($provider, 'checkAuthentication');
        $reflection->setAccessible(true);

        $reflection->invoke($provider, new InMemoryUser('foo', null), new UsernamePasswordToken('foo', 'bar', 'key'));
    }

    public function testEmptyQueryResultShouldThrowAnException()
    {
        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('The presented username is invalid.');
        $userProvider = $this->createMock(UserProviderInterface::class);

        $collection = $this->createMock(CollectionInterface::class);

        $query = $this->createMock(QueryInterface::class);
        $query
            ->expects($this->once())
            ->method('execute')
            ->willReturn($collection)
        ;

        $ldap = $this->createMock(LdapInterface::class);
        $ldap
            ->method('bind')
            ->withConsecutive(
                ['elsa', 'test1234A$']
            );
        $ldap
            ->expects($this->once())
            ->method('query')
            ->willReturn($query)
        ;
        $ldap->method('escape')->willReturnArgument(0);
        $userChecker = $this->createMock(UserCheckerInterface::class);

        $provider = new LdapBindAuthenticationProvider($userProvider, $userChecker, 'key', $ldap, '{username}', true, 'elsa', 'test1234A$');
        $provider->setQueryString('{username}bar');
        $reflection = new \ReflectionMethod($provider, 'checkAuthentication');
        $reflection->setAccessible(true);

        $reflection->invoke($provider, new InMemoryUser('foo', null), new UsernamePasswordToken('foo', 'bar', 'key'));
    }
}
