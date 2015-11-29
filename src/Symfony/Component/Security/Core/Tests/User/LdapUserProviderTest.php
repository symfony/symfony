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

use Symfony\Component\Security\Core\User\LdapUserProvider;
use Symfony\Component\Ldap\Exception\ConnectionException;

class LdapUserProviderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\UsernameNotFoundException
     */
    public function testLoadUserByUsernameFailsIfCantConnectToLdap()
    {
        $ldap = $this->getMock('Symfony\Component\Ldap\LdapInterface');
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
        $result = $this->getMockBuilder('Symfony\Component\Ldap\Search\Result')
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $ldap = $this->getMock('Symfony\Component\Ldap\LdapInterface');
        $result
            ->expects($this->once())
            ->method('all')
            ->will($this->returnValue(array(
            )))
        ;
        $ldap
            ->expects($this->once())
            ->method('escape')
            ->will($this->returnValue('foo'))
        ;
        $ldap
            ->expects($this->once())
            ->method('query')
            ->will($this->returnValue($result))
        ;

        $provider = new LdapUserProvider($ldap, 'ou=MyBusiness,dc=symfony,dc=com');
        $provider->loadUserByUsername('foo');
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\UsernameNotFoundException
     */
    public function testLoadUserByUsernameFailsIfMoreThanOneLdapEntry()
    {
        $result = $this->getMockBuilder('Symfony\Component\Ldap\Search\Result')
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $ldap = $this->getMock('Symfony\Component\Ldap\LdapInterface');
        $result
            ->expects($this->once())
            ->method('all')
            ->will($this->returnValue(array(
                array(),
                array(),
            )))
        ;
        $ldap
            ->expects($this->once())
            ->method('escape')
            ->will($this->returnValue('foo'))
        ;
        $ldap
            ->expects($this->once())
            ->method('query')
            ->will($this->returnValue($result))
        ;

        $provider = new LdapUserProvider($ldap, 'ou=MyBusiness,dc=symfony,dc=com');
        $provider->loadUserByUsername('foo');
    }

    public function testSuccessfulLoadUserByUsername()
    {
        $result = $this->getMockBuilder('Symfony\Component\Ldap\Search\Result')
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $ldap = $this->getMock('Symfony\Component\Ldap\LdapInterface');
        $result
            ->expects($this->once())
            ->method('all')
            ->will($this->returnValue(array(
                array(
                    'sAMAccountName' => 'foo',
                    'userpassword' => 'bar',
                ),
            )))
        ;
        $ldap
            ->expects($this->once())
            ->method('escape')
            ->will($this->returnValue('foo'))
        ;
        $ldap
            ->expects($this->once())
            ->method('query')
            ->will($this->returnValue($result))
        ;

        $provider = new LdapUserProvider($ldap, 'ou=MyBusiness,dc=symfony,dc=com');
        $this->assertInstanceOf(
            'Symfony\Component\Security\Core\User\User',
            $provider->loadUserByUsername('foo')
        );
    }
}
