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

/**
 * @requires extension ldap
 */
class LdapUserProviderTest extends TestCase
{
    public function testLoadUserByUsernameFailsIfCantConnectToLdap()
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\UsernameNotFoundException::class);

        $ldap = $this->createMock(LdapInterface::class);
        $ldap
            ->expects($this->once())
            ->method('bind')
            ->willThrowException(new ConnectionException())
        ;

        $provider = new LdapUserProvider($ldap, 'ou=MyBusiness,dc=symfony,dc=com');
        $provider->loadUserByUsername('foo');
    }

    public function testLoadUserByUsernameFailsIfNoLdapEntries()
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\UsernameNotFoundException::class);

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
        $provider->loadUserByUsername('foo');
    }

    public function testLoadUserByUsernameFailsIfMoreThanOneLdapEntry()
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\UsernameNotFoundException::class);

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
        $provider->loadUserByUsername('foo');
    }

    public function testLoadUserByUsernameFailsIfMoreThanOneLdapPasswordsInEntry()
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\InvalidArgumentException::class);

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

        $provider = new LdapUserProvider($ldap, 'ou=MyBusiness,dc=symfony,dc=com', null, null, [], 'sAMAccountName', '({uid_key}={username})', 'userpassword');
        $this->assertInstanceOf(LdapUser::class, $provider->loadUserByUsername('foo'));
    }

    public function testLoadUserByUsernameShouldNotFailIfEntryHasNoUidKeyAttribute()
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

        $provider = new LdapUserProvider($ldap, 'ou=MyBusiness,dc=symfony,dc=com', null, null, [], 'sAMAccountName', '({uid_key}={username})');
        $this->assertInstanceOf(LdapUser::class, $provider->loadUserByUsername('foo'));
    }

    public function testLoadUserByUsernameFailsIfEntryHasNoPasswordAttribute()
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\InvalidArgumentException::class);

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

        $provider = new LdapUserProvider($ldap, 'ou=MyBusiness,dc=symfony,dc=com', null, null, [], 'sAMAccountName', '({uid_key}={username})', 'userpassword');
        $this->assertInstanceOf(LdapUser::class, $provider->loadUserByUsername('foo'));
    }

    public function testLoadUserByUsernameIsSuccessfulWithoutPasswordAttribute()
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
        $this->assertInstanceOf(LdapUser::class, $provider->loadUserByUsername('foo'));
    }

    public function testLoadUserByUsernameIsSuccessfulWithoutPasswordAttributeAndWrongCase()
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
        $this->assertSame('foo', $provider->loadUserByUsername('Foo')->getUsername());
    }

    public function testLoadUserByUsernameIsSuccessfulWithPasswordAttribute()
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

        $provider = new LdapUserProvider($ldap, 'ou=MyBusiness,dc=symfony,dc=com', null, null, [], 'sAMAccountName', '({uid_key}={username})', 'userpassword', ['email']);
        $this->assertInstanceOf(LdapUser::class, $provider->loadUserByUsername('foo'));
    }
}
