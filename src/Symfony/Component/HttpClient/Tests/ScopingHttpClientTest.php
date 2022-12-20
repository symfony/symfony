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
use Symfony\Component\HttpClient\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\ScopingHttpClient;

class ScopingHttpClientTest extends TestCase
{
    public function testRelativeUrl()
    {
        $mockClient = new MockHttpClient();
        $client = new ScopingHttpClient($mockClient, []);

        self::expectException(InvalidArgumentException::class);
        $client->request('GET', '/foo');
    }

    public function testRelativeUrlWithDefaultRegexp()
    {
        $mockClient = new MockHttpClient();
        $client = new ScopingHttpClient($mockClient, ['.*' => ['base_uri' => 'http://example.com', 'query' => ['a' => 'b']]], '.*');

        self::assertSame('http://example.com/foo?f=g&a=b', $client->request('GET', '/foo?f=g')->getInfo('url'));
    }

    /**
     * @dataProvider provideMatchingUrls
     */
    public function testMatchingUrls(string $regexp, string $url, array $options)
    {
        $mockClient = new MockHttpClient();
        $client = new ScopingHttpClient($mockClient, $options);

        $response = $client->request('GET', $url);
        $requestedOptions = $response->getRequestOptions();

        self::assertSame($options[$regexp]['case'], $requestedOptions['case']);
    }

    public function provideMatchingUrls()
    {
        $defaultOptions = [
            '.*/foo-bar' => ['case' => 1],
            '.*' => ['case' => 2],
        ];

        yield ['regexp' => '.*/foo-bar', 'url' => 'http://example.com/foo-bar', 'default_options' => $defaultOptions];
        yield ['regexp' => '.*', 'url' => 'http://example.com/bar-foo', 'default_options' => $defaultOptions];
        yield ['regexp' => '.*', 'url' => 'http://example.com/foobar', 'default_options' => $defaultOptions];
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
        self::assertSame('Content-Type: application/json', $requestOptions['headers'][1]);
        $requestJson = json_decode($requestOptions['body'], true);
        self::assertSame('http://example.com', $requestJson['url']);
        self::assertSame('X-FooBar: '.$defaultOptions['.*/foo-bar']['headers']['X-FooBar'], $requestOptions['headers'][0]);

        $response = $client->request('GET', 'http://example.com/bar-foo', ['headers' => ['X-FooBar' => 'unit-test']]);
        $requestOptions = $response->getRequestOptions();
        self::assertSame('X-FooBar: unit-test', $requestOptions['headers'][0]);
        self::assertSame('Content-Type: text/html', $requestOptions['headers'][1]);

        $response = $client->request('GET', 'http://example.com/foobar-foo', ['headers' => ['X-FooBar' => 'unit-test']]);
        $requestOptions = $response->getRequestOptions();
        self::assertSame('X-FooBar: unit-test', $requestOptions['headers'][0]);
        self::assertSame('Content-Type: text/html', $requestOptions['headers'][1]);
    }

    public function testForBaseUri()
    {
        $client = ScopingHttpClient::forBaseUri(new MockHttpClient(null, null), 'http://example.com/foo');

        $response = $client->request('GET', '/bar');
        self::assertSame('http://example.com/foo', implode('', $response->getRequestOptions()['base_uri']));
        self::assertSame('http://example.com/bar', $response->getInfo('url'));

        $response = $client->request('GET', 'http://foo.bar/');
        self::assertNull($response->getRequestOptions()['base_uri']);
    }
}
