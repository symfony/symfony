<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

use PHPUnit\Framework\AssertionFailedError;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HttpClientTest extends AbstractWebTestCase
{
    public function testHttpClientAssertions()
    {
        $client = $this->createClient(['test_case' => 'HttpClient', 'root_config' => 'config.yml', 'debug' => true]);
        $client->enableProfiler();
        $client->request('GET', '/http_client_call');

        $this->assertHttpClientRequest('https://symfony.com/');
        $this->assertHttpClientRequest('https://symfony.com/', 'GET', null, [], 'symfony.http_client');
        $this->assertHttpClientRequest('https://symfony.com/', 'POST', 'foo', [], 'symfony.http_client');
        $this->assertHttpClientRequest('https://symfony.com/', 'POST', ['foo' => 'bar'], [], 'symfony.http_client');
        $this->assertHttpClientRequest('https://symfony.com/', 'POST', ['foo' => 'bar'], [], 'symfony.http_client');
        $this->assertHttpClientRequest('https://symfony.com/', 'POST', ['foo' => 'bar'], ['X-Test-Header' => 'foo'], 'symfony.http_client');
        $this->assertHttpClientRequest('https://symfony.com/doc/current/index.html', 'GET', null, [], 'symfony.http_client');
        $this->assertNotHttpClientRequest('https://laravel.com', 'GET', 'symfony.http_client');

        $this->assertHttpClientRequestCount(7, 'symfony.http_client');
    }

    public function testAssertHttpClientRequestFailsWhenBodyDoesNotMatch()
    {
        $client = $this->createClient(['test_case' => 'HttpClient', 'root_config' => 'config.yml', 'debug' => true]);
        $client->enableProfiler();
        $client->request('GET', '/http_client_call');

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('The request "POST" - "https://symfony.com/" has been called, but with a different body or different headers.');

        $this->assertHttpClientRequest('https://symfony.com/', 'POST', ['foo' => 'baz'], [], 'symfony.http_client');
    }

    public function testAssertHttpClientRequestFailsWhenHeadersDoNotMatch()
    {
        $client = $this->createClient(['test_case' => 'HttpClient', 'root_config' => 'config.yml', 'debug' => true]);
        $client->enableProfiler();
        $client->request('GET', '/http_client_call');

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('The request "POST" - "https://symfony.com/" has been called, but with a different body or different headers.');

        $this->assertHttpClientRequest('https://symfony.com/', 'POST', null, ['X-Test-Header' => 'bar'], 'symfony.http_client');
    }

    public function testAssertHttpClientRequestFailsWhenTheRequestHasNotBeenCalled()
    {
        $client = $this->createClient(['test_case' => 'HttpClient', 'root_config' => 'config.yml', 'debug' => true]);
        $client->enableProfiler();
        $client->request('GET', '/http_client_call');

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('The expected request has not been called: "POST" - "https://symfony.com/never-called"');

        $this->assertHttpClientRequest('https://symfony.com/never-called', 'POST', null, [], 'symfony.http_client');
    }

    public function testAssertHttpClientRequestMatchesAnEmptyBody()
    {
        $client = $this->createClient(['test_case' => 'HttpClient', 'root_config' => 'config.yml', 'debug' => true]);
        $client->enableProfiler();
        $client->request('GET', '/http_client_call');

        $this->assertHttpClientRequest('https://symfony.com/empty-body', 'POST', '', [], 'symfony.http_client');
    }

    public function testHttpClientCanBeOverriddenInWebTestCase()
    {
        $browser = $this->createClient(['test_case' => 'HttpClient', 'root_config' => 'config.yml', 'debug' => true]);
        $mockedContent = 'Request Mocked successfully!';
        static::getContainer()->set(HttpClientInterface::class, new MockHttpClient(new MockResponse($mockedContent)));

        $browser->request('GET', '/http_client_mock');

        self::assertSame($mockedContent, $browser->getResponse()->getContent());
    }
}
