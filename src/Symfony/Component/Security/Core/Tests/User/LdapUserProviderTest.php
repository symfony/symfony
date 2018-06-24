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
use Symfony\Component\Security\Core\User\LdapUserRoleProviderInterface;
use Symfony\Component\Security\Core\User\User;

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
            ->will($this->throwException(new ConnectionException()))
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
            ->will($this->returnValue($result))
        ;
        $result
            ->expects($this->once())
            ->method('count')
            ->will($this->returnValue(0))
        ;
        $ldap = $this->getMockBuilder(LdapInterface::class)->getMock();
        $ldap
            ->expects($this->once())
            ->method('escape')
            ->will($this->returnValue('foo'))
        ;
        $ldap
            ->expects($this->once())
            ->method('query')
            ->will($this->returnValue($query))
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
            ->will($this->returnValue($result))
        ;
        $result
            ->expects($this->once())
            ->method('count')
            ->will($this->returnValue(2))
        ;
        $ldap = $this->getMockBuilder(LdapInterface::class)->getMock();
        $ldap
            ->expects($this->once())
            ->method('escape')
            ->will($this->returnValue('foo'))
        ;
        $ldap
            ->expects($this->once())
            ->method('query')
            ->will($this->returnValue($query))
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
            ->will($this->returnValue($result))
        ;
        $ldap = $this->getMockBuilder(LdapInterface::class)->getMock();
        $result
            ->expects($this->once())
            ->method('offsetGet')
            ->with(0)
            ->will($this->returnValue(new Entry('foo', array(
                    'sAMAccountName' => array('foo'),
                    'userpassword' => array('bar', 'baz'),
                )
            )))
        ;
        $result
            ->expects($this->once())
            ->method('count')
            ->will($this->returnValue(1))
        ;
        $ldap
            ->expects($this->once())
            ->method('escape')
            ->will($this->returnValue('foo'))
        ;
        $ldap
            ->expects($this->once())
            ->method('query')
            ->will($this->returnValue($query))
        ;

        $provider = new LdapUserProvider($ldap, 'ou=MyBusiness,dc=symfony,dc=com', null, null, array(), 'sAMAccountName', '({uid_key}={username})', 'userpassword');
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
            ->will($this->returnValue($result))
        ;
        $ldap = $this->getMockBuilder(LdapInterface::class)->getMock();
        $result
            ->expects($this->once())
            ->method('offsetGet')
            ->with(0)
            ->will($this->returnValue(new Entry('foo', array())))
        ;
        $result
            ->expects($this->once())
            ->method('count')
            ->will($this->returnValue(1))
        ;
        $ldap
            ->expects($this->once())
            ->method('escape')
            ->will($this->returnValue('foo'))
        ;
        $ldap
            ->expects($this->once())
            ->method('query')
            ->will($this->returnValue($query))
        ;

        $provider = new LdapUserProvider($ldap, 'ou=MyBusiness,dc=symfony,dc=com', null, null, array(), 'sAMAccountName', '({uid_key}={username})');
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
            ->will($this->returnValue($result))
        ;
        $ldap = $this->getMockBuilder(LdapInterface::class)->getMock();
        $result
            ->expects($this->once())
            ->method('offsetGet')
            ->with(0)
            ->will($this->returnValue(new Entry('foo', array(
                    'sAMAccountName' => array('foo'),
                )
            )))
        ;
        $result
            ->expects($this->once())
            ->method('count')
            ->will($this->returnValue(1))
        ;
        $ldap
            ->expects($this->once())
            ->method('escape')
            ->will($this->returnValue('foo'))
        ;
        $ldap
            ->expects($this->once())
            ->method('query')
            ->will($this->returnValue($query))
        ;

        $provider = new LdapUserProvider($ldap, 'ou=MyBusiness,dc=symfony,dc=com', null, null, array(), 'sAMAccountName', '({uid_key}={username})', 'userpassword');
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
            ->will($this->returnValue($result))
        ;
        $ldap = $this->getMockBuilder(LdapInterface::class)->getMock();
        $result
            ->expects($this->once())
            ->method('offsetGet')
            ->with(0)
            ->will($this->returnValue(new Entry('foo', array(
                    'sAMAccountName' => array('foo'),
                )
            )))
        ;
        $result
            ->expects($this->once())
            ->method('count')
            ->will($this->returnValue(1))
        ;
        $ldap
            ->expects($this->once())
            ->method('escape')
            ->will($this->returnValue('foo'))
        ;
        $ldap
            ->expects($this->once())
            ->method('query')
            ->will($this->returnValue($query))
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
            ->will($this->returnValue($result))
        ;
        $ldap = $this->getMockBuilder(LdapInterface::class)->getMock();
        $result
            ->expects($this->once())
            ->method('offsetGet')
            ->with(0)
            ->will($this->returnValue(new Entry('foo', array(
                    'sAMAccountName' => array('foo'),
                )
            )))
        ;
        $result
            ->expects($this->once())
            ->method('count')
            ->will($this->returnValue(1))
        ;
        $ldap
            ->expects($this->once())
            ->method('escape')
            ->will($this->returnValue('Foo'))
        ;
        $ldap
            ->expects($this->once())
            ->method('query')
            ->will($this->returnValue($query))
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
            ->will($this->returnValue($result))
        ;
        $ldap = $this->getMockBuilder(LdapInterface::class)->getMock();
        $result
            ->expects($this->once())
            ->method('offsetGet')
            ->with(0)
            ->will($this->returnValue(new Entry('foo', array(
                    'sAMAccountName' => array('foo'),
                    'userpassword' => array('bar'),
                )
            )))
        ;
        $result
            ->expects($this->once())
            ->method('count')
            ->will($this->returnValue(1))
        ;
        $ldap
            ->expects($this->once())
            ->method('escape')
            ->will($this->returnValue('foo'))
        ;
        $ldap
            ->expects($this->once())
            ->method('query')
            ->will($this->returnValue($query))
        ;

        $provider = new LdapUserProvider($ldap, 'ou=MyBusiness,dc=symfony,dc=com', null, null, array(), 'sAMAccountName', '({uid_key}={username})', 'userpassword');
        $this->assertInstanceOf(
            'Symfony\Component\Security\Core\User\User',
            $provider->loadUserByUsername('foo')
        );
    }

    public function testWithLdapRolesProvider()
    {
        $ldap = $this->getMockBuilder(LdapInterface::class)->getMock();
        $rolesProvider = $this->getMockBuilder(LdapUserRoleProviderInterface::class)->setMethods(array('getRoles'))->getMock();
        $rolesProvider->method('getRoles')->willReturn(array('ROLE_GROUP1', 'ROLE_GROUP2'));

        $provider = new LdapUserProvider(
            $ldap,
            'ou=users,dc=onfroy,dc=net',
            null,
            null,
            array('ROLE_DEFAULT'),
            'sAMAccountName',
            '({uid_key}={username})',
            'password',
            $rolesProvider
        );

        $method = new \ReflectionMethod(LdapUserProvider::class, 'loadUser');
        $method->setAccessible(true);

        /** @var User $user */
        $user = $method->invoke($provider, 'username', new Entry('rudy', array('password' => array('password'))));

        $this->assertEquals(array('ROLE_DEFAULT', 'ROLE_GROUP1', 'ROLE_GROUP2'), $user->getRoles());
    }
}
