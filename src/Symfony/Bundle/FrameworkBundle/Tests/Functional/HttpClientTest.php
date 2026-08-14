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

use Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\Tests\TransportDecorator;
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

        $this->assertHttpClientRequestCount(6, 'symfony.http_client');
    }

    public function testHttpClientCanBeOverriddenInWebTestCase()
    {
        $browser = $this->createClient(['test_case' => 'HttpClient', 'root_config' => 'config.yml', 'debug' => true]);
        $mockedContent = 'Request Mocked successfully!';
        static::getContainer()->set(HttpClientInterface::class, new MockHttpClient(new MockResponse($mockedContent)));

        $browser->request('GET', '/http_client_mock');

        self::assertSame($mockedContent, $browser->getResponse()->getContent());
    }

    public function testTransportDecoratorsKeepRunningWhenMockResponseFactoryIsSet()
    {
        TransportDecorator::$requests = [];
        $client = $this->createClient(['test_case' => 'HttpClient', 'root_config' => 'config.yml', 'debug' => true]);

        $client->request('GET', '/http_client_call');

        $this->assertNotSame([], TransportDecorator::$requests, 'A decorator of "http_client.transport" with the default priority must be invoked while the mock factory is active.');

        $chain = self::collectTransportChain(static::getContainer()->get('http_client'));
        $this->assertContains(TransportDecorator::class, $chain);
        $this->assertSame(MockHttpClient::class, end($chain), 'The mock must be the innermost part of the transport chain.');
    }

    public function testScopedClientCanOptOutFromTheMockResponseFactory()
    {
        $this->createClient(['test_case' => 'HttpClient', 'root_config' => 'config.yml', 'debug' => true]);

        $chain = self::collectTransportChain(static::getContainer()->get('test.not_mocked.http_client'));
        $this->assertNotContains(MockHttpClient::class, $chain, 'A scoped client with "mock_response_factory: false" must not end up on the mock transport.');

        $chain = self::collectTransportChain(static::getContainer()->get('symfony.http_client'));
        $this->assertSame(MockHttpClient::class, end($chain), 'A scoped client inheriting the top-level factory must end up on the mock transport.');
    }

    /**
     * @return list<class-string>
     */
    private static function collectTransportChain(object $client): array
    {
        $chain = [];

        while (true) {
            $chain[] = $client::class;
            $inner = null;

            for ($r = new \ReflectionObject($client); $r; $r = $r->getParentClass()) {
                foreach ($r->getProperties() as $property) {
                    if ($property->isStatic() || !$property->isInitialized($client)) {
                        continue;
                    }
                    $value = $property->getValue($client);
                    if ($value instanceof HttpClientInterface) {
                        $inner = $value;
                        break 2;
                    }
                }
            }

            if (null === $inner) {
                return $chain;
            }
            $client = $inner;
        }
    }

    public function testScopedClientRetriesAgainstTheNextBaseUri()
    {
        TransportDecorator::$requests = [];

        static::bootKernel(['test_case' => 'HttpClientRetry', 'root_config' => 'config.yml']);
        static::getContainer()->get('test.rotating.http_client')->request('GET', '/posts/1')->getStatusCode();

        // the second attempt goes to the fallback URI, and the scoped options still apply to it
        $this->assertSame([
            'GET https://a.example.com/posts/1 X-Scoped: yes',
            'GET https://b.example.com/posts/1 X-Scoped: yes',
        ], TransportDecorator::$requests);
    }
}
