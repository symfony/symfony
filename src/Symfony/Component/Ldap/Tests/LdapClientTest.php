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
            ->with('foo', 'bar', array('baz'))
        ;
        $this->client->query('foo', 'bar', array('baz'));
    }

    public function testLdapFind()
    {
        $collection = $this->getMockBuilder(CollectionInterface::class)->getMock();
        $collection
            ->expects($this->once())
            ->method('getIterator')
            ->will($this->returnValue(new \ArrayIterator(array(
                new Entry('cn=qux,dc=foo,dc=com', array(
                    'cn' => array('qux'),
                    'dc' => array('com', 'foo'),
                    'givenName' => array('Qux'),
                )),
                new Entry('cn=baz,dc=foo,dc=com', array(
                    'cn' => array('baz'),
                    'dc' => array('com', 'foo'),
                    'givenName' => array('Baz'),
                )),
            ))))
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
            ->with('dc=foo,dc=com', 'bar', array('filter' => 'baz'))
            ->willReturn($query)
        ;

        $expected = array(
            'count' => 2,
            0 => array(
                'count' => 3,
                0 => 'cn',
                'cn' => array(
                    'count' => 1,
                    0 => 'qux',
                ),
                1 => 'dc',
                'dc' => array(
                    'count' => 2,
                    0 => 'com',
                    1 => 'foo',
                ),
                2 => 'givenname',
                'givenname' => array(
                    'count' => 1,
                    0 => 'Qux',
                ),
                'dn' => 'cn=qux,dc=foo,dc=com',
            ),
            1 => array(
                'count' => 3,
                0 => 'cn',
                'cn' => array(
                    'count' => 1,
                    0 => 'baz',
                ),
                1 => 'dc',
                'dc' => array(
                    'count' => 2,
                    0 => 'com',
                    1 => 'foo',
                ),
                2 => 'givenname',
                'givenname' => array(
                    'count' => 1,
                    0 => 'Baz',
                ),
                'dn' => 'cn=baz,dc=foo,dc=com',
            ),
        );
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
        $this->assertEquals($expected, call_user_func_array(array($reflMethod, 'invoke'), $args));
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
        $search = @ldap_search($con, 'dc=symfony,dc=com', '(&(objectclass=person)(ou=Maintainers))', array('*'));
        $expected = @ldap_get_entries($con, $search);

        $this->assertSame($expected, $result);
    }

    public function provideConfig()
    {
        return array(
            array(
                array('localhost', 389, 3, true, false, false),
                array(
                    'host' => 'localhost',
                    'port' => 389,
                    'encryption' => 'ssl',
                    'options' => array(
                        'protocol_version' => 3,
                        'referrals' => false,
                    ),
                ),
            ),
            array(
                array('localhost', 389, 3, false, true, false),
                array(
                    'host' => 'localhost',
                    'port' => 389,
                    'encryption' => 'tls',
                    'options' => array(
                        'protocol_version' => 3,
                        'referrals' => false,
                    ),
                ),
            ),
            array(
                array('localhost', 389, 3, false, false, false),
                array(
                    'host' => 'localhost',
                    'port' => 389,
                    'encryption' => 'none',
                    'options' => array(
                        'protocol_version' => 3,
                        'referrals' => false,
                    ),
                ),
            ),
            array(
                array('localhost', 389, 3, false, false, false),
                array(
                    'host' => 'localhost',
                    'port' => 389,
                    'encryption' => 'none',
                    'options' => array(
                        'protocol_version' => 3,
                        'referrals' => false,
                    ),
                ),
            ),
        );
    }
}
