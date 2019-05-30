<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\User;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Ldap\Adapter\CollectionInterface;
use Symfony\Component\Ldap\Adapter\QueryInterface;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Exception\ConnectionException;
use Symfony\Component\Ldap\LdapInterface;
use Symfony\Component\Security\Core\User\LdapUserProvider;

/**
 * @requires extension ldap
 */
class LdapUserProviderTest extends TestCase
{
    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\UsernameNotFoundException
     */
    public function testLoadUserByUsernameFailsIfCantConnectToLdap()
    {
        $ldap = $this->getMockBuilder(LdapInterface::class)->getMock();
        $ldap
            ->expects($this->once())
            ->method('bind')
            ->willThrowException(new ConnectionException())
        ;

        $provider = new LdapUserProvider($ldap, 'ou=MyBusiness,dc=symfony,dc=com');
        $provider->loadUserByUsername('foo');
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\UsernameNotFoundException
     */
    public function testLoadUserByUsernameFailsIfNoLdapEntries()
    {
        $result = $this->getMockBuilder(CollectionInterface::class)->getMock();
        $query = $this->getMockBuilder(QueryInterface::class)->getMock();
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
        $ldap = $this->getMockBuilder(LdapInterface::class)->getMock();
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

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\UsernameNotFoundException
     */
    public function testLoadUserByUsernameFailsIfMoreThanOneLdapEntry()
    {
        $result = $this->getMockBuilder(CollectionInterface::class)->getMock();
        $query = $this->getMockBuilder(QueryInterface::class)->getMock();
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
        $ldap = $this->getMockBuilder(LdapInterface::class)->getMock();
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

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\InvalidArgumentException
     */
    public function testLoadUserByUsernameFailsIfMoreThanOneLdapPasswordsInEntry()
    {
        $result = $this->getMockBuilder(CollectionInterface::class)->getMock();
        $query = $this->getMockBuilder(QueryInterface::class)->getMock();
        $query
            ->expects($this->once())
            ->method('execute')
            ->willReturn($result)
        ;
        $ldap = $this->getMockBuilder(LdapInterface::class)->getMock();
        $result
            ->expects($this->once())
            ->method('offsetGet')
            ->with(0)
            ->willReturn(new Entry('foo', [
                    'sAMAccountName' => ['foo'],
                    'userpassword' => ['bar', 'baz'],
                ]
            ))
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
        $this->assertInstanceOf(
            'Symfony\Component\Security\Core\User\User',
            $provider->loadUserByUsername('foo')
        );
    }

    public function testLoadUserByUsernameShouldNotFailIfEntryHasNoUidKeyAttribute()
    {
        $result = $this->getMockBuilder(CollectionInterface::class)->getMock();
        $query = $this->getMockBuilder(QueryInterface::class)->getMock();
        $query
            ->expects($this->once())
            ->method('execute')
            ->willReturn($result)
        ;
        $ldap = $this->getMockBuilder(LdapInterface::class)->getMock();
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
        $this->assertInstanceOf(
            'Symfony\Component\Security\Core\User\User',
            $provider->loadUserByUsername('foo')
        );
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\InvalidArgumentException
     */
    public function testLoadUserByUsernameFailsIfEntryHasNoPasswordAttribute()
    {
        $result = $this->getMockBuilder(CollectionInterface::class)->getMock();
        $query = $this->getMockBuilder(QueryInterface::class)->getMock();
        $query
            ->expects($this->once())
            ->method('execute')
            ->willReturn($result)
        ;
        $ldap = $this->getMockBuilder(LdapInterface::class)->getMock();
        $result
            ->expects($this->once())
            ->method('offsetGet')
            ->with(0)
            ->willReturn(new Entry('foo', [
                    'sAMAccountName' => ['foo'],
                ]
            ))
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
        $this->assertInstanceOf(
            'Symfony\Component\Security\Core\User\User',
            $provider->loadUserByUsername('foo')
        );
    }

    public function testLoadUserByUsernameIsSuccessfulWithoutPasswordAttribute()
    {
        $result = $this->getMockBuilder(CollectionInterface::class)->getMock();
        $query = $this->getMockBuilder(QueryInterface::class)->getMock();
        $query
            ->expects($this->once())
            ->method('execute')
            ->willReturn($result)
        ;
        $ldap = $this->getMockBuilder(LdapInterface::class)->getMock();
        $result
            ->expects($this->once())
            ->method('offsetGet')
            ->with(0)
            ->willReturn(new Entry('foo', [
                    'sAMAccountName' => ['foo'],
                ]
            ))
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
        $this->assertInstanceOf(
            'Symfony\Component\Security\Core\User\User',
            $provider->loadUserByUsername('foo')
        );
    }

    public function testLoadUserByUsernameIsSuccessfulWithoutPasswordAttributeAndWrongCase()
    {
        $result = $this->getMockBuilder(CollectionInterface::class)->getMock();
        $query = $this->getMockBuilder(QueryInterface::class)->getMock();
        $query
            ->expects($this->once())
            ->method('execute')
            ->willReturn($result)
        ;
        $ldap = $this->getMockBuilder(LdapInterface::class)->getMock();
        $result
            ->expects($this->once())
            ->method('offsetGet')
            ->with(0)
            ->willReturn(new Entry('foo', [
                    'sAMAccountName' => ['foo'],
                ]
            ))
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
        $result = $this->getMockBuilder(CollectionInterface::class)->getMock();
        $query = $this->getMockBuilder(QueryInterface::class)->getMock();
        $query
            ->expects($this->once())
            ->method('execute')
            ->willReturn($result)
        ;
        $ldap = $this->getMockBuilder(LdapInterface::class)->getMock();
        $result
            ->expects($this->once())
            ->method('offsetGet')
            ->with(0)
            ->willReturn(new Entry('foo', [
                    'sAMAccountName' => ['foo'],
                    'userpassword' => ['bar'],
                ]
            ))
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
        $this->assertInstanceOf(
            'Symfony\Component\Security\Core\User\User',
            $provider->loadUserByUsername('foo')
        );
    }
}
