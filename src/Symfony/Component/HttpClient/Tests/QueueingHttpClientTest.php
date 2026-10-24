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
use Symfony\Component\HttpClient\Chunk\ErrorChunk;
use Symfony\Component\HttpClient\Exception\ServerException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\QueueingHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\Response\ResponseStream;
use Symfony\Component\HttpClient\Retry\GenericRetryStrategy;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

class QueueingHttpClientTest extends TestCase
{
    #[DataProvider('provideInvalidLimits')]
    public function testRejectsInvalidConcurrency(int $limit)
    {
        $this->expectException(\InvalidArgumentException::class);

        new QueueingHttpClient(new MockHttpClient(), $limit);
    }

    public static function provideInvalidLimits(): iterable
    {
        yield [0];
        yield [-1];
    }

    public function testRequestsWaitForSlot()
    {
        $mockClient = new MockHttpClient();
        $client = new QueueingHttpClient($mockClient, 2);
        $first = $client->request('GET', 'https://example.com/first');
        $second = $client->request('GET', 'https://example.com/second');
        $third = $client->request('POST', 'https://example.com/third', ['user_data' => 'third']);

        self::assertSame(2, $mockClient->getRequestsCount());
        self::assertSame(0, $third->getInfo('http_code'));
        self::assertSame('third', $third->getInfo('user_data'));
        self::assertSame('POST', $third->getInfo('http_method'));
        self::assertSame('https://example.com/third', $third->getInfo('url'));
        self::assertSame(0.0, $third->getInfo('start_time'));
        self::assertFalse($third->getInfo('canceled'));
        self::assertNull($third->getInfo('error'));
        self::assertSame(2, $mockClient->getRequestsCount());

        $first->cancel();
        $second->cancel();
        $third->cancel();
    }

    public function testReentrantRequestSharesConcurrencyLimit()
    {
        $mockClient = new MockHttpClient();
        $client = new QueueingHttpClient($mockClient, 1);
        $nested = null;
        $first = $client->request('GET', 'https://example.com/first', ['on_progress' => static function () use ($client, &$nested) {
            $nested ??= $client->request('GET', 'https://example.com/nested');
        }]);

        try {
            self::assertSame(1, $mockClient->getRequestsCount());
            self::assertSame('', $nested->getContent());
            self::assertSame('', $first->getContent());
        } finally {
            $first->cancel();
            $nested->cancel();
        }
    }

    public function testDefaultConcurrency()
    {
        $mockClient = new MockHttpClient();
        $client = new QueueingHttpClient($mockClient);
        $responses = [];

        for ($i = 0; $i < 101; ++$i) {
            $responses[] = $client->request('GET', 'https://example.com/'.$i);
        }

        self::assertSame(100, $mockClient->getRequestsCount());

        foreach (array_reverse($responses) as $response) {
            $response->cancel();
        }
    }

    public function testCancelQueuedResponseDoesNotSendRequest()
    {
        $mockClient = new MockHttpClient(new MockResponse('first'));
        $client = new QueueingHttpClient($mockClient, 1);
        $first = $client->request('GET', 'https://example.com/first');
        $second = $client->request('GET', 'https://example.com/second');

        $second->cancel();

        self::assertTrue($second->getInfo('canceled'));
        self::assertNotNull($second->getInfo('error'));
        self::assertSame('first', $first->getContent());
        self::assertSame(1, $mockClient->getRequestsCount());
    }

    public function testCancelActiveResponseReleasesSlot()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('first'),
            new MockResponse('second'),
        ]);
        $client = new QueueingHttpClient($mockClient, 1);
        $first = $client->request('GET', 'https://example.com/first');
        $second = $client->request('GET', 'https://example.com/second');

        $first->cancel();

        self::assertSame('second', $second->getContent());
        self::assertSame(2, $mockClient->getRequestsCount());
        self::assertTrue($first->getInfo('canceled'));
    }

    public function testReadingHeadersDoesNotReleaseSlot()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('first'),
            new MockResponse('second'),
        ]);
        $client = new QueueingHttpClient($mockClient, 1);
        $first = $client->request('GET', 'https://example.com/first');
        $second = $client->request('GET', 'https://example.com/second');

        self::assertSame(200, $first->getStatusCode());
        self::assertSame(1, $mockClient->getRequestsCount());
        self::assertSame(0, $second->getInfo('http_code'));
        self::assertSame('second', $second->getContent());
        self::assertSame('first', $first->getContent());
    }

    public function testReadLaterResponsePreservesEarlierBody()
    {
        $client = new QueueingHttpClient(new MockHttpClient([
            new MockResponse(['first ', 'body']),
            new MockResponse('second body'),
        ]), 1);
        $first = $client->request('GET', 'https://example.com/first');
        $second = $client->request('GET', 'https://example.com/second');

        self::assertSame('second body', $second->getContent());
        self::assertSame('first body', $first->getContent());
        self::assertSame('first body', $first->getContent());
    }

    public function testReadLaterResponsePreservesEarlierUnbufferedStream()
    {
        $client = new QueueingHttpClient(new MockHttpClient([
            new MockResponse(['first ', 'body']),
            new MockResponse('second body'),
        ]), 1);
        $first = $client->request('GET', 'https://example.com/first', ['buffer' => false]);
        $second = $client->request('GET', 'https://example.com/second');

        self::assertSame('second body', $second->getContent());

        $body = '';
        $firstChunks = $lastChunks = 0;
        foreach ($client->stream($first) as $response => $chunk) {
            self::assertSame($first, $response);
            if ($chunk->isFirst()) {
                ++$firstChunks;
                self::assertSame(200, $response->getStatusCode());
            } elseif ($chunk->isLast()) {
                ++$lastChunks;
            } else {
                $body .= $chunk->getContent();
            }
        }

        self::assertSame('first body', $body);
        self::assertSame(1, $firstChunks);
        self::assertSame(1, $lastChunks);
    }

    public function testStreamRefillsSlotsAsResponsesComplete()
    {
        $mockClient = new class(static fn ($method, $url) => new MockResponse(array_fill(0, str_ends_with($url, '/0') ? 8 : 1, 'body'))) extends MockHttpClient {
            public array $responses = [];
            public int $peakActiveRequests = 0;
            public bool $thirdStartedBeforeFirstCompleted = false;

            public function request(string $method, string $url, array $options = []): ResponseInterface
            {
                $activeRequests = 0;
                foreach ($this->responses as $response) {
                    if (null === $response->getInfo('trailers') && null === $response->getInfo('error') && !$response->getInfo('canceled')) {
                        ++$activeRequests;
                    }
                }
                $this->peakActiveRequests = max($this->peakActiveRequests, 1 + $activeRequests);
                if (2 === \count($this->responses)) {
                    $this->thirdStartedBeforeFirstCompleted = null === $this->responses[0]->getInfo('trailers');
                }

                return $this->responses[] = parent::request($method, $url, $options);
            }
        };
        $client = new QueueingHttpClient($mockClient, 2);
        $responses = (static function () use ($client) {
            for ($i = 0; $i < 17; ++$i) {
                yield $client->request('GET', 'https://example.com/'.$i, ['user_data' => $i]);
            }
        })();

        $bodies = [];
        foreach ($client->stream($responses) as $response => $chunk) {
            if ($chunk->isFirst()) {
                self::assertSame(200, $response->getStatusCode());
            } elseif ($chunk->isLast()) {
                $bodies[$response->getInfo('user_data')] = $response->getContent();
            }
        }

        self::assertCount(17, $bodies);
        self::assertSame(str_repeat('body', 8), $bodies[0]);
        self::assertSame('body', $bodies[16]);
        self::assertSame(2, $mockClient->peakActiveRequests);
        self::assertTrue($mockClient->thirdStartedBeforeFirstCompleted);
    }

    public function testWithOptionsSharesConcurrencyLimit()
    {
        $requests = [];
        $mockClient = new MockHttpClient(static function ($method, $url, $options) use (&$requests) {
            $requests[] = [$method, $url, $options];

            return new MockResponse($url);
        });
        $client = new QueueingHttpClient($mockClient, 1);
        $clone = $client->withOptions(['headers' => ['X-Queue' => 'clone'], 'user_data' => 'clone']);
        $first = $client->request('GET', 'https://example.com/first');
        $second = $clone->request('GET', 'https://example.com/second');
        $third = $client->request('GET', 'https://example.com/third');

        self::assertCount(1, $requests);
        self::assertSame('clone', $second->getInfo('user_data'));
        self::assertNull($third->getInfo('user_data'));
        self::assertSame('https://example.com/second', $second->getContent());
        self::assertSame('https://example.com/first', $first->getContent());
        self::assertCount(2, $requests);
        self::assertContains('X-Queue: clone', $requests[1][2]['headers']);

        $third->cancel();
    }

    public function testEarlierTransportFailureDoesNotPreventLaterRequest()
    {
        $client = new QueueingHttpClient(new MockHttpClient([
            new MockResponse((static function () {
                yield new TransportException('first failed');
            })()),
            new MockResponse('second'),
        ]), 1);
        $first = $client->request('GET', 'https://example.com/first');
        $second = $client->request('GET', 'https://example.com/second');

        self::assertSame('second', $second->getContent());

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('first failed');
        $first->getContent();
    }

    public function testEarlierHttpFailureDoesNotPreventLaterRequest()
    {
        $client = new QueueingHttpClient(new MockHttpClient([
            new MockResponse('first', ['http_code' => 500]),
            new MockResponse('second'),
        ]), 1);
        $first = $client->request('GET', 'https://example.com/first');
        $second = $client->request('GET', 'https://example.com/second');

        self::assertSame('second', $second->getContent());
        self::assertSame(500, $first->getStatusCode());

        $this->expectException(ServerException::class);
        $first->getContent();
    }

    public function testDroppedQueuedResponseChecksItsStatus()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('first'),
            new MockResponse('failure', ['http_code' => 500]),
        ]);
        $client = new QueueingHttpClient($mockClient, 1);
        $first = $client->request('GET', 'https://example.com/first');

        try {
            $client->request('GET', 'https://example.com/failure');
            self::fail('The dropped response should throw on HTTP errors.');
        } catch (ServerException $e) {
            self::assertSame(500, $e->getResponse()->getStatusCode());
        }

        self::assertSame(2, $mockClient->getRequestsCount());
        self::assertSame('first', $first->getContent());
    }

    public function testTimeoutDoesNotReleaseSlot()
    {
        $mockClient = new MockHttpClient([
            new MockResponse(['', 'first']),
            new MockResponse('second'),
        ]);
        $client = new QueueingHttpClient($mockClient, 1);
        $first = $client->request('GET', 'https://example.com/first');
        $second = $client->request('GET', 'https://example.com/second');

        $timedOut = false;
        foreach ($client->stream($first, 0.0) as $chunk) {
            if ($chunk->isTimeout()) {
                $timedOut = true;
                break;
            }
            if ($chunk->isFirst()) {
                $first->getStatusCode();
            }
        }

        self::assertTrue($timedOut);
        self::assertSame(1, $mockClient->getRequestsCount());
        self::assertSame(0, $second->getInfo('http_code'));

        $first->cancel();

        self::assertSame('second', $second->getContent());
    }

    public function testFailedAdmissionDoesNotBlockOtherRequests()
    {
        $mockClient = new MockHttpClient(static function ($method, $url) {
            if (str_ends_with($url, '/failure')) {
                throw new TransportException('admission failed');
            }

            return new MockResponse($url);
        });
        $client = new QueueingHttpClient($mockClient, 1);
        $first = $client->request('GET', 'https://example.com/first');
        $failure = $client->request('GET', 'https://example.com/failure');
        $last = $client->request('GET', 'https://example.com/last');

        try {
            $failure->getContent();
            self::fail('Admission should have failed.');
        } catch (TransportExceptionInterface $e) {
            self::assertSame('admission failed', $e->getMessage());
        }

        self::assertSame('https://example.com/last', $last->getContent());
        self::assertSame('https://example.com/first', $first->getContent());
        self::assertSame('admission failed', $failure->getInfo('error'));
    }

    public function testStreamTransportErrorDoesNotBlockOtherResponses()
    {
        $client = new QueueingHttpClient(new MockHttpClient([
            new MockResponse((static function () {
                yield new TransportException('first failed');
            })()),
            new MockResponse('second'),
        ]), 1);
        $first = $client->request('GET', 'https://example.com/first');
        $second = $client->request('GET', 'https://example.com/second');

        $errors = [];
        $completed = [];
        foreach ($client->stream([$first, $second]) as $response => $chunk) {
            if (null !== $chunk->getError()) {
                try {
                    $chunk->getContent();
                    self::fail('The error chunk should throw.');
                } catch (TransportExceptionInterface $e) {
                    self::assertSame($first, $response);
                    $errors[] = $e->getMessage();
                }
            } elseif ($chunk->isFirst()) {
                self::assertSame(200, $response->getStatusCode());
            } elseif ($chunk->isLast()) {
                $completed[] = $response->getContent();
            }
        }

        self::assertSame(['first failed'], $errors);
        self::assertSame(['second'], $completed);
    }

    public function testCancelWhileStreamingStartsNextRequest()
    {
        $mockClient = new MockHttpClient([
            new MockResponse(['first ', 'body']),
            new MockResponse('second'),
        ]);
        $client = new QueueingHttpClient($mockClient, 1);
        $first = $client->request('GET', 'https://example.com/first');
        $second = $client->request('GET', 'https://example.com/second');

        $completed = [];
        foreach ($client->stream([$first, $second]) as $response => $chunk) {
            if ($chunk->isFirst()) {
                $response->getStatusCode();
                if ($response === $first) {
                    $response->cancel();
                }
            } elseif ($chunk->isLast()) {
                $completed[] = $response->getContent();
            }
        }

        self::assertSame(['second'], $completed);
        self::assertTrue($first->getInfo('canceled'));
        self::assertSame(2, $mockClient->getRequestsCount());
    }

    public function testStreamWithoutResponses()
    {
        $client = new QueueingHttpClient(new MockHttpClient(), 1);

        self::assertSame([], iterator_to_array($client->stream([])));
    }

    public function testStreamDoesNotRepeatCompletedResponse()
    {
        $client = new QueueingHttpClient(new MockHttpClient(new MockResponse('body')), 1);
        $response = $client->request('GET', 'https://example.com/');

        self::assertSame('body', $response->getContent());

        $lastChunks = 0;
        foreach ($client->stream([$response, $response]) as $chunk) {
            self::assertTrue($chunk->isLast());
            ++$lastChunks;
        }

        self::assertSame(1, $lastChunks);
    }

    public function testBufferCallbackReceivesResponseHeaders()
    {
        $headers = null;
        $client = new QueueingHttpClient(new MockHttpClient(new MockResponse('{"answer":42}', ['response_headers' => ['Content-Type: application/json']])), 1);
        $response = $client->request('GET', 'https://example.com/', ['buffer' => static function (array $responseHeaders) use (&$headers) {
            $headers = $responseHeaders;

            return true;
        }]);

        self::assertSame(['answer' => 42], $response->toArray());
        self::assertSame(['content-type' => ['application/json']], $headers);
    }

    public function testBufferResourceReceivesResponseBody()
    {
        $buffer = fopen('php://temp', 'w+');
        $client = new QueueingHttpClient(new MockHttpClient(new MockResponse(['response ', 'body'])), 1);
        $response = $client->request('GET', 'https://example.com/', ['buffer' => $buffer]);

        self::assertSame('response body', $response->getContent());
        rewind($buffer);
        self::assertSame('response body', stream_get_contents($buffer));
    }

    public function testCompletedResponseKeepsInfoAndBufferCallback()
    {
        $client = new QueueingHttpClient(new MockHttpClient([
            new MockResponse('first', [
                'response_headers' => ['Content-Type: text/plain'],
                'debug' => 'first debug',
                'trailers' => ['x-check' => ['complete']],
            ]),
            new MockResponse('second'),
        ]), 1);
        $headers = null;
        $first = $client->request('GET', 'https://example.com/first', ['buffer' => static function (array $responseHeaders) use (&$headers) {
            $headers = $responseHeaders;

            return true;
        }]);
        $second = $client->request('GET', 'https://example.com/second');

        self::assertSame('second', $second->getContent());
        self::assertSame(['x-check' => ['complete']], $first->getInfo('trailers'));
        self::assertSame('first debug', $first->getInfo('debug'));
        self::assertSame(200, $first->getInfo('http_code'));
        self::assertSame('https://example.com/first', $first->getInfo('url'));
        self::assertSame('first', $first->getContent());
        self::assertSame(['content-type' => ['text/plain']], $headers);
        self::assertSame($headers, $first->getHeaders());
    }

    public function testToStreamPreservesEarlierUnbufferedBody()
    {
        $client = new QueueingHttpClient(new MockHttpClient([
            new MockResponse(['first ', 'body'], ['http_code' => 500]),
            new MockResponse('second'),
        ]), 1);
        $first = $client->request('GET', 'https://example.com/first', ['buffer' => false]);
        $second = $client->request('GET', 'https://example.com/second');

        self::assertSame('second', $second->getContent());

        $stream = $first->toStream(false);

        self::assertSame('first body', stream_get_contents($stream));
        self::assertSame($first, stream_get_meta_data($stream)['wrapper_data']->getResponse());
        self::assertSame(500, $first->getStatusCode());
    }

    public function testBufferCallbackFailureReleasesSlot()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('first'),
            new MockResponse('second'),
        ]);
        $client = new QueueingHttpClient($mockClient, 1);
        $first = $client->request('GET', 'https://example.com/first', ['buffer' => static function () {
            throw new \RuntimeException('buffer failed');
        }]);
        $second = $client->request('GET', 'https://example.com/second');

        try {
            try {
                foreach ($client->stream($first) as $chunk) {
                }
                self::fail('The buffer callback should have failed.');
            } catch (\RuntimeException $e) {
                self::assertSame('buffer failed', $e->getMessage());
            }

            self::assertSame('buffer failed', $first->getInfo('error'));
            self::assertSame('second', $second->getContent());
            self::assertSame(2, $mockClient->getRequestsCount());
        } finally {
            $first->cancel();
            $second->cancel();
        }
    }

    public function testImmediateAdmissionFailureDoesNotRetryOnDestruct()
    {
        $attempts = 0;
        $mockClient = new MockHttpClient(static function () use (&$attempts) {
            ++$attempts;
            throw new TransportException('admission failed');
        });
        $client = new QueueingHttpClient($mockClient, 1);

        try {
            $client->request('GET', 'https://example.com/failure');
            self::fail('Admission should have failed.');
        } catch (TransportExceptionInterface $e) {
            self::assertSame('admission failed', $e->getMessage());
        }

        self::assertSame(1, $attempts);
    }

    public function testTimeoutIncludesQueuedResponses()
    {
        $mockClient = new MockHttpClient([
            new MockResponse(['', 'first']),
            new MockResponse('second'),
        ]);
        $client = new QueueingHttpClient($mockClient, 1);
        $first = $client->request('GET', 'https://example.com/first', ['user_data' => 'first']);
        $second = $client->request('GET', 'https://example.com/second', ['user_data' => 'second']);

        $timedOut = [];
        foreach ($client->stream([$first, $second], 0.0) as $response => $chunk) {
            if ($chunk->isTimeout()) {
                $timedOut[] = $response->getInfo('user_data');
            } elseif ($chunk->isFirst()) {
                $response->getStatusCode();
            }
        }

        self::assertSame(['first', 'second'], $timedOut);
        self::assertSame(1, $mockClient->getRequestsCount());
        self::assertSame('first', $first->getContent());
        self::assertSame('second', $second->getContent());
    }

    public function testNonBlockingStreamDrainsReadyResponses()
    {
        $client = new QueueingHttpClient(new MockHttpClient([
            new MockResponse(['first ', 'body']),
            new MockResponse(['second ', 'body']),
        ]), 2);
        $responses = [
            $client->request('GET', 'https://example.com/first'),
            $client->request('GET', 'https://example.com/second'),
        ];

        $bodies = [];
        foreach ($client->stream($responses, 0.0) as $response => $chunk) {
            self::assertFalse($chunk->isTimeout());
            if ($chunk->isFirst()) {
                $response->getStatusCode();
            } elseif ($chunk->isLast()) {
                $bodies[] = $response->getContent();
            }
        }

        self::assertSame(['first body', 'second body'], $bodies);
    }

    public function testNegativeTimeoutAllowsIdlePolling()
    {
        $client = new QueueingHttpClient(new MockHttpClient(new MockResponse(['', 'body'])), 1);
        $response = $client->request('GET', 'https://example.com/', ['timeout' => -1]);

        self::assertSame('body', $response->getContent());
    }

    #[DataProvider('provideAdmissionAfterTimeout')]
    public function testNewAdmissionStartsWithFreshTimeout(bool $deferred)
    {
        $mockClient = new class(static fn () => new MockResponse('fresh body')) extends MockHttpClient {
            private array $polled = [];

            public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
            {
                $responses = $responses instanceof ResponseInterface ? [$responses] : [...$responses];
                $response = $responses[0];
                $id = spl_object_id($response);

                if (str_ends_with($response->getInfo('url'), '/old') || !isset($this->polled[$id])) {
                    $this->polled[$id] = true;

                    return new ResponseStream((static function () use ($response) {
                        usleep(1000);
                        yield $response => new ErrorChunk(0, 'Waiting for headers.');
                    })());
                }

                return parent::stream($responses, $timeout);
            }
        };
        $client = new QueueingHttpClient($mockClient, 1);
        $old = $client->request('GET', 'https://example.com/old', ['timeout' => 0.001]);
        if ($deferred) {
            $fresh = $client->request('GET', 'https://example.com/fresh', ['timeout' => 0.05]);
        }

        try {
            $old->getStatusCode();
            self::fail('The first request should time out.');
        } catch (TransportExceptionInterface $e) {
            self::assertStringContainsString('Idle timeout reached', $e->getMessage());
        }

        usleep(70000);
        if (!$deferred) {
            $fresh = $client->request('GET', 'https://example.com/fresh', ['timeout' => 0.05]);
        }

        self::assertSame('fresh body', $fresh->getContent());
    }

    public static function provideAdmissionAfterTimeout(): iterable
    {
        yield 'immediate' => [false];
        yield 'deferred' => [true];
    }

    public function testCompletedResponsesAreReleasedWhileStreaming()
    {
        $client = new QueueingHttpClient(new MockHttpClient(new \ArrayIterator(array_fill(0, 8, static fn () => new MockResponse('body')))), 2);
        $references = [];
        $responses = (static function () use ($client, &$references) {
            for ($i = 0; $i < 8; ++$i) {
                $response = $client->request('GET', 'https://example.com/'.$i, ['user_data' => $i]);
                $references[$i] = \WeakReference::create($response);
                yield $response;
                unset($response);
            }
        })();

        $completed = [];
        foreach ($client->stream($responses) as $response => $chunk) {
            foreach ($completed as $id) {
                self::assertNull($references[$id]->get());
            }

            if ($chunk->isFirst()) {
                $response->getStatusCode();
            } elseif ($chunk->isLast()) {
                $completed[] = $response->getInfo('user_data');
                unset($response);
            }
        }

        self::assertCount(8, $completed);
    }

    #[DataProvider('provideRetryComposition')]
    public function testRetryableClientComposition(bool $queueRetries)
    {
        $attempts = [];
        $mockClient = new MockHttpClient(static function ($method, $url) use (&$attempts) {
            $attempt = $attempts[$url] = 1 + ($attempts[$url] ?? 0);

            return new MockResponse($url, ['http_code' => 1 === $attempt ? 503 : 200]);
        });
        $strategy = new GenericRetryStrategy([503], 0);
        $client = $queueRetries ? new RetryableHttpClient(new QueueingHttpClient($mockClient, 1), $strategy, 1) : new QueueingHttpClient(new RetryableHttpClient($mockClient, $strategy, 1), 1);
        $first = $client->request('GET', 'https://example.com/first');
        $second = $client->request('GET', 'https://example.com/second');

        self::assertSame('https://example.com/second', $second->getContent());
        self::assertSame('https://example.com/first', $first->getContent());
        self::assertSame(['https://example.com/first' => 2, 'https://example.com/second' => 2], $attempts);
    }

    public static function provideRetryComposition(): iterable
    {
        yield 'queue outside retry' => [false];
        yield 'queue inside retry' => [true];
    }
}
