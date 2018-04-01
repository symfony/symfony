<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Ldap\Tests;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Ldap\Adapter\AdapterInterface;
use Symphony\Component\Ldap\Adapter\ConnectionInterface;
use Symphony\Component\Ldap\Exception\DriverNotFoundException;
use Symphony\Component\Ldap\Ldap;

class LdapTest extends TestCase
{
    /** @var \PHPUnit_Framework_MockObject_MockObject */
    private $adapter;

    /** @var Ldap */
    private $ldap;

    protected function setUp()
    {
        $this->adapter = $this->getMockBuilder(AdapterInterface::class)->getMock();
        $this->ldap = new Ldap($this->adapter);
    }

    public function testLdapBind()
    {
        $connection = $this->getMockBuilder(ConnectionInterface::class)->getMock();
        $connection
            ->expects($this->once())
            ->method('bind')
            ->with('foo', 'bar')
        ;
        $this->adapter
            ->expects($this->once())
            ->method('getConnection')
            ->will($this->returnValue($connection))
        ;
        $this->ldap->bind('foo', 'bar');
    }

    public function testLdapEscape()
    {
        $this->adapter
            ->expects($this->once())
            ->method('escape')
            ->with('foo', 'bar', 'baz')
        ;
        $this->ldap->escape('foo', 'bar', 'baz');
    }

    public function testLdapQuery()
    {
        $this->adapter
            ->expects($this->once())
            ->method('createQuery')
            ->with('foo', 'bar', array('baz'))
        ;
        $this->ldap->query('foo', 'bar', array('baz'));
    }

    /**
     * @requires extension ldap
     */
    public function testLdapCreate()
    {
        $ldap = Ldap::create('ext_ldap');
        $this->assertInstanceOf(Ldap::class, $ldap);
    }

    public function testCreateWithInvalidAdapterName()
    {
        $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}(DriverNotFoundException::class);
        Ldap::create('foo');
    }
}
