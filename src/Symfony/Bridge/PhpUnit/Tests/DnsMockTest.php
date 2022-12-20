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
    protected function tearDown(): void
    {
        DnsMock::withMockedHosts([]);
    }

    public function testCheckdnsrr()
    {
        DnsMock::withMockedHosts(['example.com' => [['type' => 'MX']]]);
        self::assertTrue(DnsMock::checkdnsrr('example.com'));

        DnsMock::withMockedHosts(['example.com' => [['type' => 'A']]]);
        self::assertFalse(DnsMock::checkdnsrr('example.com'));
        self::assertTrue(DnsMock::checkdnsrr('example.com', 'a'));
        self::assertTrue(DnsMock::checkdnsrr('example.com', 'any'));
        self::assertFalse(DnsMock::checkdnsrr('foobar.com', 'ANY'));
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

        self::assertFalse(DnsMock::getmxrr('foobar.com', $mxhosts, $weight));
        self::assertTrue(DnsMock::getmxrr('example.com', $mxhosts, $weight));
        self::assertSame(['mx.example.com'], $mxhosts);
        self::assertSame([10], $weight);
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

        self::assertSame('::21', DnsMock::gethostbyaddr('::21'));
        self::assertSame('example.com', DnsMock::gethostbyaddr('::12'));
        self::assertSame('example.com', DnsMock::gethostbyaddr('1.2.3.4'));
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

        self::assertSame('foobar.com', DnsMock::gethostbyname('foobar.com'));
        self::assertSame('1.2.3.4', DnsMock::gethostbyname('example.com'));
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

        self::assertFalse(DnsMock::gethostbynamel('foobar.com'));
        self::assertSame(['1.2.3.4', '2.3.4.5'], DnsMock::gethostbynamel('example.com'));
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

        self::assertFalse(DnsMock::dns_get_record('foobar.com'));
        self::assertSame($records, DnsMock::dns_get_record('example.com'));
        self::assertSame($records, DnsMock::dns_get_record('example.com', \DNS_ALL));
        self::assertSame($records, DnsMock::dns_get_record('example.com', \DNS_A | \DNS_PTR));
        self::assertSame([$ptr], DnsMock::dns_get_record('example.com', \DNS_PTR));
    }
}
