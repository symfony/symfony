<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Ldap\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Ldap\Adapter\AdapterInterface;
use Symfony\Component\Ldap\Adapter\ConnectionInterface;
use Symfony\Component\Ldap\Exception\DriverNotFoundException;
use Symfony\Component\Ldap\Ldap;

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
