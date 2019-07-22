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

        $this->expectException(InvalidArgumentException::class);
        $client->request('GET', '/foo');
    }

    public function testRelativeUrlWithDefaultRegexp()
    {
        $mockClient = new MockHttpClient();
        $client = new ScopingHttpClient($mockClient, ['.*' => ['base_uri' => 'http://example.com']], '.*');

        $this->assertSame('http://example.com/foo', $client->request('GET', '/foo')->getInfo('url'));
    }

    /**
     * @dataProvider provideMatchingUrls
     */
    public function testMatchingUrls(string $regexp, string $url, array $options)
    {
        $mockClient = new MockHttpClient();
        $client = new ScopingHttpClient($mockClient, $options);

        $response = $client->request('GET', $url);
        $reuestedOptions = $response->getRequestOptions();

        $this->assertEquals($reuestedOptions['case'], $options[$regexp]['case']);
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
            '.*/foo-bar' => ['headers' => ['x-app' => 'unit-test-foo-bar']],
            '.*' => ['headers' => ['content-type' => 'text/html']],
        ];

        $mockClient = new MockHttpClient();
        $client = new ScopingHttpClient($mockClient, $defaultOptions);

        $response = $client->request('GET', 'http://example.com/foo-bar', ['json' => ['url' => 'http://example.com']]);
        $requestOptions = $response->getRequestOptions();
        $this->assertEquals($requestOptions['headers']['content-type'][0], 'application/json');
        $requestJson = json_decode($requestOptions['body'], true);
        $this->assertEquals($requestJson['url'], 'http://example.com');
        $this->assertEquals($requestOptions['headers']['x-app'][0], $defaultOptions['.*/foo-bar']['headers']['x-app']);

        $response = $client->request('GET', 'http://example.com/bar-foo', ['headers' => ['x-app' => 'unit-test']]);
        $requestOptions = $response->getRequestOptions();
        $this->assertEquals($requestOptions['headers']['x-app'][0], 'unit-test');
        $this->assertEquals($requestOptions['headers']['content-type'][0], 'text/html');

        $response = $client->request('GET', 'http://example.com/foobar-foo', ['headers' => ['x-app' => 'unit-test']]);
        $requestOptions = $response->getRequestOptions();
        $this->assertEquals($requestOptions['headers']['x-app'][0], 'unit-test');
        $this->assertEquals($requestOptions['headers']['content-type'][0], 'text/html');
    }

    public function testForBaseUri()
    {
        $client = ScopingHttpClient::forBaseUri(new MockHttpClient(), 'http://example.com/foo');

        $response = $client->request('GET', '/bar');
        $this->assertSame('http://example.com/foo', implode('', $response->getRequestOptions()['base_uri']));
        $this->assertSame('http://example.com/bar', $response->getInfo('url'));

        $response = $client->request('GET', 'http://foo.bar/');
        $this->assertNull($response->getRequestOptions()['base_uri']);
    }
}
