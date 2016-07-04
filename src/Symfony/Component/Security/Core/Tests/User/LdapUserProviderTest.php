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

use Symfony\Component\Ldap\Adapter\CollectionInterface;
use Symfony\Component\Ldap\Adapter\QueryInterface;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\LdapInterface;
use Symfony\Component\Security\Core\User\LdapUserProvider;
use Symfony\Component\Ldap\Exception\ConnectionException;

/**
 * @requires extension ldap
 */
class LdapUserProviderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\UsernameNotFoundException
     */
    public function testLoadUserByUsernameFailsIfCantConnectToLdap()
    {
        $ldap = $this->getMock(LdapInterface::class);
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
        $result = $this->getMock(CollectionInterface::class);
        $query = $this->getMock(QueryInterface::class);
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
        $ldap = $this->getMock(LdapInterface::class);
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
        $result = $this->getMock(CollectionInterface::class);
        $query = $this->getMock(QueryInterface::class);
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
        $ldap = $this->getMock(LdapInterface::class);
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
        $result = $this->getMock(CollectionInterface::class);
        $query = $this->getMock(QueryInterface::class);
        $query
            ->expects($this->once())
            ->method('execute')
            ->will($this->returnValue($result))
        ;
        $ldap = $this->getMock(LdapInterface::class);
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

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\InvalidArgumentException
     */
    public function testLoadUserByUsernameFailsIfEntryHasNoPasswordAttribute()
    {
        $result = $this->getMock(CollectionInterface::class);
        $query = $this->getMock(QueryInterface::class);
        $query
            ->expects($this->once())
            ->method('execute')
            ->will($this->returnValue($result))
        ;
        $ldap = $this->getMock(LdapInterface::class);
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
        $result = $this->getMock(CollectionInterface::class);
        $query = $this->getMock(QueryInterface::class);
        $query
            ->expects($this->once())
            ->method('execute')
            ->will($this->returnValue($result))
        ;
        $ldap = $this->getMock(LdapInterface::class);
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

    public function testLoadUserByUsernameIsSuccessfulWithPasswordAttribute()
    {
        $result = $this->getMock(CollectionInterface::class);
        $query = $this->getMock(QueryInterface::class);
        $query
            ->expects($this->once())
            ->method('execute')
            ->will($this->returnValue($result))
        ;
        $ldap = $this->getMock(LdapInterface::class);
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
}
