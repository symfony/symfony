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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\DnsMock;
use Symfony\Component\HttpClient\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
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

    #[DataProvider('getExcludeIpData')]
    #[Group('dns-sensitive')]
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

    #[DataProvider('getExcludeHostData')]
    #[Group('dns-sensitive')]
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

    public static function getAllowListData(): iterable
    {
        // private IPs that match the allow-list -> request is allowed
        yield 'private IPv4 explicitly allowed (single IP)' => ['10.0.0.1', null,                  '10.0.0.1',         false];
        yield 'private IPv4 explicitly allowed (CIDR)' => ['10.0.0.42', null,                 '10.0.0.0/24',      false];
        yield 'private IPv4 explicitly allowed (array)' => ['10.0.0.1', null,                  ['10.0.0.1'],       false];
        yield 'private IPv6 explicitly allowed (single IP)' => ['fc00::1',  null,                  'fc00::1',          false];
        yield 'private IPv6 explicitly allowed (CIDR)' => ['fc00::1',  null,                  'fc00::/7',         false];

        // private IPs that don't match the allow-list -> request is still blocked
        yield 'private IPv4 not in allow-list still blocked' => ['10.0.0.1', null,                  '192.168.0.0/24',   true];
        yield 'private IPv6 not in allow-list still blocked' => ['fc00::1',  null,                  'fd00::/8',         true];

        // allow-list combined with a custom $subnets list
        yield 'private IPv4 allowed via allow-list against custom subnets' => ['10.0.0.1', '10.0.0.0/8',          '10.0.0.1',         false];
        yield 'public IPv4 keeps being allowed regardless of allow-list' => ['104.26.14.6', null,               '10.0.0.0/8',       false];
    }

    #[DataProvider('getAllowListData')]
    #[Group('dns-sensitive')]
    public function testAllowList(string $ipAddr, string|array|null $subnets, string|array $allowList, bool $mustThrow)
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
        $client = new NoPrivateNetworkHttpClient($previousHttpClient, $subnets, $allowList);
        $response = $client->request('GET', $url);

        if (!$mustThrow) {
            $this->assertEquals($content, $response->getContent());
            $this->assertEquals(200, $response->getStatusCode());
        }
    }

    public function testCustomOnProgressCallback()
    {
        $ipAddr = '104.26.14.6';
        $url = \sprintf('http://%s/', $ipAddr);
        $content = 'foo';

        $executionCount = 0;
        $customCallback = static function (int $dlNow, int $dlSize, array $info) use (&$executionCount): void {
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
        $url = \sprintf('http://%s/', $ipAddr);
        $customCallback = \sprintf('cb_%s', microtime(true));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Option "on_progress" must be callable, "string" given.');

        $client = new NoPrivateNetworkHttpClient(new MockHttpClient());
        $client->request('GET', $url, ['on_progress' => $customCallback]);
    }

    public function testHeadersArePassedOnRedirect()
    {
        $ipAddr = '104.26.14.6';
        $url = \sprintf('http://%s/', $ipAddr);
        $content = 'foo';

        $callback = function ($method, $url, $options) use ($content): MockResponse {
            $this->assertArrayHasKey('headers', $options);
            $this->assertNotContains('content-type: application/json', $options['headers']);
            $this->assertContains('foo: bar', $options['headers']);

            return new MockResponse($content);
        };
        $responses = [
            new MockResponse('', ['http_code' => 302, 'redirect_url' => 'http://104.26.14.7']),
            $callback,
        ];
        $client = new NoPrivateNetworkHttpClient(new MockHttpClient($responses));
        $response = $client->request('POST', $url, ['headers' => ['foo' => 'bar', 'content-type' => 'application/json']]);
        $this->assertEquals($content, $response->getContent());
    }

    /**
     * The redirect response is abandoned in favor of the target response as soon as it's
     * followed. If it isn't explicitly canceled at that point, its underlying transport is left
     * running unsupervised in the background, and its own destructor can later throw uncaught,
     * independently of the response actually being used, whenever it eventually gets
     * garbage-collected on its own.
     */
    public function testRedirectResponseIsCanceledAfterBeingFollowed()
    {
        $redirectResponse = new MockResponse('', ['http_code' => 302, 'redirect_url' => 'http://104.26.14.7']);
        $client = new NoPrivateNetworkHttpClient(new MockHttpClient([$redirectResponse, new MockResponse('final body')]));

        $response = $client->request('GET', 'http://104.26.14.6/');

        // MockHttpClient::request() wraps $redirectResponse into a fresh MockResponse instance
        // (see MockResponse::fromRequest()) -- that's the one AsyncResponse actually holds and
        // the one that must end up canceled, not the request template itself.
        $wrapped = new \ReflectionProperty($response, 'response');
        $firstHop = $wrapped->getValue($response);

        $this->assertSame('final body', $response->getContent());
        $this->assertTrue($firstHop->getInfo('canceled'));
    }

    /**
     * Demonstrates the actual consequence of not canceling the abandoned redirect response: its
     * own destructor throws uncaught. In production, this happens because the response's
     * underlying transport is left running unsupervised in the background and can complete (or
     * time out) entirely independently of AsyncResponse's own bookkeeping -- e.g. curl finishing
     * headers for an abandoned handle before any PHP-level code ever calls getStatusCode() on it.
     * That exact curl/GC timing can't be forced in a fast, deterministic test, but the state it
     * leaves behind can be recreated directly: a response whose status was genuinely never
     * checked by anything. Without the fix, destructing such a response crashes; with it, the
     * explicit cancel() call already short-circuits that destructor check beforehand.
     */
    public function testUnfollowedRedirectResponseDoesNotThrowFromDestructWhenLeftUnchecked()
    {
        $redirectResponse = new MockResponse('', ['http_code' => 302, 'redirect_url' => 'http://104.26.14.7']);
        $client = new NoPrivateNetworkHttpClient(new MockHttpClient([$redirectResponse, new MockResponse('final body')]));

        $response = $client->request('GET', 'http://104.26.14.6/');

        $wrapped = new \ReflectionProperty($response, 'response');
        $firstHop = $wrapped->getValue($response);

        $this->assertSame('final body', $response->getContent());

        // Simulate curl completing this abandoned hop's headers on its own, as if
        // getStatusCode() had never been called on it by anything.
        (new \ReflectionProperty($firstHop, 'initializer'))->setValue($firstHop, static fn () => false);

        try {
            unset($firstHop);
        } catch (\Throwable $e) {
            $this->fail(\sprintf('Destructing the abandoned redirect response threw: %s(%s)', $e::class, $e->getMessage()));
        }

        $this->addToAssertionCount(1);
    }

    private function getMockHttpClient(string $ipAddr, string $content)
    {
        return new MockHttpClient(new MockResponse($content, ['primary_ip' => $ipAddr]));
    }
}
