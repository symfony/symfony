<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\DnsMock;

class DnsMockTest extends TestCase
{
    protected function tearDown()
    {
        DnsMock::withMockedHosts([]);
    }

    public function testCheckdnsrr()
    {
        DnsMock::withMockedHosts(['example.com' => [['type' => 'MX']]]);
        $this->assertTrue(DnsMock::checkdnsrr('example.com'));

        DnsMock::withMockedHosts(['example.com' => [['type' => 'A']]]);
        $this->assertFalse(DnsMock::checkdnsrr('example.com'));
        $this->assertTrue(DnsMock::checkdnsrr('example.com', 'a'));
        $this->assertTrue(DnsMock::checkdnsrr('example.com', 'any'));
        $this->assertFalse(DnsMock::checkdnsrr('foobar.com', 'ANY'));
    }

    public function testGetmxrr()
    {
        DnsMock::withMockedHosts([
            'example.com' => [[
                'type' => 'MX',
                'host' => 'mx.example.com',
                'pri' => 10,
            ]],
        ]);

        $this->assertFalse(DnsMock::getmxrr('foobar.com', $mxhosts, $weight));
        $this->assertTrue(DnsMock::getmxrr('example.com', $mxhosts, $weight));
        $this->assertSame(['mx.example.com'], $mxhosts);
        $this->assertSame([10], $weight);
    }

    public function testGethostbyaddr()
    {
        DnsMock::withMockedHosts([
            'example.com' => [
                [
                    'type' => 'A',
                    'ip' => '1.2.3.4',
                ],
                [
                    'type' => 'AAAA',
                    'ipv6' => '::12',
                ],
            ],
        ]);

        $this->assertSame('::21', DnsMock::gethostbyaddr('::21'));
        $this->assertSame('example.com', DnsMock::gethostbyaddr('::12'));
        $this->assertSame('example.com', DnsMock::gethostbyaddr('1.2.3.4'));
    }

    public function testGethostbyname()
    {
        DnsMock::withMockedHosts([
            'example.com' => [
                [
                    'type' => 'AAAA',
                    'ipv6' => '::12',
                ],
                [
                    'type' => 'A',
                    'ip' => '1.2.3.4',
                ],
            ],
        ]);

        $this->assertSame('foobar.com', DnsMock::gethostbyname('foobar.com'));
        $this->assertSame('1.2.3.4', DnsMock::gethostbyname('example.com'));
    }

    public function testGethostbynamel()
    {
        DnsMock::withMockedHosts([
            'example.com' => [
                [
                    'type' => 'A',
                    'ip' => '1.2.3.4',
                ],
                [
                    'type' => 'A',
                    'ip' => '2.3.4.5',
                ],
            ],
        ]);

        $this->assertFalse(DnsMock::gethostbynamel('foobar.com'));
        $this->assertSame(['1.2.3.4', '2.3.4.5'], DnsMock::gethostbynamel('example.com'));
    }

    public function testDnsGetRecord()
    {
        DnsMock::withMockedHosts([
            'example.com' => [
                [
                    'type' => 'A',
                    'ip' => '1.2.3.4',
                ],
                [
                    'type' => 'PTR',
                    'ip' => '2.3.4.5',
                ],
            ],
        ]);

        $records = [
            [
                'host' => 'example.com',
                'class' => 'IN',
                'ttl' => 1,
                'type' => 'A',
                'ip' => '1.2.3.4',
            ],
            $ptr = [
                'host' => 'example.com',
                'class' => 'IN',
                'ttl' => 1,
                'type' => 'PTR',
                'ip' => '2.3.4.5',
            ],
        ];

        $this->assertFalse(DnsMock::dns_get_record('foobar.com'));
        $this->assertSame($records, DnsMock::dns_get_record('example.com'));
        $this->assertSame($records, DnsMock::dns_get_record('example.com', DNS_ALL));
        $this->assertSame($records, DnsMock::dns_get_record('example.com', DNS_A | DNS_PTR));
        $this->assertSame([$ptr], DnsMock::dns_get_record('example.com', DNS_PTR));
    }
}
