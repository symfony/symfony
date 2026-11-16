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
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\HttpClient\Exception\ServerException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\NativeHttpClient;
use Symfony\Component\HttpClient\Response\AsyncContext;
use Symfony\Component\HttpClient\Response\AsyncResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\Retry\GenericRetryStrategy;
use Symfony\Component\HttpClient\Retry\RetryStrategyInterface;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\Test\TestHttpServer;

class RetryableHttpClientTest extends TestCase
{
    use TestServerPortTrait;

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

    public function testRetrySendsTheStreamBodyAgain()
    {
        $body = fopen('php://temp', 'r+');
        fwrite($body, 'skipped payload');
        fseek($body, 8);

        $sentBodies = [];
        $client = new RetryableHttpClient(new MockHttpClient(static function (string $method, string $url, array $options) use (&$sentBodies) {
            $sentBodies[] = self::readBody($options['body']);

            return new MockResponse('', ['http_code' => 1 === \count($sentBodies) ? 503 : 200]);
        }), new GenericRetryStrategy([503], 0), 1);

        $this->assertSame(200, $client->request('PUT', 'http://example.com/', ['body' => $body])->getStatusCode());
        $this->assertSame(['payload', 'payload'], $sentBodies);
    }

    public function testRetrySendsTheStreamsOfAMultipartBodyAgain()
    {
        $file = fopen('php://temp', 'r+');
        fwrite($file, 'payload');
        rewind($file);

        $sentBodies = [];
        $client = new RetryableHttpClient(new MockHttpClient(static function (string $method, string $url, array $options) use (&$sentBodies) {
            $sentBodies[] = self::readBody($options['body']);

            return new MockResponse('', ['http_code' => 1 === \count($sentBodies) ? 503 : 200]);
        }), new GenericRetryStrategy([503], 0), 1);

        $this->assertSame(200, $client->request('POST', 'http://example.com/', ['body' => ['form' => ['file' => $file]]])->getStatusCode());
        $this->assertCount(2, $sentBodies);
        $this->assertStringContainsString("\r\n\r\npayload\r\n", $sentBodies[1]);
    }

    public function testRetrySendsTheStreamsNestedInObjectsAgain()
    {
        $form = new \stdClass();
        $form->file = fopen('php://temp', 'r+');
        fwrite($form->file, 'payload');
        rewind($form->file);

        $sentBodies = [];
        $client = new RetryableHttpClient(new MockHttpClient(static function (string $method, string $url, array $options) use (&$sentBodies) {
            $sentBodies[] = self::readBody($options['body']);

            return new MockResponse('', ['http_code' => 1 === \count($sentBodies) ? 503 : 200]);
        }), new GenericRetryStrategy([503], 0), 1);

        $this->assertSame(200, $client->request('POST', 'http://example.com/', ['body' => ['form' => $form]])->getStatusCode());
        $this->assertCount(2, $sentBodies);
        $this->assertStringContainsString("\r\n\r\npayload\r\n", $sentBodies[1]);
    }

    public function testRetrySendsAResponseUsedAsBodyPartAgainOnlyWhenItIsBuffered()
    {
        foreach ([true, false] as $buffer) {
            $part = (new MockHttpClient(new MockResponse('payload')))->request('GET', 'http://example.com/file.txt', ['buffer' => $buffer]);

            $sentBodies = [];
            $client = new RetryableHttpClient(new MockHttpClient(static function (string $method, string $url, array $options) use (&$sentBodies) {
                $sentBodies[] = self::readBody($options['body']);

                return new MockResponse('', ['http_code' => 1 === \count($sentBodies) ? 503 : 200]);
            }), new GenericRetryStrategy([503], 0), 1);

            $this->assertSame($buffer ? 200 : 503, $client->request('POST', 'http://example.com/', ['body' => ['file' => $part]])->getStatusCode());
            $this->assertCount($buffer ? 2 : 1, $sentBodies);

            foreach ($sentBodies as $body) {
                $this->assertStringContainsString("\r\n\r\npayload\r\n", $body);
            }
        }
    }

    public function testRetrySendsTheBodiesThatAreBuiltForEachRequestAgain()
    {
        $bodies = [
            'generator closure' => static function () {
                yield 'pay';
                yield 'load';
            },
            'iterator' => new \ArrayIterator(['pay', 'load']),
        ];

        foreach ($bodies as $type => $body) {
            $sentBodies = [];
            $client = new RetryableHttpClient(new MockHttpClient(static function (string $method, string $url, array $options) use (&$sentBodies) {
                $sentBodies[] = self::readBody($options['body']);

                return new MockResponse('', ['http_code' => 1 === \count($sentBodies) ? 503 : 200]);
            }), new GenericRetryStrategy([503], 0), 1);

            $this->assertSame(200, $client->request('PUT', 'http://example.com/', ['body' => $body])->getStatusCode(), $type);
            $this->assertSame(['payload', 'payload'], $sentBodies, $type);
        }
    }

    public function testNoRetryWhenTheBodyCannotBeSentAgain()
    {
        $chunks = ['pay', 'load'];
        $bodies = [
            'generator' => (static function () {
                yield 'pay';
                yield 'load';
            })(),
            'closure' => static function () use (&$chunks) {
                return array_shift($chunks) ?? '';
            },
        ];

        foreach ($bodies as $type => $body) {
            $sentBodies = [];
            $client = new RetryableHttpClient(new MockHttpClient(static function (string $method, string $url, array $options) use (&$sentBodies) {
                $sentBodies[] = self::readBody($options['body']);

                return new MockResponse('', ['http_code' => 1 === \count($sentBodies) ? 503 : 200]);
            }), new GenericRetryStrategy([503], 0), 1);

            $this->assertSame(503, $client->request('PUT', 'http://example.com/', ['body' => $body])->getStatusCode(), $type);
            $this->assertSame(['payload'], $sentBodies, $type);
        }
    }

    public function testRetrySendsTheStreamBodyAgainWithCurl()
    {
        if (!\extension_loaded('curl')) {
            $this->markTestSkipped('This test requires the curl extension.');
        }

        TestHttpServer::start();

        foreach (['file' => tmpfile(), 'php://temp' => fopen('php://temp', 'r+')] as $type => $body) {
            fwrite($body, 'foo=0123456789');
            rewind($body);

            // Retrying on 200 makes the test server receive the body twice
            $client = new RetryableHttpClient(new CurlHttpClient(), new GenericRetryStrategy([200], 0), 1);
            $response = $client->request('POST', 'http://localhost:'.self::port().'/post', ['body' => $body]);

            $this->assertSame(['foo' => '0123456789', 'REQUEST_METHOD' => 'POST'], $response->toArray(), $type);
            $this->assertSame(1, $response->getInfo('retry_count'), $type);
        }
    }

    public function testRetrySendsTheBodyWhenTheFirstAttemptFailedBeforeReadingIt()
    {
        if (!\extension_loaded('curl')) {
            $this->markTestSkipped('This test requires the curl extension.');
        }

        TestHttpServer::start();

        [$writer, $stream] = stream_socket_pair('\\' === \DIRECTORY_SEPARATOR ? \STREAM_PF_INET : \STREAM_PF_UNIX, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP);
        fwrite($writer, 'foo=0123456789');
        fclose($writer);

        $chunks = ['foo=', '0123456789'];
        $bodies = [
            'generator' => (static function () {
                yield 'foo=';
                yield '0123456789';
            })(),
            'closure' => static function () use (&$chunks) {
                return array_shift($chunks) ?? '';
            },
            'stream that is not seekable' => $stream,
        ];

        foreach ($bodies as $type => $body) {
            $client = new RetryableHttpClient(new CurlHttpClient(), new GenericRetryStrategy([], 0), 1);
            $response = $client->request('POST', 'post', ['base_uri' => ['http://does.not.exists/', 'http://localhost:'.self::port().'/'], 'body' => $body]);

            $this->assertSame(['foo' => '0123456789', 'REQUEST_METHOD' => 'POST'], $response->toArray(), $type);
        }
    }

    public function testNoRetryWhenTheStreamBodyIsNotSeekable()
    {
        [$writer, $body] = stream_socket_pair('\\' === \DIRECTORY_SEPARATOR ? \STREAM_PF_INET : \STREAM_PF_UNIX, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP);
        fwrite($writer, 'payload');
        fclose($writer);

        $sentBodies = [];
        $client = new RetryableHttpClient(new MockHttpClient(static function (string $method, string $url, array $options) use (&$sentBodies) {
            $sentBodies[] = self::readBody($options['body']);

            return new MockResponse('', ['http_code' => 1 === \count($sentBodies) ? 503 : 200]);
        }), new GenericRetryStrategy([503], 0), 1);

        $this->assertSame(503, $client->request('PUT', 'http://example.com/', ['body' => $body])->getStatusCode());
        $this->assertSame(['payload'], $sentBodies);
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
        $this->assertSame('Try #1 after 0ms: Could not resolve host "does.not.exists".', $logger->logs[0]);
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
            $logger = new class extends TestLogger {
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

    #[DataProvider('provideRetryAfterDates')]
    public function testRetryAfterHttpDate(string $retryAfter, int $delay)
    {
        $mockClient = new MockHttpClient([
            new MockResponse('', ['http_code' => 503, 'response_headers' => ['Retry-After' => $retryAfter]]),
            new MockResponse(''),
        ]);
        $client = new RetryableHttpClient($mockClient, new GenericRetryStrategy(GenericRetryStrategy::DEFAULT_RETRY_STATUS_CODES, 100, 1, 0, 0), 1, $logger = new TestLogger());

        $client->request('GET', 'http://example.com/foo-bar')->getContent();

        $this->assertSame([\sprintf('Try #1 after %dms, status code: 503', $delay)], $logger->logs);
    }

    public static function provideRetryAfterDates(): iterable
    {
        yield 'HTTP-date in the past' => ['Thu, 01 Jan 1970 00:00:00 GMT', 0];
        yield 'not an HTTP-date' => ['yesterday', 100];
        yield 'mismatched weekday name' => ['Fri, 01 Jan 1970 00:00:00 GMT', 100];
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

        $strategy = new class implements RetryStrategyInterface {
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
        $response = $client->request('GET', 'http://localhost:'.self::port().'/timeout-header', ['timeout' => 0.1]);

        try {
            $response->getStatusCode();
            $this->fail(TransportException::class.' expected');
        } catch (TransportException $e) {
        }

        $this->assertTrue($strategy->isCalled, 'The HTTP retry strategy should be called');
    }

    public function testRetryDelayLongerThanTimeout()
    {
        TestHttpServer::start();

        $client = new RetryableHttpClient(HttpClient::create(), new GenericRetryStrategy([404], 1000, 1.0, 0, 0.0), 1);
        $response = $client->request('GET', 'http://localhost:'.self::port().'/404', ['timeout' => 0.5]);

        $time = hrtime(true);
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(1, $response->getInfo('retry_count'));
        $this->assertGreaterThanOrEqual(0.9, (hrtime(true) - $time) / 1E9);
    }

    public function testRetryAfterLongerThanMaxDuration()
    {
        TestHttpServer::start();

        $client = new RetryableHttpClient(HttpClient::create());
        $response = $client->request('GET', 'http://localhost:'.self::port().'/custom?status=503&headers[]=Retry-After:10', ['max_duration' => 1, 'timeout' => 0.5]);

        $time = hrtime(true);

        try {
            $response->getStatusCode();
            $this->fail(TransportException::class.' expected');
        } catch (TransportException $e) {
            $this->assertStringStartsWith('Max duration was reached for "http://localhost:'.self::port().'/custom', $e->getMessage());
        }

        $this->assertLessThan(5, (hrtime(true) - $time) / 1E9);
    }

    public function testMaxDurationCountsAllRetriesAndDelays()
    {
        TestHttpServer::start();

        $client = new RetryableHttpClient(HttpClient::create(), new GenericRetryStrategy([404], 400, 1.0, 0, 0.0), 5);
        $response = $client->request('GET', 'http://localhost:'.self::port().'/404', ['max_duration' => 1]);

        $time = hrtime(true);

        try {
            $response->getStatusCode();
            $this->fail(TransportException::class.' expected');
        } catch (TransportException $e) {
            $this->assertSame('Max duration was reached for "http://localhost:'.self::port().'/404".', $e->getMessage());
        }

        $this->assertLessThan(5, $response->getInfo('retry_count'));
        $this->assertLessThan(1.5, (hrtime(true) - $time) / 1E9);
    }

    public function testRetryAfterLongerThanMaxDurationWithMockResponses()
    {
        $client = new RetryableHttpClient(new MockHttpClient([
            new MockResponse('', ['http_code' => 503, 'response_headers' => ['Retry-After' => '3']]),
            new MockResponse('', ['http_code' => 200]),
        ]), null, 1);

        $time = hrtime(true);
        $response = $client->request('GET', 'http://example.com/foo-bar', ['max_duration' => 0.5]);

        try {
            $response->getStatusCode();
            $this->fail(TransportException::class.' expected');
        } catch (TransportException $e) {
            $this->assertSame('Max duration was reached for "http://example.com/foo-bar".', $e->getMessage());
        }

        $this->assertGreaterThanOrEqual(0.4, (hrtime(true) - $time) / 1E9);
        $this->assertLessThan(2, (hrtime(true) - $time) / 1E9);
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

    public function testWithOptionsKeepsTheBaseUrisOfTheOriginalClient()
    {
        $client = new RetryableHttpClient(new MockHttpClient(), new GenericRetryStrategy([500], 0), 1);
        $client = $client->withOptions(['base_uri' => ['http://example.com/a/', 'http://example.com/b/']]);

        $clone = $client->withOptions(['base_uri' => 'http://example.com/c/']);

        self::assertSame('http://example.com/c/foo-bar', $clone->request('GET', 'foo-bar')->getInfo('url'));
        self::assertSame('http://example.com/a/foo-bar', $client->request('GET', 'foo-bar')->getInfo('url'));

        $clone = $client->withOptions(['base_uri' => ['http://example.com/d/', 'http://example.com/e/']]);

        self::assertSame('http://example.com/d/foo-bar', $clone->request('GET', 'foo-bar')->getInfo('url'));
        self::assertSame('http://example.com/a/foo-bar', $client->request('GET', 'foo-bar')->getInfo('url'));
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

    public function testRetryOnTimeoutWithAsyncDecorator()
    {
        $client = HttpClient::create();

        TestHttpServer::start();

        $strategy = new class implements RetryStrategyInterface {
            public bool $isCalled = false;

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

        $client = new class($client) implements HttpClientInterface {
            use \Symfony\Component\HttpClient\AsyncDecoratorTrait;

            public function request(string $method, string $url, array $options = []): ResponseInterface
            {
                return new AsyncResponse($this->client, $method, $url, $options);
            }
        };

        $response = $client->request('GET', 'http://localhost:'.self::port().'/timeout-header', ['timeout' => 0.1]);

        try {
            $response->getStatusCode();
            $this->fail(TransportException::class.' expected');
        } catch (TransportException $e) {
        }

        $this->assertTrue($strategy->isCalled, 'The HTTP retry strategy should be called');
    }

    public function testRetryOnErrorWithAsyncDecorator()
    {
        $client = new MockHttpClient([
            new MockResponse('', ['http_code' => 500]),
            new MockResponse('OK', ['http_code' => 200]),
        ]);

        $client = new RetryableHttpClient($client, new GenericRetryStrategy([500], 0), 1);

        $client = new class($client) implements HttpClientInterface {
            use \Symfony\Component\HttpClient\AsyncDecoratorTrait;

            public function request(string $method, string $url, array $options = []): ResponseInterface
            {
                return new AsyncResponse($this->client, $method, $url, $options);
            }
        };

        $response = $client->request('GET', 'http://example.com/foo-bar');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('OK', $response->getContent());
    }

    public function testRetryOnTimeoutWithMultipleAsyncDecorators()
    {
        $client = HttpClient::create();

        TestHttpServer::start();

        $strategy = new class implements RetryStrategyInterface {
            public bool $isCalled = false;

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

        // Two nested async decorators without passthru around the RetryableHttpClient
        $client = new class($client) implements HttpClientInterface {
            use \Symfony\Component\HttpClient\AsyncDecoratorTrait;

            public function request(string $method, string $url, array $options = []): ResponseInterface
            {
                return new AsyncResponse($this->client, $method, $url, $options);
            }
        };
        $client = new class($client) implements HttpClientInterface {
            use \Symfony\Component\HttpClient\AsyncDecoratorTrait;

            public function request(string $method, string $url, array $options = []): ResponseInterface
            {
                return new AsyncResponse($this->client, $method, $url, $options);
            }
        };

        $response = $client->request('GET', 'http://localhost:'.self::port().'/timeout-header', ['timeout' => 0.1]);

        try {
            $response->getStatusCode();
            $this->fail(TransportException::class.' expected');
        } catch (TransportException $e) {
        }

        $this->assertTrue($strategy->isCalled, 'The HTTP retry strategy should be called with multiple decorators');
    }

    public function testRetryActuallyRetriesWithAsyncDecorator()
    {
        $client = HttpClient::create();

        TestHttpServer::start();

        $retryCount = 0;
        $strategy = new class($retryCount) implements RetryStrategyInterface {
            private int $retryCount;

            public function __construct(int &$retryCount)
            {
                $this->retryCount = &$retryCount;
            }

            public function shouldRetry(AsyncContext $context, ?string $responseContent, ?TransportExceptionInterface $exception): ?bool
            {
                ++$this->retryCount;

                return $this->retryCount < 2;
            }

            public function getDelay(AsyncContext $context, ?string $responseContent, ?TransportExceptionInterface $exception): int
            {
                return 0;
            }
        };

        $client = new RetryableHttpClient($client, $strategy, 2);

        $client = new class($client) implements HttpClientInterface {
            use \Symfony\Component\HttpClient\AsyncDecoratorTrait;

            public function request(string $method, string $url, array $options = []): ResponseInterface
            {
                return new AsyncResponse($this->client, $method, $url, $options);
            }
        };

        $response = $client->request('GET', 'http://localhost:'.self::port().'/timeout-header', ['timeout' => 0.1]);

        try {
            $response->getStatusCode();
            $this->fail(TransportException::class.' expected');
        } catch (TransportException $e) {
        }

        $this->assertSame(2, $retryCount, 'The request should have been retried once');
    }

    private static function readBody($body): string
    {
        if (\is_resource($body)) {
            return stream_get_contents($body);
        }

        if (!$body instanceof \Closure) {
            return $body;
        }

        $data = '';
        while ('' !== $chunk = $body(16372)) {
            $data .= $chunk;
        }

        return $data;
    }
}
