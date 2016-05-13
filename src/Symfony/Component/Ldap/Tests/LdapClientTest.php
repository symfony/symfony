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

use Symfony\Component\Ldap\Adapter\CollectionInterface;
use Symfony\Component\Ldap\Adapter\QueryInterface;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\LdapClient;
use Symfony\Component\Ldap\LdapInterface;

/**
 * @group legacy
 */
class LdapClientTest extends \PHPUnit_Framework_TestCase
{
    /** @var LdapClient */
    private $client;
    /** @var \PHPUnit_Framework_MockObject_MockObject */
    private $ldap;

    protected function setUp()
    {
        $this->ldap = $this->getMock(LdapInterface::class);

        $this->client = new LdapClient(null, 389, 3, false, false, false, $this->ldap);
    }

    public function testLdapBind()
    {
        $this->ldap
            ->expects($this->once())
            ->method('bind')
            ->with('foo', 'bar')
        ;
        $this->client->bind('foo', 'bar');
    }

    public function testLdapEscape()
    {
        $this->ldap
            ->expects($this->once())
            ->method('escape')
            ->with('foo', 'bar', 'baz')
        ;
        $this->client->escape('foo', 'bar', 'baz');
    }

    public function testLdapQuery()
    {
        $this->ldap
            ->expects($this->once())
            ->method('query')
            ->with('foo', 'bar', array('baz'))
        ;
        $this->client->query('foo', 'bar', array('baz'));
    }

    public function testLdapFind()
    {
        $collection = $this->getMock(CollectionInterface::class);
        $collection
            ->expects($this->once())
            ->method('getIterator')
            ->will($this->returnValue(new \ArrayIterator(array(
                new Entry('cn=qux,dc=foo,dc=com', array(
                    'dn' => array('cn=qux,dc=foo,dc=com'),
                    'cn' => array('qux'),
                    'dc' => array('com', 'foo'),
                    'givenName' => array('Qux'),
                )),
                new Entry('cn=baz,dc=foo,dc=com', array(
                    'dn' => array('cn=baz,dc=foo,dc=com'),
                    'cn' => array('baz'),
                    'dc' => array('com', 'foo'),
                    'givenName' => array('Baz'),
                )),
            ))))
        ;
        $query = $this->getMock(QueryInterface::class);
        $query
            ->expects($this->once())
            ->method('execute')
            ->will($this->returnValue($collection))
        ;
        $this->ldap
            ->expects($this->once())
            ->method('query')
            ->with('dc=foo,dc=com', 'bar', array('filter' => 'baz'))
            ->willReturn($query)
        ;

        $expected = array(
            'count' => 2,
            0 => array(
                'count' => 4,
                0 => array(
                    'count' => 1,
                    0 => 'cn=qux,dc=foo,dc=com',
                ),
                'dn' => array(
                    'count' => 1,
                    0 => 'cn=qux,dc=foo,dc=com',
                ),
                1 => array(
                    'count' => 1,
                    0 => 'qux',
                ),
                'cn' => array(
                    'count' => 1,
                    0 => 'qux',
                ),
                2 => array(
                    'count' => 2,
                    0 => 'com',
                    1 => 'foo',
                ),
                'dc' => array(
                    'count' => 2,
                    0 => 'com',
                    1 => 'foo',
                ),
                3 => array(
                    'count' => 1,
                    0 => 'Qux',
                ),
                'givenName' => array(
                    'count' => 1,
                    0 => 'Qux',
                ),
            ),
            1 => array(
                'count' => 4,
                0 => array(
                    'count' => 1,
                    0 => 'cn=baz,dc=foo,dc=com',
                ),
                'dn' => array(
                    'count' => 1,
                    0 => 'cn=baz,dc=foo,dc=com',
                ),
                1 => array(
                    'count' => 1,
                    0 => 'baz',
                ),
                'cn' => array(
                    'count' => 1,
                    0 => 'baz',
                ),
                2 => array(
                    'count' => 2,
                    0 => 'com',
                    1 => 'foo',
                ),
                'dc' => array(
                    'count' => 2,
                    0 => 'com',
                    1 => 'foo',
                ),
                3 => array(
                    'count' => 1,
                    0 => 'Baz',
                ),
                'givenName' => array(
                    'count' => 1,
                    0 => 'Baz',
                ),
            ),
        );
        $this->assertEquals($expected, $this->client->find('dc=foo,dc=com', 'bar', 'baz'));
    }
}
