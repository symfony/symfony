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
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\Retry\GenericRetryStrategy;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Component\HttpClient\ScopingHttpClient;

class ScopingHttpClientTest extends TestCase
{
    public function testRelativeUrl()
    {
        $mockClient = new MockHttpClient();
        $client = new ScopingHttpClient($mockClient, []);

        $this->expectException(InvalidArgumentException::class);
        $client->request('GET', '/foo');
    }

    public function testRelativeUrlWithDefaultRegexp()
    {
        $mockClient = new MockHttpClient();
        $client = new ScopingHttpClient($mockClient, ['.*' => ['base_uri' => 'http://example.com', 'query' => ['a' => 'b']]], '.*');

        $this->assertSame('http://example.com/foo?f=g&a=b', $client->request('GET', '/foo?f=g')->getInfo('url'));
    }

    #[DataProvider('provideMatchingUrls')]
    public function testMatchingUrls(string $regexp, string $url, array $options)
    {
        $mockClient = new MockHttpClient();
        $client = new ScopingHttpClient($mockClient, $options);

        $response = $client->request('GET', $url);
        $requestedOptions = $response->getRequestOptions();

        $this->assertSame($options[$regexp]['case'], $requestedOptions['case']);
    }

    public static function provideMatchingUrls(): iterable
    {
        $defaultOptions = [
            '.*/foo-bar' => ['case' => 1],
            '.*' => ['case' => 2],
        ];

        yield ['regexp' => '.*/foo-bar', 'url' => 'http://example.com/foo-bar', 'options' => $defaultOptions];
        yield ['regexp' => '.*', 'url' => 'http://example.com/bar-foo', 'options' => $defaultOptions];
        yield ['regexp' => '.*', 'url' => 'http://example.com/foobar', 'options' => $defaultOptions];
    }

    public function testMatchingUrlsAndOptions()
    {
        $defaultOptions = [
            '.*/foo-bar' => ['headers' => ['X-FooBar' => 'unit-test-foo-bar']],
            '.*' => ['headers' => ['Content-Type' => 'text/html']],
        ];

        $mockClient = new MockHttpClient();
        $client = new ScopingHttpClient($mockClient, $defaultOptions);

        $response = $client->request('GET', 'http://example.com/foo-bar', ['json' => ['url' => 'http://example.com']]);
        $requestOptions = $response->getRequestOptions();
        $this->assertSame('Content-Type: application/json', $requestOptions['headers'][1]);
        $requestJson = json_decode($requestOptions['body'], true);
        $this->assertSame('http://example.com', $requestJson['url']);
        $this->assertSame('X-FooBar: '.$defaultOptions['.*/foo-bar']['headers']['X-FooBar'], $requestOptions['headers'][0]);

        $response = $client->request('GET', 'http://example.com/bar-foo', ['headers' => ['X-FooBar' => 'unit-test']]);
        $requestOptions = $response->getRequestOptions();
        $this->assertSame('X-FooBar: unit-test', $requestOptions['headers'][0]);
        $this->assertSame('Content-Type: text/html', $requestOptions['headers'][1]);

        $response = $client->request('GET', 'http://example.com/foobar-foo', ['headers' => ['X-FooBar' => 'unit-test']]);
        $requestOptions = $response->getRequestOptions();
        $this->assertSame('X-FooBar: unit-test', $requestOptions['headers'][0]);
        $this->assertSame('Content-Type: text/html', $requestOptions['headers'][1]);
    }

    public function testForBaseUri()
    {
        $client = ScopingHttpClient::forBaseUri(new MockHttpClient(null, null), 'http://example.com/foo');

        $response = $client->request('GET', '/bar');
        $this->assertSame('http://example.com/bar', $response->getInfo('url'));

        $response = $client->request('GET', 'http://foo.bar/');
        $this->assertSame('http://foo.bar/', $response->getInfo('url'));
    }

    public function testRetryableHttpClientIntegration()
    {
        $responses = [
            new MockResponse('', ['http_code' => 503]),
            new MockResponse('', ['http_code' => 503]),
            new MockResponse('', ['http_code' => 503]),
            new MockResponse(),
        ];

        $client = ScopingHttpClient::forBaseUri(
            new RetryableHttpClient(
                new MockHttpClient($responses),
                new GenericRetryStrategy(GenericRetryStrategy::DEFAULT_RETRY_STATUS_CODES, 0)
            ),
            'https://foo.example.com/app/',
        );

        $response = $client->request('GET', 'santysisi');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('https://foo.example.com/app/santysisi', $response->getInfo('url'));
    }

    #[DataProvider('provideUrlsOnTheBaseUriOrigin')]
    public function testForBaseUriAppliesOptionsOnTheBaseUriOrigin(string $url, string $expectedUrl)
    {
        $client = ScopingHttpClient::forBaseUri(new MockHttpClient(null, null), 'https://safe.example/api/', [
            'auth_bearer' => 'scope-secret',
            'headers' => ['X-Api-Key' => 'scope-secret'],
            'local_cert' => '/scope/client.pem',
        ]);

        $response = $client->request('GET', $url);
        $requestOptions = $response->getRequestOptions();

        $this->assertSame($expectedUrl, $response->getInfo('url'));
        $this->assertContains('Authorization: Bearer scope-secret', $requestOptions['headers']);
        $this->assertContains('X-Api-Key: scope-secret', $requestOptions['headers']);
        $this->assertSame('/scope/client.pem', $requestOptions['local_cert']);
    }

    public static function provideUrlsOnTheBaseUriOrigin(): iterable
    {
        yield 'relative path' => ['users/1', 'https://safe.example/api/users/1'];
        yield 'absolute path outside the base path' => ['/outside-scope', 'https://safe.example/outside-scope'];
        yield 'dot segments outside the base path' => ['../escape', 'https://safe.example/escape'];
        yield 'network-path reference' => ['//safe.example/api/users/1', 'https://safe.example/api/users/1'];
        yield 'network-path reference with the default port' => ['//safe.example:443/api/users/1', 'https://safe.example/api/users/1'];
        yield 'network-path reference with an uppercase host' => ['//SAFE.Example/api/users/1', 'https://safe.example/api/users/1'];
        yield 'network-path reference with userinfo' => ['//user@safe.example/api/users/1', 'https://safe.example/api/users/1'];
        yield 'absolute URL' => ['https://safe.example/api/users/1', 'https://safe.example/api/users/1'];
    }

    #[DataProvider('provideUrlsOutsideTheBaseUriOrigin')]
    public function testForBaseUriDoesNotApplyOptionsOutsideTheBaseUriOrigin(string $url, string $expectedUrl)
    {
        $client = ScopingHttpClient::forBaseUri(new MockHttpClient(null, null), 'https://safe.example/api/', [
            'auth_bearer' => 'scope-secret',
            'headers' => ['X-Api-Key' => 'scope-secret'],
            'local_cert' => '/scope/client.pem',
        ]);

        $response = $client->request('GET', $url);
        $requestOptions = $response->getRequestOptions();

        $this->assertSame($expectedUrl, $response->getInfo('url'));
        $this->assertNotContains('Authorization: Bearer scope-secret', $requestOptions['headers']);
        $this->assertNotContains('X-Api-Key: scope-secret', $requestOptions['headers']);
        $this->assertNull($requestOptions['local_cert'] ?? null);
    }

    public static function provideUrlsOutsideTheBaseUriOrigin(): iterable
    {
        yield 'absolute URL' => ['https://evil.example/steal', 'https://evil.example/steal'];
        yield 'network-path reference' => ['//evil.example/steal', 'https://evil.example/steal'];
        yield 'network-path reference with userinfo naming the base host' => ['//safe.example@evil.example/steal', 'https://evil.example/steal'];
        yield 'network-path reference with another port' => ['//safe.example:8443/api/users/1', 'https://safe.example:8443/api/users/1'];
        yield 'network-path reference with the default port of another scheme' => ['//safe.example:80/api/users/1', 'https://safe.example:80/api/users/1'];
    }

    #[DataProvider('provideUrlsOnABaseUriWithUserinfo')]
    public function testForBaseUriWithUserinfoAppliesOptionsOnItsHost(string $url, string $expectedUrl, bool $withUserinfo)
    {
        $client = ScopingHttpClient::forBaseUri(new MockHttpClient(null, null), 'https://user:pass@safe.example/api/', [
            'headers' => ['X-Api-Key' => 'scope-secret'],
        ]);

        $response = $client->request('GET', $url);

        $this->assertSame($expectedUrl, $response->getInfo('url'));
        $this->assertSame($withUserinfo, \in_array('Authorization: Basic '.base64_encode('user:pass'), $response->getRequestOptions()['headers'], true));
        $this->assertContains('X-Api-Key: scope-secret', $response->getRequestOptions()['headers']);
    }

    public static function provideUrlsOnABaseUriWithUserinfo(): iterable
    {
        yield 'relative path' => ['users/1', 'https://safe.example/api/users/1', true];
        yield 'absolute URL' => ['https://safe.example/api/users/1', 'https://safe.example/api/users/1', false];
        yield 'absolute URL with the userinfo of the base URI' => ['https://user:pass@safe.example/api/users/1', 'https://safe.example/api/users/1', true];
    }

    #[DataProvider('provideUrlsWithUserinfo')]
    public function testScopesAreMatchedAgainstTheUrlWithoutItsUserinfo(string $url, bool $inScope)
    {
        $client = new ScopingHttpClient(new MockHttpClient(), [
            'https://api\.github\.com' => ['auth_bearer' => 'scope-secret'],
        ]);

        $response = $client->request('GET', $url);

        $this->assertSame($inScope ? 'https://api.github.com/' : 'https://evil.example/', $response->getInfo('url'));
        $this->assertSame($inScope, \in_array('Authorization: Bearer scope-secret', $response->getRequestOptions()['headers'], true));
    }

    public static function provideUrlsWithUserinfo(): iterable
    {
        yield 'userinfo naming the host of the scope' => ['https://api.github.com@evil.example/', false];
        yield 'userinfo naming the host of the scope and a port' => ['https://api.github.com:443@evil.example/', false];
        yield 'userinfo naming the host of the scope after an @' => ['https://user@api.github.com@evil.example/', false];
        yield 'userinfo on the host of the scope' => ['https://user:pass@api.github.com/', true];
        yield 'userinfo with an @ on the host of the scope' => ['https://user@evil.example@api.github.com/', true];
    }

    public function testNetworkPathReferenceGetsTheOptionsOfTheScopeItResolvesTo()
    {
        $client = new ScopingHttpClient(new MockHttpClient(), [
            'https://safe\.example/' => [
                'base_uri' => 'https://safe.example/api/',
                'auth_bearer' => 'safe-secret',
                'headers' => ['X-Api-Key' => 'safe-secret'],
            ],
            'https://other\.example/' => [
                'auth_bearer' => 'other-secret',
            ],
        ], 'https://safe\.example/');

        $response = $client->request('GET', '//other.example/users/1');
        $requestOptions = $response->getRequestOptions();

        $this->assertSame('https://other.example/users/1', $response->getInfo('url'));
        $this->assertContains('Authorization: Bearer other-secret', $requestOptions['headers']);
        $this->assertNotContains('X-Api-Key: safe-secret', $requestOptions['headers']);
    }

    public function testNetworkPathReferenceGetsTheDefaultOptionsWhenTheDefaultRegexpMatches()
    {
        $client = new ScopingHttpClient(new MockHttpClient(), [
            'https://(safe|mirror)\.example/' => [
                'base_uri' => 'https://safe.example/api/',
                'auth_bearer' => 'safe-secret',
            ],
        ], 'https://(safe|mirror)\.example/');

        $response = $client->request('GET', '//mirror.example/api/users/1');

        $this->assertSame('https://mirror.example/api/users/1', $response->getInfo('url'));
        $this->assertContains('Authorization: Bearer safe-secret', $response->getRequestOptions()['headers']);
    }
}
