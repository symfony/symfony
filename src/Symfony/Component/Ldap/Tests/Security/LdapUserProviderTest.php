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
use Symfony\Component\Ldap\Security\MemberOfRoles;
use Symfony\Component\Ldap\Security\RoleFetcherInterface;
use Symfony\Component\Security\Core\Exception\InvalidArgumentException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

/**
 * @requires extension ldap
 */
class LdapUserProviderTest extends TestCase
{
    public function testLoadUserByIdentifierFailsIfCantConnectToLdap()
    {
        $this->expectException(ConnectionException::class);

        $ldap = $this->createMock(LdapInterface::class);
        $ldap
            ->expects($this->once())
            ->method('bind')
            ->willThrowException(new ConnectionException())
        ;

        $provider = new LdapUserProvider($ldap, 'ou=MyBusiness,dc=symfony,dc=com');
        $provider->loadUserByIdentifier('foo');
    }

    public function testLoadUserByIdentifierFailsIfNoLdapEntries()
    {
        $this->expectException(UserNotFoundException::class);

        $result = $this->createMock(CollectionInterface::class);
        $query = $this->createMock(QueryInterface::class);
        $query
            ->expects($this->once())
            ->method('execute')
            ->willReturn($result)
        ;
        $result
            ->expects($this->once())
            ->method('count')
            ->willReturn(0)
        ;
        $ldap = $this->createMock(LdapInterface::class);
        $ldap
            ->expects($this->once())
            ->method('escape')
            ->willReturn('foo')
        ;
        $ldap
            ->expects($this->once())
            ->method('query')
            ->willReturn($query)
        ;

        $provider = new LdapUserProvider($ldap, 'ou=MyBusiness,dc=symfony,dc=com');
        $provider->loadUserByIdentifier('foo');
    }

    public function testLoadUserByIdentifierFailsIfMoreThanOneLdapEntry()
    {
        $this->expectException(UserNotFoundException::class);

        $result = $this->createMock(CollectionInterface::class);
        $query = $this->createMock(QueryInterface::class);
        $query
            ->expects($this->once())
            ->method('execute')
            ->willReturn($result)
        ;
        $result
            ->expects($this->once())
            ->method('count')
            ->willReturn(2)
        ;
        $ldap = $this->createMock(LdapInterface::class);
        $ldap
            ->expects($this->once())
            ->method('escape')
            ->willReturn('foo')
        ;
        $ldap
            ->expects($this->once())
            ->method('query')
            ->willReturn($query)
        ;

        $provider = new LdapUserProvider($ldap, 'ou=MyBusiness,dc=symfony,dc=com');
        $provider->loadUserByIdentifier('foo');
    }

    public function testLoadUserByIdentifierFailsIfMoreThanOneLdapPasswordsInEntry()
    {
        $this->expectException(InvalidArgumentException::class);

        $result = $this->createMock(CollectionInterface::class);
        $query = $this->createMock(QueryInterface::class);
        $query
            ->expects($this->once())
            ->method('execute')
            ->willReturn($result)
        ;
        $ldap = $this->createMock(LdapInterface::class);
        $result
            ->expects($this->once())
            ->method('offsetGet')
            ->with(0)
            ->willReturn(new Entry('foo', [
                'sAMAccountName' => ['foo'],
                'userpassword' => ['bar', 'baz'],
            ]))
        ;
        $result
            ->expects($this->once())
            ->method('count')
            ->willReturn(1)
        ;
        $ldap
            ->expects($this->once())
            ->method('escape')
            ->willReturn('foo')
        ;
        $ldap
            ->expects($this->once())
            ->method('query')
            ->willReturn($query)
        ;

        $provider = new LdapUserProvider($ldap, 'ou=MyBusiness,dc=symfony,dc=com', null, null, [], 'sAMAccountName', '({uid_key}={user_identifier})', 'userpassword');
        $this->assertInstanceOf(LdapUser::class, $provider->loadUserByIdentifier('foo'));
    }

    public function testLoadUserByIdentifierShouldNotFailIfEntryHasNoUidKeyAttribute()
    {
        $result = $this->createMock(CollectionInterface::class);
        $query = $this->createMock(QueryInterface::class);
        $query
            ->expects($this->once())
            ->method('execute')
            ->willReturn($result)
        ;
        $ldap = $this->createMock(LdapInterface::class);
        $result
            ->expects($this->once())
            ->method('offsetGet')
            ->with(0)
            ->willReturn(new Entry('foo', []))
        ;
        $result
            ->expects($this->once())
            ->method('count')
            ->willReturn(1)
        ;
        $ldap
            ->expects($this->once())
            ->method('escape')
            ->willReturn('foo')
        ;
        $ldap
            ->expects($this->once())
            ->method('query')
            ->willReturn($query)
        ;

        $provider = new LdapUserProvider($ldap, 'ou=MyBusiness,dc=symfony,dc=com', null, null, [], 'sAMAccountName', '({uid_key}={user_identifier})');
        $this->assertInstanceOf(LdapUser::class, $provider->loadUserByIdentifier('foo'));
    }

    public function testLoadUserByIdentifierFailsIfEntryHasNoPasswordAttribute()
    {
        $this->expectException(InvalidArgumentException::class);

        $result = $this->createMock(CollectionInterface::class);
        $query = $this->createMock(QueryInterface::class);
        $query
            ->expects($this->once())
            ->method('execute')
            ->willReturn($result)
        ;
        $ldap = $this->createMock(LdapInterface::class);
        $result
            ->expects($this->once())
            ->method('offsetGet')
            ->with(0)
            ->willReturn(new Entry('foo', ['sAMAccountName' => ['foo']]))
        ;
        $result
            ->expects($this->once())
            ->method('count')
            ->willReturn(1)
        ;
        $ldap
            ->expects($this->once())
            ->method('escape')
            ->willReturn('foo')
        ;
        $ldap
            ->expects($this->once())
            ->method('query')
            ->willReturn($query)
        ;

        $provider = new LdapUserProvider($ldap, 'ou=MyBusiness,dc=symfony,dc=com', null, null, [], 'sAMAccountName', '({uid_key}={user_identifier})', 'userpassword');
        $this->assertInstanceOf(LdapUser::class, $provider->loadUserByIdentifier('foo'));
    }

    public function testLoadUserByIdentifierIsSuccessfulWithoutPasswordAttribute()
    {
        $result = $this->createMock(CollectionInterface::class);
        $query = $this->createMock(QueryInterface::class);
        $query
            ->expects($this->once())
            ->method('execute')
            ->willReturn($result)
        ;
        $ldap = $this->createMock(LdapInterface::class);
        $result
            ->expects($this->once())
            ->method('offsetGet')
            ->with(0)
            ->willReturn(new Entry('foo', ['sAMAccountName' => ['foo']]))
        ;
        $result
            ->expects($this->once())
            ->method('count')
            ->willReturn(1)
        ;
        $ldap
            ->expects($this->once())
            ->method('escape')
            ->willReturn('foo')
        ;
        $ldap
            ->expects($this->once())
            ->method('query')
            ->willReturn($query)
        ;

        $provider = new LdapUserProvider($ldap, 'ou=MyBusiness,dc=symfony,dc=com');
        $this->assertInstanceOf(LdapUser::class, $provider->loadUserByIdentifier('foo'));
    }

    public function testLoadUserByIdentifierIsSuccessfulWithoutPasswordAttributeAndWrongCase()
    {
        $result = $this->createMock(CollectionInterface::class);
        $query = $this->createMock(QueryInterface::class);
        $query
            ->expects($this->once())
            ->method('execute')
            ->willReturn($result)
        ;
        $ldap = $this->createMock(LdapInterface::class);
        $result
            ->expects($this->once())
            ->method('offsetGet')
            ->with(0)
            ->willReturn(new Entry('foo', ['sAMAccountName' => ['foo']]))
        ;
        $result
            ->expects($this->once())
            ->method('count')
            ->willReturn(1)
        ;
        $ldap
            ->expects($this->once())
            ->method('escape')
            ->willReturn('Foo')
        ;
        $ldap
            ->expects($this->once())
            ->method('query')
            ->willReturn($query)
        ;

        $provider = new LdapUserProvider($ldap, 'ou=MyBusiness,dc=symfony,dc=com');
        $this->assertSame('foo', $provider->loadUserByIdentifier('Foo')->getUserIdentifier());
    }

    public function testLoadUserByIdentifierIsSuccessfulWithPasswordAttribute()
    {
        $result = $this->createMock(CollectionInterface::class);
        $query = $this->createMock(QueryInterface::class);
        $query
            ->expects($this->once())
            ->method('execute')
            ->willReturn($result)
        ;
        $ldap = $this->createMock(LdapInterface::class);
        $result
            ->expects($this->once())
            ->method('offsetGet')
            ->with(0)
            ->willReturn(new Entry('foo', [
                'sAMAccountName' => ['foo'],
                'userpassword' => ['bar'],
                'email' => ['elsa@symfony.com'],
            ]))
        ;
        $result
            ->expects($this->once())
            ->method('count')
            ->willReturn(1)
        ;
        $ldap
            ->expects($this->once())
            ->method('escape')
            ->willReturn('foo')
        ;
        $ldap
            ->expects($this->once())
            ->method('query')
            ->willReturn($query)
        ;

        $provider = new LdapUserProvider($ldap, 'ou=MyBusiness,dc=symfony,dc=com', null, null, [], 'sAMAccountName', '({uid_key}={user_identifier})', 'userpassword', ['email']);
        $this->assertInstanceOf(LdapUser::class, $provider->loadUserByIdentifier('foo'));
    }

    public function testLoadUserByIdentifierIsSuccessfulWithMultipleExtraAttributes()
    {
        $result = $this->createMock(CollectionInterface::class);
        $query = $this->createMock(QueryInterface::class);
        $query
            ->expects($this->once())
            ->method('execute')
            ->willReturn($result)
        ;
        $ldap = $this->createMock(LdapInterface::class);
        $memberOf = [
            'cn=foo,ou=MyBusiness,dc=symfony,dc=com',
            'cn=bar,ou=MyBusiness,dc=symfony,dc=com',
        ];
        $result
            ->expects($this->once())
            ->method('offsetGet')
            ->with(0)
            ->willReturn(new Entry('foo', [
                'sAMAccountName' => ['foo'],
                'userpassword' => ['bar'],
                'memberOf' => $memberOf,
            ]))
        ;
        $result
            ->expects($this->once())
            ->method('count')
            ->willReturn(1)
        ;
        $ldap
            ->expects($this->once())
            ->method('escape')
            ->willReturn('foo')
        ;
        $ldap
            ->expects($this->once())
            ->method('query')
            ->willReturn($query)
        ;

        $provider = new LdapUserProvider($ldap, 'ou=MyBusiness,dc=symfony,dc=com', null, null, [], 'sAMAccountName', '({uid_key}={user_identifier})', 'userpassword', ['memberOf']);
        $user = $provider->loadUserByIdentifier('foo');
        $this->assertInstanceOf(LdapUser::class, $user);
        $this->assertSame(['memberOf' => $memberOf], $user->getExtraFields());
    }

    public function testRefreshUserShouldReturnUserWithSameProperties()
    {
        $ldap = $this->createMock(LdapInterface::class);
        $provider = new LdapUserProvider($ldap, 'ou=MyBusiness,dc=symfony,dc=com', null, null, [], 'sAMAccountName', '({uid_key}={user_identifier})', 'userpassword', ['email']);

        $user = new LdapUser(new Entry('foo'), 'foo', 'bar', ['ROLE_DUMMY'], ['email' => 'foo@symfony.com']);

        $this->assertEquals($user, $provider->refreshUser($user));
    }

    public function testLoadUserWithCorrectRoles()
    {
        // Given
        $result = $this->createMock(CollectionInterface::class);
        $query = $this->createMock(QueryInterface::class);
        $query
            ->method('execute')
            ->willReturn($result)
        ;
        $ldap = $this->createMock(LdapInterface::class);
        $result
            ->method('offsetGet')
            ->with(0)
            ->willReturn(new Entry('foo', ['sAMAccountName' => ['foo']]))
        ;
        $result
            ->method('count')
            ->willReturn(1)
        ;
        $ldap
            ->method('escape')
            ->willReturn('foo')
        ;
        $ldap
            ->method('query')
            ->willReturn($query)
        ;
        $roleFetcher = $this->createMock(RoleFetcherInterface::class);
        $roleFetcher
            ->method('fetchRoles')
            ->willReturn(['ROLE_FOO', 'ROLE_BAR'])
        ;

        $provider = new LdapUserProvider($ldap, 'ou=MyBusiness,dc=symfony,dc=com', roleFetcher: $roleFetcher);

        // When
        $user = $provider->loadUserByIdentifier('foo');

        // Then
        $this->assertInstanceOf(LdapUser::class, $user);
        $this->assertSame(['ROLE_FOO', 'ROLE_BAR'], $user->getRoles());
    }

    public function testMemberOfRoleFetch()
    {
        // Given
        $roleFetcher = new MemberOfRoles(
            ['Staff' => 'ROLE_STAFF', 'Admin' => 'ROLE_ADMIN'],
            'memberOf'
        );

        $entry = new Entry('uid=elliot.alderson,ou=staff,ou=people,dc=example,dc=com', ['memberOf' => ['cn=Staff,ou=Groups,dc=example,dc=com', 'cn=Admin,ou=Groups,dc=example,dc=com']]);

        // When
        $roles = $roleFetcher->fetchRoles($entry);

        // Then
        $this->assertSame(['ROLE_STAFF', 'ROLE_ADMIN'], $roles);
    }
}
