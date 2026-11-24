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
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\DnsMock;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\HttpClient\CachingHttpClient;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\HttpClient\DnsResolvingHttpClient;
use Symfony\Component\HttpClient\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\NativeHttpClient;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\ScopingHttpClient;
use Symfony\Component\HttpClient\TraceableHttpClient;
use Symfony\Component\HttpClient\Tests\Fixtures\ResettableClient;
use Symfony\Component\HttpClient\Tests\Fixtures\ShutdownAwareClient;
use Symfony\Contracts\HttpClient\Test\TestHttpServer;

class NoPrivateNetworkHttpClientTest extends TestCase
{
    use TestServerPortTrait;

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
            ['::ffff:127.0.0.1', ['127.0.0.0/8', '::1/128'], true],
            ['::ffff:7f00:1',    ['127.0.0.0/8', '::1/128'], true],

            ['172.16.0.1',  ['10.0.0.0/8', '192.168.0.0/16'], false],
            ['fc00::1',     ['fe80::/10', '::ffff:0:0/96'],   false],
            ['::ffff:172.16.0.1', ['127.0.0.0/8', '::1/128'], false],

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
        yield ['::ffff:10.0.0.1',    'fc00::/7',              true];
        yield ['::ffff:104.26.14.6', ['fc00::/7', '::1/128'], true];
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
        yield 'IPv6 still blocked by IPv4 subnets despite an IPv6 allow-list entry' => ['::1',      '10.0.0.0/8',          'fd00::1',          true];
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

    public static function getAllowListAddressFamilyData(): iterable
    {
        yield ['fd00::1', '10.0.0.0/8', 'fd00::1', false];
        yield ['::1', '10.0.0.0/8', 'fd00::1', true];
        yield ['2606:4700:20::681a:e06', '10.0.0.0/8', 'fd00::1', true];
        yield ['10.0.0.1', 'fc00::/7', '10.0.0.1', false];
        yield ['127.0.0.1', 'fc00::/7', '10.0.0.1', true];
    }

    #[DataProvider('getAllowListAddressFamilyData')]
    public function testAllowListDoesNotLetItsAddressFamilyThrough(string $ipAddr, string $subnets, string $allowList, bool $mustThrow)
    {
        $host = str_contains($ipAddr, ':') ? '['.$ipAddr.']' : $ipAddr;
        $url = \sprintf('http://%s/', $host);

        if ($mustThrow) {
            $this->expectException(TransportException::class);
            $this->expectExceptionMessage(\sprintf('Host "%s" is blocked for "%s".', $host, $url));
        }

        $client = new NoPrivateNetworkHttpClient(new MockHttpClient(), $subnets, $allowList);

        $this->assertSame(200, $client->request('GET', $url)->getStatusCode());
    }

    #[Group('dns-sensitive')]
    public function testCachedHostIsResolvedToTheCheckedIp()
    {
        DnsMock::withMockedHosts(['example.com' => [['type' => 'A', 'ip' => '104.26.14.6']]]);

        $resolved = [];
        $client = new NoPrivateNetworkHttpClient(new MockHttpClient(static function (string $method, string $url, array $options) use (&$resolved) {
            $resolved[] = $options['resolve'] ?? [];

            return new MockResponse('foo', ['primary_ip' => '104.26.14.6']);
        }));

        $client->request('GET', 'http://example.com/')->getContent();

        DnsMock::withMockedHosts(['example.com' => [['type' => 'A', 'ip' => '127.0.0.1']]]);

        $client->request('GET', 'http://example.com/')->getContent();

        $this->assertSame([['example.com' => '104.26.14.6'], ['example.com' => '104.26.14.6']], $resolved);
    }

    #[DataProvider('getIpv4MappedRequestData')]
    public function testIpv4MappedAddressIsCheckedAgainstCustomIpv4Subnets(string $url, array $options, string $message)
    {
        $client = new NoPrivateNetworkHttpClient(new MockHttpClient([
            new MockResponse('', ['http_code' => 302, 'redirect_url' => 'http://[::ffff:127.0.0.1]/']),
            new MockResponse('internal'),
        ]), ['10.0.0.0/8', '127.0.0.0/8', 'fc00::/7', '::1/128']);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage($message);

        $client->request('GET', $url, $options)->getContent();
    }

    public static function getIpv4MappedRequestData(): iterable
    {
        yield 'url' => ['http://[::ffff:127.0.0.1]/', [], 'Host "[::ffff:127.0.0.1]" is blocked for "http://[::ffff:127.0.0.1]/".'];
        yield 'resolve option' => ['http://example.com/', ['resolve' => ['example.com' => '::ffff:127.0.0.1']], 'Host "example.com" is blocked for "http://example.com/".'];
        yield 'redirect' => ['http://104.26.14.6/', [], 'Host "[::ffff:127.0.0.1]" is blocked for "http://[::ffff:127.0.0.1]/".'];
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

    #[RequiresPhpExtension('openssl')]
    public function testRedirectFromHttpsToHttpIsNotFollowed()
    {
        TestSocketServer::start('tls/redirect-server.php', self::port(8059));
        $client = new NoPrivateNetworkHttpClient(new NativeHttpClient(), '10.0.0.0/8');

        $response = $client->request('GET', 'https://127.0.0.1:'.self::port(8059).'/', [
            'verify_peer' => false,
            'verify_host' => false,
        ]);

        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame('https://127.0.0.1:'.self::port(8059).'/', $response->getInfo('url'));
        $this->assertSame('http://127.0.0.1:'.self::port(8059).'/', $response->getInfo('redirect_url'));
    }

    #[RequiresPhpExtension('openssl')]
    public function testRedirectToADifferentSchemeDropsCredentials()
    {
        TestSocketServer::start('tls/redirect-server.php', self::port(8059));
        $client = new NoPrivateNetworkHttpClient(new NativeHttpClient(), '10.0.0.0/8');

        $response = $client->request('GET', 'http://127.0.0.1:'.self::port(8059).'/upgrade', [
            'auth_basic' => 'foo:bar',
            'headers' => ['Cookie' => 'a=b', 'X-Custom' => 'kept'],
            'verify_peer' => false,
            'verify_host' => false,
        ]);
        $headers = $response->toArray();

        $this->assertSame('https://127.0.0.1:'.self::port(8059).'/echo', $response->getInfo('url'));
        $this->assertSame('kept', $headers['x-custom']);
        $this->assertArrayNotHasKey('authorization', $headers);
        $this->assertArrayNotHasKey('cookie', $headers);
    }

    #[RequiresPhpExtension('openssl')]
    public function testRedirectToADifferentSchemeDropsTheDefaultCredentialsOfTheDecoratedClient()
    {
        TestSocketServer::start('tls/redirect-server.php', self::port(8059));
        $client = new NoPrivateNetworkHttpClient(new NativeHttpClient([
            'auth_bearer' => 'foo',
            'headers' => ['Cookie' => 'a=b', 'X-Custom' => 'kept'],
        ]), '10.0.0.0/8');

        $response = $client->request('GET', 'http://127.0.0.1:'.self::port(8059).'/upgrade', [
            'verify_peer' => false,
            'verify_host' => false,
        ]);
        $headers = $response->toArray();

        $this->assertSame('https://127.0.0.1:'.self::port(8059).'/echo', $response->getInfo('url'));
        $this->assertSame('kept', $headers['x-custom']);
        $this->assertArrayNotHasKey('authorization', $headers);
        $this->assertArrayNotHasKey('cookie', $headers);
    }

    public function testRedirectToAnotherOriginDropsDefaultCredentials()
    {
        $requests = [];
        $mockClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests) {
            $requests[] = [$url, $options['normalized_headers']['authorization'][0] ?? null, $options['normalized_headers']['cookie'][0] ?? null, $options['auth_ntlm'] ?? null];

            switch (\count($requests) % 4) {
                case 1: return new MockResponse('', ['http_code' => 302, 'redirect_url' => 'https://104.26.14.6/same-origin']);
                case 2: return new MockResponse('', ['http_code' => 302, 'redirect_url' => 'https://104.26.14.7/other-origin']);
                case 3: return new MockResponse('', ['http_code' => 302, 'redirect_url' => 'https://104.26.14.6/back']);
                default: return new MockResponse('OK');
            }
        });

        $client = new NoPrivateNetworkHttpClient($mockClient->withOptions([
            'auth_basic' => 'foo:bar',
            'headers' => ['Cookie' => 'a=b'],
            'auth_ntlm' => 'foo:bar',
        ]));
        $client->request('GET', 'https://104.26.14.6/')->getContent();

        $client = (new NoPrivateNetworkHttpClient($mockClient))->withOptions([
            'auth_bearer' => 'foo',
            'headers' => ['Cookie' => 'a=b'],
        ]);
        $client->request('GET', 'https://104.26.14.6/')->getContent();

        $client = new NoPrivateNetworkHttpClient(new TraceableHttpClient($mockClient->withOptions(['headers' => ['Authorization' => 'Bearer foo']])));
        $client->request('GET', 'https://104.26.14.6/', ['auth_ntlm' => 'foo:bar'])->getContent();

        $this->assertSame([
            ['https://104.26.14.6/', 'Authorization: Basic Zm9vOmJhcg==', 'Cookie: a=b', 'foo:bar'],
            ['https://104.26.14.6/same-origin', 'Authorization: Basic Zm9vOmJhcg==', 'Cookie: a=b', 'foo:bar'],
            ['https://104.26.14.7/other-origin', null, null, null],
            ['https://104.26.14.6/back', 'Authorization: Basic Zm9vOmJhcg==', 'Cookie: a=b', 'foo:bar'],
            ['https://104.26.14.6/', 'Authorization: Bearer foo', 'Cookie: a=b', null],
            ['https://104.26.14.6/same-origin', 'Authorization: Bearer foo', 'Cookie: a=b', null],
            ['https://104.26.14.7/other-origin', null, null, null],
            ['https://104.26.14.6/back', 'Authorization: Bearer foo', 'Cookie: a=b', null],
            ['https://104.26.14.6/', 'Authorization: Bearer foo', null, 'foo:bar'],
            ['https://104.26.14.6/same-origin', 'Authorization: Bearer foo', null, 'foo:bar'],
            ['https://104.26.14.7/other-origin', null, null, null],
            ['https://104.26.14.6/back', 'Authorization: Bearer foo', null, 'foo:bar'],
        ], $requests);
    }

    public function testRedirectToAnotherOriginDropsDefaultCredentialsBehindOtherDecorators()
    {
        $requests = [];
        $mockClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests) {
            $requests[] = [$url, $options['normalized_headers']['authorization'][0] ?? null, $options['normalized_headers']['cookie'][0] ?? null, $options['auth_ntlm'] ?? null];

            return 1 === \count($requests) % 2 ? new MockResponse('', ['http_code' => 302, 'redirect_url' => 'https://104.26.14.7/other-origin']) : new MockResponse('OK');
        });
        $mockClient = $mockClient->withOptions([
            'auth_bearer' => 'foo',
            'headers' => ['Cookie' => 'a=b'],
            'auth_ntlm' => 'foo:bar',
        ]);

        $decorators = [
            new ScopingHttpClient($mockClient, ['https://104\.26\.14\.7/' => ['headers' => ['X-Scope' => 'b']]]),
            new CachingHttpClient($mockClient, new TagAwareAdapter(new ArrayAdapter())),
            new NoPrivateNetworkHttpClient($mockClient),
            new NoPrivateNetworkHttpClient(new ScopingHttpClient($mockClient, ['https://104\.26\.14\.7/' => []])),
            new DnsResolvingHttpClient($mockClient, static fn (string $host): ?string => null),
        ];

        foreach ($decorators as $decorator) {
            $client = new NoPrivateNetworkHttpClient($decorator);
            $client->request('GET', 'https://104.26.14.6/', ['extra' => ['no_cache' => true]])->getContent();
        }

        $this->assertSame(array_merge(...array_fill(0, 5, [
            ['https://104.26.14.6/', 'Authorization: Bearer foo', 'Cookie: a=b', 'foo:bar'],
            ['https://104.26.14.7/other-origin', null, null, null],
        ])), $requests);
    }

    public function testRedirectDoesNotRepeatTheQueryOption()
    {
        TestHttpServer::start();
        $client = new NoPrivateNetworkHttpClient(new NativeHttpClient(), '10.0.0.0/8');

        $response = $client->request('GET', 'http://localhost:'.self::port().'/302', [
            'query' => ['location' => 'http://127.0.0.1:'.self::port().'/', 'foo' => 'bar'],
        ]);

        $this->assertSame('/', $response->toArray()['REQUEST_URI']);
    }

    public function testRedirectSendsTheStreamBodyAgain()
    {
        TestHttpServer::start();

        $clients = ['native' => new NativeHttpClient()];
        if (\extension_loaded('curl')) {
            $clients['curl'] = new CurlHttpClient();
        }

        foreach ($clients as $type => $client) {
            $body = fopen('php://temp', 'r+');
            fwrite($body, 'skipped foo=0123456789');
            fseek($body, 8);

            $client = new NoPrivateNetworkHttpClient($client, '10.0.0.0/8');
            $response = $client->request('POST', 'http://localhost:'.self::port().'/307', ['body' => $body]);

            $this->assertSame(['foo' => '0123456789', 'REQUEST_METHOD' => 'POST'], $response->toArray(), $type);
        }
    }

    #[RequiresPhpExtension('openssl')]
    public function testDecoratedClientAppliesItsDefaultPeerFingerprint()
    {
        TestSocketServer::start('tls/redirect-server.php', self::port(8059));
        $client = new NoPrivateNetworkHttpClient(new NativeHttpClient(['peer_fingerprint' => ['sha256' => str_repeat('0', 64)]]), '10.0.0.0/8');

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('peer_fingerprint match failure');

        $client->request('GET', 'https://127.0.0.1:'.self::port(8059).'/', ['verify_peer' => false, 'verify_host' => false])->getHeaders();
    }

    public function testDecoratedClientAppliesItsDefaultOptions()
    {
        $requests = [];
        $mockClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests) {
            $requests[] = [$url, $options['cafile'] ?? null, $options['proxy'] ?? null, $options['timeout'], $options['max_redirects'], $options['normalized_headers']['accept']];

            return 1 === \count($requests) % 2 ? new MockResponse('', ['http_code' => 302, 'redirect_url' => 'https://104.26.14.7/']) : new MockResponse('OK');
        });

        $client = new NoPrivateNetworkHttpClient($mockClient->withOptions([
            'cafile' => '/path/to/ca.pem',
            'proxy' => 'http://proxy.example:3128',
            'timeout' => 7,
            'headers' => ['Accept' => 'application/json'],
        ]));
        $client->request('GET', 'https://104.26.14.6/')->getContent();

        $client = new NoPrivateNetworkHttpClient(ScopingHttpClient::forBaseUri($mockClient, 'https://104.26.14.6/', ['cafile' => '/path/to/ca.pem']));
        $client->request('GET', 'https://104.26.14.6/')->getContent();

        $progress = false;
        $client = (new NoPrivateNetworkHttpClient($mockClient))->withOptions([
            'max_redirects' => 0,
            'user_data' => 'foo',
            'on_progress' => static function () use (&$progress) { $progress = true; },
        ]);
        $response = $client->request('GET', 'https://104.26.14.6/');
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('foo', $response->getInfo('user_data'));
        $this->assertTrue($progress);

        $this->assertSame([
            ['https://104.26.14.6/', '/path/to/ca.pem', 'http://proxy.example:3128', 7.0, 0, ['Accept: application/json']],
            ['https://104.26.14.7/', '/path/to/ca.pem', 'http://proxy.example:3128', 7.0, 0, ['Accept: application/json']],
            ['https://104.26.14.6/', '/path/to/ca.pem', null, (float) \ini_get('default_socket_timeout'), 0, ['Accept: */*']],
            ['https://104.26.14.7/', null, null, (float) \ini_get('default_socket_timeout'), 0, ['Accept: */*']],
            ['https://104.26.14.6/', null, null, (float) \ini_get('default_socket_timeout'), 0, ['Accept: */*']],
        ], $requests);
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

    private function getMockHttpClient(string $ipAddr, string $content)
    {
        return new MockHttpClient(new MockResponse($content, ['primary_ip' => $ipAddr]));
    }

    public function testResetForwardsTheShutdownFlagToShutdownAwareClientsOnly()
    {
        $client = new NoPrivateNetworkHttpClient($inner = new ShutdownAwareClient());
        $client->reset();
        $client->reset(true);

        $this->assertSame([false, true], $inner->resets);

        $client = new NoPrivateNetworkHttpClient($inner = new ResettableClient());
        $client->reset(true);

        $this->assertSame([[]], $inner->resets);
    }
}
