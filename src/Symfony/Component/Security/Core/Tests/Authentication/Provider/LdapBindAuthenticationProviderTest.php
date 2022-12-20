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
        self::expectException(BadCredentialsException::class);
        self::expectExceptionMessage('The presented password must not be empty.');
        $userProvider = self::createMock(UserProviderInterface::class);
        $ldap = self::createMock(LdapInterface::class);
        $userChecker = self::createMock(UserCheckerInterface::class);

        $provider = new LdapBindAuthenticationProvider($userProvider, $userChecker, 'key', $ldap);
        $reflection = new \ReflectionMethod($provider, 'checkAuthentication');
        $reflection->setAccessible(true);

        $reflection->invoke($provider, new InMemoryUser('foo', null), new UsernamePasswordToken('foo', '', 'key'));
    }

    public function testNullPasswordShouldThrowAnException()
    {
        self::expectException(BadCredentialsException::class);
        self::expectExceptionMessage('The presented password must not be empty.');
        $userProvider = self::createMock(UserProviderInterface::class);
        $ldap = self::createMock(LdapInterface::class);
        $userChecker = self::createMock(UserCheckerInterface::class);

        $provider = new LdapBindAuthenticationProvider($userProvider, $userChecker, 'key', $ldap);
        $reflection = new \ReflectionMethod($provider, 'checkAuthentication');
        $reflection->setAccessible(true);

        $reflection->invoke($provider, new InMemoryUser('foo', null), new UsernamePasswordToken('foo', null, 'key'));
    }

    public function testBindFailureShouldThrowAnException()
    {
        self::expectException(BadCredentialsException::class);
        self::expectExceptionMessage('The presented password is invalid.');
        $userProvider = self::createMock(UserProviderInterface::class);
        $ldap = self::createMock(LdapInterface::class);
        $ldap
            ->expects(self::once())
            ->method('bind')
            ->willThrowException(new ConnectionException())
        ;
        $ldap->method('escape')->willReturnArgument(0);
        $userChecker = self::createMock(UserCheckerInterface::class);

        $provider = new LdapBindAuthenticationProvider($userProvider, $userChecker, 'key', $ldap);
        $reflection = new \ReflectionMethod($provider, 'checkAuthentication');
        $reflection->setAccessible(true);

        $reflection->invoke($provider, new InMemoryUser('foo', null), new UsernamePasswordToken('foo', 'bar', 'key'));
    }

    public function testRetrieveUser()
    {
        $userProvider = self::createMock(InMemoryUserProvider::class);
        $userProvider
            ->expects(self::once())
            ->method('loadUserByIdentifier')
            ->with('foo')
        ;
        $ldap = self::createMock(LdapInterface::class);

        $userChecker = self::createMock(UserCheckerInterface::class);

        $provider = new LdapBindAuthenticationProvider($userProvider, $userChecker, 'key', $ldap);
        $reflection = new \ReflectionMethod($provider, 'retrieveUser');
        $reflection->setAccessible(true);

        $reflection->invoke($provider, 'foo', new UsernamePasswordToken('foo', 'bar', 'key'));
    }

    public function testQueryForDn()
    {
        $userProvider = self::createMock(UserProviderInterface::class);

        $collection = new class([new Entry('')]) extends \ArrayObject implements CollectionInterface {
            public function toArray(): array
            {
                return $this->getArrayCopy();
            }
        };

        $query = self::createMock(QueryInterface::class);
        $query
            ->expects(self::once())
            ->method('execute')
            ->willReturn($collection)
        ;

        $ldap = self::createMock(LdapInterface::class);
        $ldap
            ->method('bind')
            ->withConsecutive(
                ['elsa', 'test1234A$']
            );
        $ldap
            ->expects(self::once())
            ->method('escape')
            ->with('foo', '')
            ->willReturn('foo')
        ;
        $ldap
            ->expects(self::once())
            ->method('query')
            ->with('{username}', 'foobar')
            ->willReturn($query)
        ;
        $userChecker = self::createMock(UserCheckerInterface::class);

        $provider = new LdapBindAuthenticationProvider($userProvider, $userChecker, 'key', $ldap, '{username}', true, 'elsa', 'test1234A$');
        $provider->setQueryString('{username}bar');
        $reflection = new \ReflectionMethod($provider, 'checkAuthentication');
        $reflection->setAccessible(true);

        $reflection->invoke($provider, new InMemoryUser('foo', null), new UsernamePasswordToken('foo', 'bar', 'key'));
    }

    public function testQueryWithUserForDn()
    {
        $userProvider = self::createMock(UserProviderInterface::class);

        $collection = new class([new Entry('')]) extends \ArrayObject implements CollectionInterface {
            public function toArray(): array
            {
                return $this->getArrayCopy();
            }
        };

        $query = self::createMock(QueryInterface::class);
        $query
            ->expects(self::once())
            ->method('execute')
            ->willReturn($collection)
        ;

        $ldap = self::createMock(LdapInterface::class);
        $ldap
            ->method('bind')
            ->withConsecutive(
                ['elsa', 'test1234A$']
            );
        $ldap
            ->expects(self::once())
            ->method('escape')
            ->with('foo', '')
            ->willReturn('foo')
        ;
        $ldap
            ->expects(self::once())
            ->method('query')
            ->with('{username}', 'foobar')
            ->willReturn($query)
        ;

        $userChecker = self::createMock(UserCheckerInterface::class);

        $provider = new LdapBindAuthenticationProvider($userProvider, $userChecker, 'key', $ldap, '{username}', true, 'elsa', 'test1234A$');
        $provider->setQueryString('{username}bar');
        $reflection = new \ReflectionMethod($provider, 'checkAuthentication');
        $reflection->setAccessible(true);

        $reflection->invoke($provider, new InMemoryUser('foo', null), new UsernamePasswordToken('foo', 'bar', 'key'));
    }

    public function testEmptyQueryResultShouldThrowAnException()
    {
        self::expectException(BadCredentialsException::class);
        self::expectExceptionMessage('The presented username is invalid.');
        $userProvider = self::createMock(UserProviderInterface::class);

        $collection = self::createMock(CollectionInterface::class);

        $query = self::createMock(QueryInterface::class);
        $query
            ->expects(self::once())
            ->method('execute')
            ->willReturn($collection)
        ;

        $ldap = self::createMock(LdapInterface::class);
        $ldap
            ->method('bind')
            ->withConsecutive(
                ['elsa', 'test1234A$']
            );
        $ldap
            ->expects(self::once())
            ->method('query')
            ->willReturn($query)
        ;
        $ldap->method('escape')->willReturnArgument(0);
        $userChecker = self::createMock(UserCheckerInterface::class);

        $provider = new LdapBindAuthenticationProvider($userProvider, $userChecker, 'key', $ldap, '{username}', true, 'elsa', 'test1234A$');
        $provider->setQueryString('{username}bar');
        $reflection = new \ReflectionMethod($provider, 'checkAuthentication');
        $reflection->setAccessible(true);

        $reflection->invoke($provider, new InMemoryUser('foo', null), new UsernamePasswordToken('foo', 'bar', 'key'));
    }
}
