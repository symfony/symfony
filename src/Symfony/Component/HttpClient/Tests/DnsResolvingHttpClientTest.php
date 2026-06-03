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

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\DnsResolvingHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\Service\ResetInterface;

class DnsResolvingHttpClientTest extends TestCase
{
    public function testResolverIsCalledOnRequest()
    {
        $resolvedHosts = [];
        $mockClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $this->assertSame('1.2.3.4', $options['resolve']['example.com'] ?? null);

            return new MockResponse('OK');
        });

        $client = new DnsResolvingHttpClient($mockClient, static function (string $host) use (&$resolvedHosts): ?string {
            $resolvedHosts[] = $host;

            return '1.2.3.4';
        });
        $response = $client->request('GET', 'http://example.com/');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('OK', $response->getContent());
        $this->assertSame(['example.com'], $resolvedHosts);
    }

    public function testResolverReturningNullFallsBackToTransport()
    {
        $mockClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $this->assertArrayNotHasKey('example.com', $options['resolve']);

            return new MockResponse('OK');
        });

        $client = new DnsResolvingHttpClient($mockClient, static fn (string $host): ?string => null);

        $this->assertSame(200, $client->request('GET', 'http://example.com/')->getStatusCode());
    }

    public function testResolverIsCalledOnRedirect()
    {
        $resolvedHosts = [];
        $responses = [
            new MockResponse('', ['http_code' => 302, 'redirect_url' => 'http://other.example.com/target']),
            function (string $method, string $url, array $options) {
                $this->assertSame('http://other.example.com/target', $url);
                $this->assertSame('10.0.0.2', $options['resolve']['other.example.com'] ?? null);

                return new MockResponse('Redirected');
            },
        ];

        $client = new DnsResolvingHttpClient(new MockHttpClient($responses), static function (string $host) use (&$resolvedHosts): ?string {
            $resolvedHosts[] = $host;

            return '10.0.0.'.\count($resolvedHosts);
        });
        $response = $client->request('GET', 'http://example.com/');

        $this->assertSame('Redirected', $response->getContent());
        $this->assertSame(['example.com', 'other.example.com'], $resolvedHosts);
    }

    public function testPostBecomesGetOnRedirect()
    {
        $responses = [
            new MockResponse('', ['http_code' => 302, 'redirect_url' => 'http://example.com/target']),
            function (string $method, string $url, array $options) {
                $this->assertSame('GET', $method);

                return new MockResponse('Redirected');
            },
        ];

        $client = new DnsResolvingHttpClient(new MockHttpClient($responses), static fn (string $host): ?string => '1.2.3.4');

        $this->assertSame('Redirected', $client->request('POST', 'http://example.com/', ['body' => 'foo'])->getContent());
    }

    #[TestWith(['http://93.184.216.34/'])]
    #[TestWith(['http://[2606:2800:220:1:248:1893:25c8:1946]/'])]
    public function testIpHostsAreNotResolved(string $url)
    {
        $resolverCalled = false;
        $client = new DnsResolvingHttpClient(new MockHttpClient(new MockResponse('OK')), static function () use (&$resolverCalled): ?string {
            $resolverCalled = true;

            return null;
        });

        $this->assertSame(200, $client->request('GET', $url)->getStatusCode());
        $this->assertFalse($resolverCalled);
    }

    public function testExplicitResolveOptionIsNotOverridden()
    {
        $resolverCalled = false;
        $mockClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $this->assertSame('5.5.5.5', $options['resolve']['example.com'] ?? null);

            return new MockResponse('OK');
        });

        $client = new DnsResolvingHttpClient($mockClient, static function () use (&$resolverCalled): ?string {
            $resolverCalled = true;

            return '9.9.9.9';
        });
        $client->request('GET', 'http://example.com/', ['resolve' => ['example.com' => '5.5.5.5']]);

        $this->assertFalse($resolverCalled);
    }

    public function testWithOptions()
    {
        $mockClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $this->assertSame('1.2.3.4', $options['resolve']['example.com'] ?? null);
            $this->assertContains('x-foo: bar', $options['headers']);

            return new MockResponse('OK');
        });

        $client = (new DnsResolvingHttpClient($mockClient, static fn (string $host): ?string => '1.2.3.4'))
            ->withOptions(['headers' => ['x-foo' => 'bar']]);

        $this->assertSame(200, $client->request('GET', 'http://example.com/')->getStatusCode());
    }

    public function testResetIsForwardedToResolver()
    {
        $resolver = new class implements ResetInterface {
            public int $resetCount = 0;

            public function __invoke(string $host): ?string
            {
                return null;
            }

            public function reset(): void
            {
                ++$this->resetCount;
            }
        };

        $client = new DnsResolvingHttpClient(new MockHttpClient(), $resolver);
        $client->reset();

        $this->assertSame(1, $resolver->resetCount);
    }
}
