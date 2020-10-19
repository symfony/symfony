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
use Symfony\Component\HttpClient\CachingHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpKernel\HttpCache\Store;
use Symfony\Contracts\HttpClient\ResponseInterface;

class CachingHttpClientTest extends TestCase
{
    public function testRequestHeaders()
    {
        $options = [
            'headers' => [
                'Application-Name' => 'test1234',
                'Test-Name-Header' => 'test12345',
            ],
        ];

        $mockClient = new MockHttpClient();
        $store = new Store(sys_get_temp_dir().'/sf_http_cache');
        $client = new CachingHttpClient($mockClient, $store, $options);

        $response = $client->request('GET', 'http://example.com/foo-bar');

        rmdir(sys_get_temp_dir().'/sf_http_cache');
        self::assertInstanceOf(MockResponse::class, $response);
        self::assertSame($response->getRequestOptions()['normalized_headers']['application-name'][0], 'Application-Name: test1234');
        self::assertSame($response->getRequestOptions()['normalized_headers']['test-name-header'][0], 'Test-Name-Header: test12345');
    }

    public function testDoesNotEvaluateResponseBody()
    {
        $body = file_get_contents(__DIR__.'/Fixtures/assertion_failure.php');
        $response = $this->runRequest(new MockResponse($body, ['response_headers' => ['X-Body-Eval' => true]]));
        $headers = $response->getHeaders();

        $this->assertSame($body, $response->getContent());
        $this->assertArrayNotHasKey('x-body-eval', $headers);
    }

    public function testDoesNotIncludeFile()
    {
        $file = __DIR__.'/Fixtures/assertion_failure.php';

        $response = $this->runRequest(new MockResponse(
            'test', ['response_headers' => [
                'X-Body-Eval' => true,
                'X-Body-File' => $file,
            ]]
        ));
        $headers = $response->getHeaders();

        $this->assertSame('test', $response->getContent());
        $this->assertArrayNotHasKey('x-body-eval', $headers);
        $this->assertArrayNotHasKey('x-body-file', $headers);
    }

    public function testDoesNotReadFile()
    {
        $file = __DIR__.'/Fixtures/assertion_failure.php';

        $response = $this->runRequest(new MockResponse(
            'test', ['response_headers' => [
                'X-Body-File' => $file,
            ]]
        ));
        $headers = $response->getHeaders();

        $this->assertSame('test', $response->getContent());
        $this->assertArrayNotHasKey('x-body-file', $headers);
    }

    public function testRemovesXContentDigest()
    {
        $response = $this->runRequest(new MockResponse(
            'test', [
            'response_headers' => [
                'X-Content-Digest' => 'some-hash',
            ],
        ]));
        $headers = $response->getHeaders();

        $this->assertArrayNotHasKey('x-content-digest', $headers);
    }

    private function runRequest(MockResponse $mockResponse): ResponseInterface
    {
        $mockClient = new MockHttpClient($mockResponse);

        $store = new Store(sys_get_temp_dir().'/sf_http_cache');
        $client = new CachingHttpClient($mockClient, $store);

        $response = $client->request('GET', 'http://test');

        return $response;
    }
}
