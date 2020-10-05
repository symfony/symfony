<?php

namespace Symfony\Component\HttpClient\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\ServerException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\Retry\ExponentialBackOff;
use Symfony\Component\HttpClient\Retry\HttpStatusCodeDecider;
use Symfony\Component\HttpClient\Retry\RetryDeciderInterface;
use Symfony\Component\HttpClient\RetryableHttpClient;

class RetryableHttpClientTest extends TestCase
{
    public function testRetryOnError()
    {
        $client = new RetryableHttpClient(
            new MockHttpClient([
                new MockResponse('', ['http_code' => 500]),
                new MockResponse('', ['http_code' => 200]),
            ]),
            new HttpStatusCodeDecider([500]),
            new ExponentialBackOff(0),
            1
        );

        $response = $client->request('GET', 'http://example.com/foo-bar');

        self::assertSame(200, $response->getStatusCode());
    }

    public function testRetryRespectStrategy()
    {
        $client = new RetryableHttpClient(
            new MockHttpClient([
                new MockResponse('', ['http_code' => 500]),
                new MockResponse('', ['http_code' => 500]),
                new MockResponse('', ['http_code' => 200]),
            ]),
            new HttpStatusCodeDecider([500]),
            new ExponentialBackOff(0),
            1
        );

        $response = $client->request('GET', 'http://example.com/foo-bar');

        $this->expectException(ServerException::class);
        $response->getHeaders();
    }

    public function testRetryWithBody()
    {
        $client = new RetryableHttpClient(
            new MockHttpClient([
                new MockResponse('', ['http_code' => 500]),
                new MockResponse('', ['http_code' => 200]),
            ]),
            new class() implements RetryDeciderInterface {
                public function shouldRetry(string $requestMethod, string $requestUrl, array $requestOptions, int $responseCode, array $responseHeaders, ?string $responseContent): ?bool
                {
                    return null === $responseContent ? null : 200 !== $responseCode;
                }
            },
            new ExponentialBackOff(0),
            1
        );

        $response = $client->request('GET', 'http://example.com/foo-bar');

        self::assertSame(200, $response->getStatusCode());
    }

    public function testRetryWithBodyInvalid()
    {
        $client = new RetryableHttpClient(
            new MockHttpClient([
                new MockResponse('', ['http_code' => 500]),
                new MockResponse('', ['http_code' => 200]),
            ]),
            new class() implements RetryDeciderInterface {
                public function shouldRetry(string $requestMethod, string $requestUrl, array $requestOptions, int $responseCode, array $responseHeaders, ?string $responseContent, \Throwable $throwable = null): ?bool
                {
                    return null;
                }
            },
            new ExponentialBackOff(0),
            1
        );

        $response = $client->request('GET', 'http://example.com/foo-bar');

        $this->expectExceptionMessageMatches('/must not return null when called with a body/');
        $response->getHeaders();
    }

    public function testStreamNoRetry()
    {
        $client = new RetryableHttpClient(
            new MockHttpClient([
                new MockResponse('', ['http_code' => 500]),
            ]),
            new HttpStatusCodeDecider([500]),
            new ExponentialBackOff(0),
            0
        );

        $response = $client->request('GET', 'http://example.com/foo-bar');

        foreach ($client->stream($response) as $chunk) {
            if ($chunk->isFirst()) {
                self::assertSame(500, $response->getStatusCode());
            }
        }
    }
}
