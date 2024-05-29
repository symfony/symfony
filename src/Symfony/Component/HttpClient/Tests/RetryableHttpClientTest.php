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
use Symfony\Component\HttpClient\Exception\ServerException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\NativeHttpClient;
use Symfony\Component\HttpClient\Response\AsyncContext;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\Retry\GenericRetryStrategy;
use Symfony\Component\HttpClient\Retry\RetryStrategyInterface;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\Test\TestHttpServer;

class RetryableHttpClientTest extends TestCase
{
    public static function tearDownAfterClass(): void
    {
        TestHttpServer::stop();
    }

    public function testRetryOnError()
    {
        $client = new RetryableHttpClient(
            new MockHttpClient([
                new MockResponse('', ['http_code' => 500]),
                new MockResponse('', ['http_code' => 200]),
            ]),
            new GenericRetryStrategy([500], 0),
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
            new GenericRetryStrategy([500], 0),
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
                new MockResponse('abc', ['http_code' => 500]),
                new MockResponse('def', ['http_code' => 200]),
            ]),
            new class(GenericRetryStrategy::DEFAULT_RETRY_STATUS_CODES, 0) extends GenericRetryStrategy {
                public function shouldRetry(AsyncContext $context, ?string $responseContent, ?TransportExceptionInterface $exception): ?bool
                {
                    return 500 === $context->getStatusCode() && null === $responseContent ? null : 200 !== $context->getStatusCode();
                }
            },
            2
        );

        $response = $client->request('GET', 'http://example.com/foo-bar');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('def', $response->getContent());
    }

    public function testRetryWithBodyKeepContent()
    {
        $client = new RetryableHttpClient(
            new MockHttpClient([
                new MockResponse('my bad', ['http_code' => 400]),
            ]),
            new class([400], 0) extends GenericRetryStrategy {
                public function shouldRetry(AsyncContext $context, ?string $responseContent, ?TransportExceptionInterface $exception): ?bool
                {
                    if (null === $responseContent) {
                        return null;
                    }

                    return 'my bad' !== $responseContent;
                }
            },
            1
        );

        $response = $client->request('GET', 'http://example.com/foo-bar');

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('my bad', $response->getContent(false));
    }

    public function testRetryWithBodyInvalid()
    {
        $client = new RetryableHttpClient(
            new MockHttpClient([
                new MockResponse('', ['http_code' => 500]),
                new MockResponse('', ['http_code' => 200]),
            ]),
            new class(GenericRetryStrategy::DEFAULT_RETRY_STATUS_CODES, 0) extends GenericRetryStrategy {
                public function shouldRetry(AsyncContext $context, ?string $responseContent, ?TransportExceptionInterface $exception): ?bool
                {
                    return null;
                }
            },
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
            new GenericRetryStrategy([500], 0),
            0
        );

        $response = $client->request('GET', 'http://example.com/foo-bar');

        foreach ($client->stream($response) as $chunk) {
            if ($chunk->isFirst()) {
                self::assertSame(500, $response->getStatusCode());
            }
        }
    }

    public function testRetryWithDnsIssue()
    {
        $client = new RetryableHttpClient(
            new NativeHttpClient(),
            new class(GenericRetryStrategy::DEFAULT_RETRY_STATUS_CODES, 0) extends GenericRetryStrategy {
                public function shouldRetry(AsyncContext $context, ?string $responseContent, ?TransportExceptionInterface $exception): ?bool
                {
                    $this->fail('should not be called');
                }
            },
            2,
            $logger = new TestLogger()
        );

        $response = $client->request('GET', 'http://does.not.exists/foo-bar');

        try {
            $response->getHeaders();
        } catch (TransportExceptionInterface $e) {
            $this->assertSame('Could not resolve host "does.not.exists".', $e->getMessage());
        }
        $this->assertCount(2, $logger->logs);
        $this->assertSame('Try #{count} after {delay}ms: Could not resolve host "does.not.exists".', $logger->logs[0]);
    }

    public function testCancelOnTimeout()
    {
        $client = HttpClient::create();

        if ($client instanceof NativeHttpClient) {
            $this->markTestSkipped('NativeHttpClient cannot timeout before receiving headers');
        }

        $client = new RetryableHttpClient($client);

        $response = $client->request('GET', 'https://example.com/');

        foreach ($client->stream($response, 0) as $chunk) {
            $this->assertTrue($chunk->isTimeout());
            $response->cancel();
        }
    }

    public function testRetryWithDelay()
    {
        $retryAfter = '0.46';

        $client = new RetryableHttpClient(
            new MockHttpClient([
                new MockResponse('', [
                    'http_code' => 503,
                    'response_headers' => [
                        'retry-after' => $retryAfter,
                    ],
                ]),
                new MockResponse('', [
                    'http_code' => 200,
                ]),
            ]),
            new GenericRetryStrategy(),
            1,
            $logger = new class() extends TestLogger {
                public array $context = [];

                public function log($level, $message, array $context = []): void
                {
                    $this->context = $context;
                    parent::log($level, $message, $context);
                }
            }
        );

        $client->request('GET', 'http://example.com/foo-bar')->getContent();

        $delay = $logger->context['delay'] ?? null;

        $this->assertArrayHasKey('delay', $logger->context);
        $this->assertNotNull($delay);
        $this->assertSame((int) ($retryAfter * 1000), $delay);
    }

    public function testRetryOnErrorAssertContent()
    {
        $client = new RetryableHttpClient(
            new MockHttpClient([
               new MockResponse('', ['http_code' => 500]),
               new MockResponse('Test out content', ['http_code' => 200]),
            ]),
            new GenericRetryStrategy([500], 0),
            1
        );

        $response = $client->request('GET', 'http://example.com/foo-bar');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Test out content', $response->getContent());
        self::assertSame('Test out content', $response->getContent(), 'Content should be buffered');
    }

    public function testRetryOnTimeout()
    {
        $client = HttpClient::create();

        TestHttpServer::start();

        $strategy = new class() implements RetryStrategyInterface {
            public $isCalled = false;

            public function shouldRetry(AsyncContext $context, ?string $responseContent, ?TransportExceptionInterface $exception): ?bool
            {
                $this->isCalled = true;

                return false;
            }

            public function getDelay(AsyncContext $context, ?string $responseContent, ?TransportExceptionInterface $exception): int
            {
                return 0;
            }
        };
        $client = new RetryableHttpClient($client, $strategy);
        $response = $client->request('GET', 'http://localhost:8057/timeout-header', ['timeout' => 0.1]);

        try {
            $response->getStatusCode();
            $this->fail(TransportException::class.' expected');
        } catch (TransportException $e) {
        }

        $this->assertTrue($strategy->isCalled, 'The HTTP retry strategy should be called');
    }

    public function testRetryWithMultipleBaseUris()
    {
        $client = new RetryableHttpClient(
            new MockHttpClient([
                new MockResponse('', ['http_code' => 500]),
                new MockResponse('Hit on second uri', ['http_code' => 200]),
            ]),
            new GenericRetryStrategy([500], 0),
            1
        );

        $response = $client->request('GET', 'foo-bar', [
            'base_uri' => [
                'http://example.com/a/',
                'http://example.com/b/',
            ],
        ]);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('http://example.com/b/foo-bar', $response->getInfo('url'));
    }

    public function testMultipleBaseUrisAsOptions()
    {
        $client = new RetryableHttpClient(
            new MockHttpClient([
                new MockResponse('', ['http_code' => 500]),
                new MockResponse('Hit on second uri', ['http_code' => 200]),
            ]),
            new GenericRetryStrategy([500], 0),
            1
        );

        $client = $client->withOptions([
            'base_uri' => [
                'http://example.com/a/',
                'http://example.com/b/',
            ],
        ]);

        $response = $client->request('GET', 'foo-bar');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('http://example.com/b/foo-bar', $response->getInfo('url'));
    }

    public function testRetryWithMultipleBaseUrisShufflesNestedArray()
    {
        $client = new RetryableHttpClient(
            new MockHttpClient([
                new MockResponse('', ['http_code' => 500]),
                new MockResponse('Hit on second uri', ['http_code' => 200]),
            ]),
            new GenericRetryStrategy([500], 0),
            1
        );

        $response = $client->request('GET', 'foo-bar', [
            'base_uri' => [
                'http://example.com/a/',
                [
                    'http://example.com/b/',
                    'http://example.com/c/',
                ],
                'http://example.com/d/',
            ],
        ]);

        self::assertSame(200, $response->getStatusCode());
        self::assertMatchesRegularExpression('#^http://example.com/(b|c)/foo-bar$#', $response->getInfo('url'));
    }

    public function testRetryWithMultipleBaseUrisPreservesNonNestedOrder()
    {
        $client = new RetryableHttpClient(
            new MockHttpClient([
                new MockResponse('', ['http_code' => 500]),
                new MockResponse('', ['http_code' => 500]),
                new MockResponse('', ['http_code' => 500]),
                new MockResponse('Hit on second uri', ['http_code' => 200]),
            ]),
            new GenericRetryStrategy([500], 0),
            3
        );

        $response = $client->request('GET', 'foo-bar', [
            'base_uri' => [
                'http://example.com/a/',
                [
                    'http://example.com/b/',
                    'http://example.com/c/',
                ],
                'http://example.com/d/',
            ],
        ]);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('http://example.com/d/foo-bar', $response->getInfo('url'));
    }

    public function testMaxRetriesOption()
    {
        $client = new RetryableHttpClient(
            new MockHttpClient([
                new MockResponse('', ['http_code' => 500]),
                new MockResponse('', ['http_code' => 502]),
                new MockResponse('', ['http_code' => 200]),
            ]),
            new GenericRetryStrategy([500, 502], 0),
            3
        );

        $response = $client->request('GET', 'http://example.com/foo-bar', [
            'max_retries' => 1,
        ]);

        self::assertSame(502, $response->getStatusCode());
    }

    public function testMaxRetriesWithOptions()
    {
        $client = new RetryableHttpClient(
            new MockHttpClient([
                new MockResponse('', ['http_code' => 500]),
                new MockResponse('', ['http_code' => 502]),
                new MockResponse('', ['http_code' => 504]),
                new MockResponse('', ['http_code' => 200]),
            ]),
            new GenericRetryStrategy([500, 502, 504], 0),
            3
        );

        $client = $client->withOptions([
            'max_retries' => 2,
        ]);

        $response = $client->request('GET', 'http://example.com/foo-bar');

        self::assertSame(504, $response->getStatusCode());
    }
}
