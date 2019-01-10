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
class LdapClientTest extends LdapTestCase
{
    /** @var LdapClient */
    private $client;
    /** @var \PHPUnit_Framework_MockObject_MockObject */
    private $ldap;

    protected function setUp()
    {
        $this->ldap = $this->getMockBuilder(LdapInterface::class)->getMock();

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
            ->with('foo', 'bar', ['baz'])
        ;
        $this->client->query('foo', 'bar', ['baz']);
    }

    public function testLdapFind()
    {
        $collection = $this->getMockBuilder(CollectionInterface::class)->getMock();
        $collection
            ->expects($this->once())
            ->method('getIterator')
            ->will($this->returnValue(new \ArrayIterator([
                new Entry('cn=qux,dc=foo,dc=com', [
                    'cn' => ['qux'],
                    'dc' => ['com', 'foo'],
                    'givenName' => ['Qux'],
                ]),
                new Entry('cn=baz,dc=foo,dc=com', [
                    'cn' => ['baz'],
                    'dc' => ['com', 'foo'],
                    'givenName' => ['Baz'],
                ]),
            ])))
        ;
        $query = $this->getMockBuilder(QueryInterface::class)->getMock();
        $query
            ->expects($this->once())
            ->method('execute')
            ->will($this->returnValue($collection))
        ;
        $this->ldap
            ->expects($this->once())
            ->method('query')
            ->with('dc=foo,dc=com', 'bar', ['filter' => 'baz'])
            ->willReturn($query)
        ;

        $expected = [
            'count' => 2,
            0 => [
                'count' => 3,
                0 => 'cn',
                'cn' => [
                    'count' => 1,
                    0 => 'qux',
                ],
                1 => 'dc',
                'dc' => [
                    'count' => 2,
                    0 => 'com',
                    1 => 'foo',
                ],
                2 => 'givenname',
                'givenname' => [
                    'count' => 1,
                    0 => 'Qux',
                ],
                'dn' => 'cn=qux,dc=foo,dc=com',
            ],
            1 => [
                'count' => 3,
                0 => 'cn',
                'cn' => [
                    'count' => 1,
                    0 => 'baz',
                ],
                1 => 'dc',
                'dc' => [
                    'count' => 2,
                    0 => 'com',
                    1 => 'foo',
                ],
                2 => 'givenname',
                'givenname' => [
                    'count' => 1,
                    0 => 'Baz',
                ],
                'dn' => 'cn=baz,dc=foo,dc=com',
            ],
        ];
        $this->assertEquals($expected, $this->client->find('dc=foo,dc=com', 'bar', 'baz'));
    }

    /**
     * @dataProvider provideConfig
     */
    public function testLdapClientConfig($args, $expected)
    {
        $reflObj = new \ReflectionObject($this->client);
        $reflMethod = $reflObj->getMethod('normalizeConfig');
        $reflMethod->setAccessible(true);
        array_unshift($args, $this->client);
        $this->assertEquals($expected, \call_user_func_array([$reflMethod, 'invoke'], $args));
    }

    /**
     * @group functional
     * @requires extension ldap
     */
    public function testLdapClientFunctional()
    {
        $config = $this->getLdapConfig();
        $ldap = new LdapClient($config['host'], $config['port']);
        $ldap->bind('cn=admin,dc=symfony,dc=com', 'symfony');
        $result = $ldap->find('dc=symfony,dc=com', '(&(objectclass=person)(ou=Maintainers))');

        $con = @ldap_connect($config['host'], $config['port']);
        @ldap_bind($con, 'cn=admin,dc=symfony,dc=com', 'symfony');
        $search = @ldap_search($con, 'dc=symfony,dc=com', '(&(objectclass=person)(ou=Maintainers))', ['*']);
        $expected = @ldap_get_entries($con, $search);

        $this->assertSame($expected, $result);
    }

    public function provideConfig()
    {
        return [
            [
                ['localhost', 389, 3, true, false, false],
                [
                    'host' => 'localhost',
                    'port' => 389,
                    'encryption' => 'ssl',
                    'options' => [
                        'protocol_version' => 3,
                        'referrals' => false,
                    ],
                ],
            ],
            [
                ['localhost', 389, 3, false, true, false],
                [
                    'host' => 'localhost',
                    'port' => 389,
                    'encryption' => 'tls',
                    'options' => [
                        'protocol_version' => 3,
                        'referrals' => false,
                    ],
                ],
            ],
            [
                ['localhost', 389, 3, false, false, false],
                [
                    'host' => 'localhost',
                    'port' => 389,
                    'encryption' => 'none',
                    'options' => [
                        'protocol_version' => 3,
                        'referrals' => false,
                    ],
                ],
            ],
            [
                ['localhost', 389, 3, false, false, false],
                [
                    'host' => 'localhost',
                    'port' => 389,
                    'encryption' => 'none',
                    'options' => [
                        'protocol_version' => 3,
                        'referrals' => false,
                    ],
                ],
            ],
        ];
    }
}
