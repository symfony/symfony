<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\DnsMock;
use Symfony\Component\HttpClient\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\NativeHttpClient;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class NoPrivateNetworkHttpClientTest extends TestCase
{
    public static function getExcludeIpData(): array
    {
        return [
            // private
            ['0.0.0.1',     null,          true],
            ['169.254.0.1', null,          true],
            ['127.0.0.1',   null,          true],
            ['240.0.0.1',   null,          true],
            ['10.0.0.1',    null,          true],
            ['172.16.0.1',  null,          true],
            ['192.168.0.1', null,          true],
            ['::1',         null,          true],
            ['::ffff:0:1',  null,          true],
            ['fe80::1',     null,          true],
            ['fc00::1',     null,          true],
            ['fd00::1',     null,          true],
            ['::7f00:1',          null,    true],
            ['2002:7f00:1::',     null,    true],
            ['2001::1',           null,    true],
            ['64:ff9b::7f00:1',   null,    true],
            ['64:ff9b:1::7f00:1', null,    true],
            ['10.0.0.1',    '10.0.0.0/24', true],
            ['10.0.0.1',    '10.0.0.1',    true],
            ['fc00::1',     'fc00::1/120', true],
            ['fc00::1',     'fc00::1',     true],

            ['172.16.0.1',  ['10.0.0.0/8', '192.168.0.0/16'], false],
            ['fc00::1',     ['fe80::/10', '::ffff:0:0/96'],   false],

            // public
            ['104.26.14.6',            null,                false],
            ['104.26.14.6',            '104.26.14.0/24',    true],
            ['2606:4700:20::681a:e06', null,                false],
            ['2606:4700:20::681a:e06', '2606:4700:20::/43', true],
        ];
    }

    public static function getExcludeHostData(): iterable
    {
        yield from self::getExcludeIpData();

        // no ipv4/ipv6 at all
        yield ['2606:4700:20::681a:e06', '::/0',      true];
        yield ['104.26.14.6',            '0.0.0.0/0', true];

        // weird scenarios (e.g.: when trying to match ipv4 address on ipv6 subnet)
        yield ['10.0.0.1', 'fc00::/7',   true];
        yield ['fc00::1',  '10.0.0.0/8', true];
    }

    /**
     * @dataProvider getExcludeIpData
     * @group dns-sensitive
     */
    public function testExcludeByIp(string $ipAddr, $subnets, bool $mustThrow)
    {
        $host = strtr($ipAddr, '.:', '--');
        DnsMock::withMockedHosts([
            $host => [
                str_contains($ipAddr, ':') ? [
                    'type' => 'AAAA',
                    'ipv6' => '3706:5700:20::ac43:4826',
                ] : [
                    'type' => 'A',
                    'ip' => '105.26.14.6',
                ],
            ],
        ]);

        $content = 'foo';
        $url = \sprintf('http://%s/', $host);

        if ($mustThrow) {
            $this->expectException(TransportException::class);
            $this->expectExceptionMessage(\sprintf('IP "%s" is blocked for "%s".', $ipAddr, $url));
        }

        $previousHttpClient = $this->getMockHttpClient($ipAddr, $content);
        $client = new NoPrivateNetworkHttpClient($previousHttpClient, $subnets);
        $response = $client->request('GET', $url);

        if (!$mustThrow) {
            $this->assertEquals($content, $response->getContent());
            $this->assertEquals(200, $response->getStatusCode());
        }
    }

    /**
     * @dataProvider getExcludeHostData
     * @group dns-sensitive
     */
    public function testExcludeByHost(string $ipAddr, $subnets, bool $mustThrow)
    {
        $host = strtr($ipAddr, '.:', '--');
        DnsMock::withMockedHosts([
            $host => [
                str_contains($ipAddr, ':') ? [
                    'type' => 'AAAA',
                    'ipv6' => $ipAddr,
                ] : [
                    'type' => 'A',
                    'ip' => $ipAddr,
                ],
            ],
        ]);

        $content = 'foo';
        $url = \sprintf('http://%s/', $host);

        if ($mustThrow) {
            $this->expectException(TransportException::class);
            $this->expectExceptionMessage(\sprintf('Host "%s" is blocked for "%s".', $host, $url));
        }

        $previousHttpClient = $this->getMockHttpClient($ipAddr, $content);
        $client = new NoPrivateNetworkHttpClient($previousHttpClient, $subnets);
        $response = $client->request('GET', $url);

        if (!$mustThrow) {
            $this->assertEquals($content, $response->getContent());
            $this->assertEquals(200, $response->getStatusCode());
        }
    }

    public function testCustomOnProgressCallback()
    {
        $ipAddr = '104.26.14.6';
        $url = sprintf('http://%s/', $ipAddr);
        $content = 'foo';

        $executionCount = 0;
        $customCallback = function (int $dlNow, int $dlSize, array $info) use (&$executionCount): void {
            ++$executionCount;
        };

        $previousHttpClient = $this->getMockHttpClient($ipAddr, $content);
        $client = new NoPrivateNetworkHttpClient($previousHttpClient);
        $response = $client->request('GET', $url, ['on_progress' => $customCallback]);

        $this->assertEquals(1, $executionCount);
        $this->assertEquals($content, $response->getContent());
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testNonCallableOnProgressCallback()
    {
        $ipAddr = '104.26.14.6';
        $url = sprintf('http://%s/', $ipAddr);
        $customCallback = sprintf('cb_%s', microtime(true));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Option "on_progress" must be callable, "string" given.');

        $client = new NoPrivateNetworkHttpClient(new MockHttpClient());
        $client->request('GET', $url, ['on_progress' => $customCallback]);
    }

    /**
     * @requires extension openssl
     */
    public function testRedirectToADifferentSchemeDropsCredentials()
    {
        TestRedirectServer::start();
        $client = new NoPrivateNetworkHttpClient(new NativeHttpClient(), '10.0.0.0/8');

        $response = $client->request('GET', 'https://127.0.0.1:8059/', [
            'auth_basic' => 'foo:bar',
            'headers' => ['Cookie' => 'a=b', 'X-Custom' => 'kept'],
            'verify_peer' => false,
            'verify_host' => false,
        ]);
        $headers = $response->toArray();

        $this->assertSame('http://127.0.0.1:8059/', $response->getInfo('url'));
        $this->assertSame('kept', $headers['x-custom']);
        $this->assertArrayNotHasKey('authorization', $headers);
        $this->assertArrayNotHasKey('cookie', $headers);
    }

    public function testConstructor()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument 2 passed to "Symfony\Component\HttpClient\NoPrivateNetworkHttpClient::__construct()" must be of the type array, string or null. "int" given.');

        new NoPrivateNetworkHttpClient(new MockHttpClient(), 3);
    }

    private function getMockHttpClient(string $ipAddr, string $content)
    {
        return new MockHttpClient(new MockResponse($content, ['primary_ip' => $ipAddr]));
    }
}
