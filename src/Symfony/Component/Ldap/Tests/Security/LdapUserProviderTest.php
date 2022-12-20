<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Ldap\Tests\Security;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Ldap\Adapter\CollectionInterface;
use Symfony\Component\Ldap\Adapter\QueryInterface;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Exception\ConnectionException;
use Symfony\Component\Ldap\LdapInterface;
use Symfony\Component\Ldap\Security\LdapUser;
use Symfony\Component\Ldap\Security\LdapUserProvider;
use Symfony\Component\Security\Core\Exception\InvalidArgumentException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

/**
 * @requires extension ldap
 */
class LdapUserProviderTest extends TestCase
{
    public function testLoadUserByUsernameFailsIfCantConnectToLdap()
    {
        self::expectException(UserNotFoundException::class);

        $ldap = self::createMock(LdapInterface::class);
        $ldap
            ->expects(self::once())
            ->method('bind')
            ->willThrowException(new ConnectionException())
        ;

        $provider = new LdapUserProvider($ldap, 'ou=MyBusiness,dc=symfony,dc=com');
        $provider->loadUserByIdentifier('foo');
    }

    public function testLoadUserByUsernameFailsIfNoLdapEntries()
    {
        self::expectException(UserNotFoundException::class);

        $result = self::createMock(CollectionInterface::class);
        $query = self::createMock(QueryInterface::class);
        $query
            ->expects(self::once())
            ->method('execute')
            ->willReturn($result)
        ;
        $result
            ->expects(self::once())
            ->method('count')
            ->willReturn(0)
        ;
        $ldap = self::createMock(LdapInterface::class);
        $ldap
            ->expects(self::once())
            ->method('escape')
            ->willReturn('foo')
        ;
        $ldap
            ->expects(self::once())
            ->method('query')
            ->willReturn($query)
        ;

        $provider = new LdapUserProvider($ldap, 'ou=MyBusiness,dc=symfony,dc=com');
        $provider->loadUserByIdentifier('foo');
    }

    public function testLoadUserByUsernameFailsIfMoreThanOneLdapEntry()
    {
        self::expectException(UserNotFoundException::class);

        $result = self::createMock(CollectionInterface::class);
        $query = self::createMock(QueryInterface::class);
        $query
            ->expects(self::once())
            ->method('execute')
            ->willReturn($result)
        ;
        $result
            ->expects(self::once())
            ->method('count')
            ->willReturn(2)
        ;
        $ldap = self::createMock(LdapInterface::class);
        $ldap
            ->expects(self::once())
            ->method('escape')
            ->willReturn('foo')
        ;
        $ldap
            ->expects(self::once())
            ->method('query')
            ->willReturn($query)
        ;

        $provider = new LdapUserProvider($ldap, 'ou=MyBusiness,dc=symfony,dc=com');
        $provider->loadUserByIdentifier('foo');
    }

    public function testLoadUserByUsernameFailsIfMoreThanOneLdapPasswordsInEntry()
    {
        self::expectException(InvalidArgumentException::class);

        $result = self::createMock(CollectionInterface::class);
        $query = self::createMock(QueryInterface::class);
        $query
            ->expects(self::once())
            ->method('execute')
            ->willReturn($result)
        ;
        $ldap = self::createMock(LdapInterface::class);
        $result
            ->expects(self::once())
            ->method('offsetGet')
            ->with(0)
            ->willReturn(new Entry('foo', [
                    'sAMAccountName' => ['foo'],
                    'userpassword' => ['bar', 'baz'],
            ]))
        ;
        $result
            ->expects(self::once())
            ->method('count')
            ->willReturn(1)
        ;
        $ldap
            ->expects(self::once())
            ->method('escape')
            ->willReturn('foo')
        ;
        $ldap
            ->expects(self::once())
            ->method('query')
            ->willReturn($query)
        ;

        $provider = new LdapUserProvider($ldap, 'ou=MyBusiness,dc=symfony,dc=com', null, null, [], 'sAMAccountName', '({uid_key}={username})', 'userpassword');
        self::assertInstanceOf(LdapUser::class, $provider->loadUserByIdentifier('foo'));
    }

    public function testLoadUserByUsernameShouldNotFailIfEntryHasNoUidKeyAttribute()
    {
        $result = self::createMock(CollectionInterface::class);
        $query = self::createMock(QueryInterface::class);
        $query
            ->expects(self::once())
            ->method('execute')
            ->willReturn($result)
        ;
        $ldap = self::createMock(LdapInterface::class);
        $result
            ->expects(self::once())
            ->method('offsetGet')
            ->with(0)
            ->willReturn(new Entry('foo', []))
        ;
        $result
            ->expects(self::once())
            ->method('count')
            ->willReturn(1)
        ;
        $ldap
            ->expects(self::once())
            ->method('escape')
            ->willReturn('foo')
        ;
        $ldap
            ->expects(self::once())
            ->method('query')
            ->willReturn($query)
        ;

        $provider = new LdapUserProvider($ldap, 'ou=MyBusiness,dc=symfony,dc=com', null, null, [], 'sAMAccountName', '({uid_key}={username})');
        self::assertInstanceOf(LdapUser::class, $provider->loadUserByIdentifier('foo'));
    }

    public function testLoadUserByUsernameFailsIfEntryHasNoPasswordAttribute()
    {
        self::expectException(InvalidArgumentException::class);

        $result = self::createMock(CollectionInterface::class);
        $query = self::createMock(QueryInterface::class);
        $query
            ->expects(self::once())
            ->method('execute')
            ->willReturn($result)
        ;
        $ldap = self::createMock(LdapInterface::class);
        $result
            ->expects(self::once())
            ->method('offsetGet')
            ->with(0)
            ->willReturn(new Entry('foo', ['sAMAccountName' => ['foo']]))
        ;
        $result
            ->expects(self::once())
            ->method('count')
            ->willReturn(1)
        ;
        $ldap
            ->expects(self::once())
            ->method('escape')
            ->willReturn('foo')
        ;
        $ldap
            ->expects(self::once())
            ->method('query')
            ->willReturn($query)
        ;

        $provider = new LdapUserProvider($ldap, 'ou=MyBusiness,dc=symfony,dc=com', null, null, [], 'sAMAccountName', '({uid_key}={username})', 'userpassword');
        self::assertInstanceOf(LdapUser::class, $provider->loadUserByIdentifier('foo'));
    }

    public function testLoadUserByUsernameIsSuccessfulWithoutPasswordAttribute()
    {
        $result = self::createMock(CollectionInterface::class);
        $query = self::createMock(QueryInterface::class);
        $query
            ->expects(self::once())
            ->method('execute')
            ->willReturn($result)
        ;
        $ldap = self::createMock(LdapInterface::class);
        $result
            ->expects(self::once())
            ->method('offsetGet')
            ->with(0)
            ->willReturn(new Entry('foo', ['sAMAccountName' => ['foo']]))
        ;
        $result
            ->expects(self::once())
            ->method('count')
            ->willReturn(1)
        ;
        $ldap
            ->expects(self::once())
            ->method('escape')
            ->willReturn('foo')
        ;
        $ldap
            ->expects(self::once())
            ->method('query')
            ->willReturn($query)
        ;

        $provider = new LdapUserProvider($ldap, 'ou=MyBusiness,dc=symfony,dc=com');
        self::assertInstanceOf(LdapUser::class, $provider->loadUserByIdentifier('foo'));
    }

    public function testLoadUserByUsernameIsSuccessfulWithoutPasswordAttributeAndWrongCase()
    {
        $result = self::createMock(CollectionInterface::class);
        $query = self::createMock(QueryInterface::class);
        $query
            ->expects(self::once())
            ->method('execute')
            ->willReturn($result)
        ;
        $ldap = self::createMock(LdapInterface::class);
        $result
            ->expects(self::once())
            ->method('offsetGet')
            ->with(0)
            ->willReturn(new Entry('foo', ['sAMAccountName' => ['foo']]))
        ;
        $result
            ->expects(self::once())
            ->method('count')
            ->willReturn(1)
        ;
        $ldap
            ->expects(self::once())
            ->method('escape')
            ->willReturn('Foo')
        ;
        $ldap
            ->expects(self::once())
            ->method('query')
            ->willReturn($query)
        ;

        $provider = new LdapUserProvider($ldap, 'ou=MyBusiness,dc=symfony,dc=com');
        self::assertSame('foo', $provider->loadUserByIdentifier('Foo')->getUserIdentifier());
    }

    public function testLoadUserByUsernameIsSuccessfulWithPasswordAttribute()
    {
        $result = self::createMock(CollectionInterface::class);
        $query = self::createMock(QueryInterface::class);
        $query
            ->expects(self::once())
            ->method('execute')
            ->willReturn($result)
        ;
        $ldap = self::createMock(LdapInterface::class);
        $result
            ->expects(self::once())
            ->method('offsetGet')
            ->with(0)
            ->willReturn(new Entry('foo', [
                    'sAMAccountName' => ['foo'],
                    'userpassword' => ['bar'],
                    'email' => ['elsa@symfony.com'],
            ]))
        ;
        $result
            ->expects(self::once())
            ->method('count')
            ->willReturn(1)
        ;
        $ldap
            ->expects(self::once())
            ->method('escape')
            ->willReturn('foo')
        ;
        $ldap
            ->expects(self::once())
            ->method('query')
            ->willReturn($query)
        ;

        $provider = new LdapUserProvider($ldap, 'ou=MyBusiness,dc=symfony,dc=com', null, null, [], 'sAMAccountName', '({uid_key}={username})', 'userpassword', ['email']);
        self::assertInstanceOf(LdapUser::class, $provider->loadUserByIdentifier('foo'));
    }

    public function testRefreshUserShouldReturnUserWithSameProperties()
    {
        $ldap = self::createMock(LdapInterface::class);
        $provider = new LdapUserProvider($ldap, 'ou=MyBusiness,dc=symfony,dc=com', null, null, [], 'sAMAccountName', '({uid_key}={username})', 'userpassword', ['email']);

        $user = new LdapUser(new Entry('foo'), 'foo', 'bar', ['ROLE_DUMMY'], ['email' => 'foo@symfony.com']);

        self::assertEquals($user, $provider->refreshUser($user));
    }
}
