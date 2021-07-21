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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Ldap\Adapter\AdapterInterface;
use Symfony\Component\Ldap\Adapter\ConnectionInterface;
use Symfony\Component\Ldap\Adapter\QueryInterface;
use Symfony\Component\Ldap\Exception\DriverNotFoundException;
use Symfony\Component\Ldap\Ldap;

class LdapTest extends TestCase
{
    /** @var MockObject&AdapterInterface */
    private $adapter;

    /** @var Ldap */
    private $ldap;

    protected function setUp(): void
    {
        $this->adapter = $this->createMock(AdapterInterface::class);
        $this->ldap = new Ldap($this->adapter);
    }

    public function testLdapBind()
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection
            ->expects($this->once())
            ->method('bind')
            ->with('foo', 'bar')
        ;
        $this->adapter
            ->expects($this->once())
            ->method('getConnection')
            ->willReturn($connection)
        ;
        $this->ldap->bind('foo', 'bar');
    }

    public function testLdapEscape()
    {
        $this->adapter
            ->expects($this->once())
            ->method('escape')
            ->with('foo', 'bar', 0)
            ->willReturn('')
        ;

        $this->ldap->escape('foo', 'bar', 0);
    }

    public function testLdapQuery()
    {
        $this->adapter
            ->expects($this->once())
            ->method('createQuery')
            ->with('foo', 'bar', ['baz'])
            ->willReturn($this->createMock(QueryInterface::class))
        ;
        $this->ldap->query('foo', 'bar', ['baz']);
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
        $this->expectException(DriverNotFoundException::class);
        Ldap::create('foo');
    }
}
