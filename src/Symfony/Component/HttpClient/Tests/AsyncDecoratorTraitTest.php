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

use Symfony\Component\HttpClient\AsyncDecoratorTrait;
use Symfony\Component\HttpClient\DecoratorTrait;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\NativeHttpClient;
use Symfony\Component\HttpClient\Response\AsyncContext;
use Symfony\Component\HttpClient\Response\AsyncResponse;
use Symfony\Contracts\HttpClient\ChunkInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class AsyncDecoratorTraitTest extends NativeHttpClientTest
{
    protected function getHttpClient(string $testCase, ?\Closure $chunkFilter = null, ?HttpClientInterface $decoratedClient = null): HttpClientInterface
    {
        if ('testHandleIsRemovedOnException' === $testCase) {
            $this->markTestSkipped("AsyncDecoratorTrait doesn't cache handles");
        }

        if ('testTimeoutOnDestruct' === $testCase) {
            return HttpClient::create();
        }

        $chunkFilter ??= static function (ChunkInterface $chunk, AsyncContext $context) { yield $chunk; };

        return new class($decoratedClient ?? parent::getHttpClient($testCase), $chunkFilter) implements HttpClientInterface {
            use AsyncDecoratorTrait;

            private ?\Closure $chunkFilter;

            public function __construct(HttpClientInterface $client, ?\Closure $chunkFilter = null)
            {
                $this->chunkFilter = $chunkFilter;
                $this->client = $client;
            }

            public function request(string $method, string $url, array $options = []): ResponseInterface
            {
                return new AsyncResponse($this->client, $method, $url, $options, $this->chunkFilter);
            }
        };
    }

    public function testTimeoutOnDestruct()
    {
        if (HttpClient::create() instanceof NativeHttpClient) {
            $this->markTestSkipped('NativeHttpClient doesn\'t support opening concurrent requests.');
        }

        HttpClientTestCase::testTimeoutOnDestruct();
    }

    public function testRetry404()
    {
        $client = $this->getHttpClient(__FUNCTION__, function (ChunkInterface $chunk, AsyncContext $context) {
            $this->assertTrue($chunk->isFirst());
            $this->assertSame(404, $context->getStatusCode());
            $context->getResponse()->cancel();
            $context->replaceRequest('GET', 'http://localhost:8057/');
            $context->passthru();
        });

        $response = $client->request('GET', 'http://localhost:8057/404');

        foreach ($client->stream($response) as $chunk) {
        }
        $this->assertTrue($chunk->isLast());
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testRetry404WithThrow()
    {
        $client = $this->getHttpClient(__FUNCTION__, function (ChunkInterface $chunk, AsyncContext $context) {
            $this->assertTrue($chunk->isFirst());
            $this->assertSame(404, $context->getStatusCode());
            $context->getResponse()->cancel();
            $context->replaceRequest('GET', 'http://localhost:8057/404');
            $context->passthru();
        });

        $response = $client->request('GET', 'http://localhost:8057/404');

        $this->expectException(ClientExceptionInterface::class);
        $response->getContent(true);
    }

    public function testRetryTransportError()
    {
        $client = $this->getHttpClient(__FUNCTION__, function (ChunkInterface $chunk, AsyncContext $context) {
            try {
                if ($chunk->isFirst()) {
                    $this->assertSame(200, $context->getStatusCode());
                }
            } catch (TransportExceptionInterface $e) {
                $context->getResponse()->cancel();
                $context->replaceRequest('GET', 'http://localhost:8057/');
                $context->passthru();
            }
        });

        $response = $client->request('GET', 'http://localhost:8057/chunked-broken');

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testJsonTransclusion()
    {
        $client = $this->getHttpClient(__FUNCTION__, function (ChunkInterface $chunk, AsyncContext $context) {
            if ('' === $content = $chunk->getContent()) {
                yield $chunk;

                return;
            }

            $this->assertSame('{"documents":[{"id":"\/json\/1"},{"id":"\/json\/2"},{"id":"\/json\/3"}]}', $content);

            $steps = preg_split('{\{"id":"\\\/json\\\/(\d)"\}}', $content, -1, \PREG_SPLIT_DELIM_CAPTURE);
            $steps[7] = $context->getResponse();
            $steps[1] = $context->replaceRequest('GET', 'http://localhost:8057/json/1');
            $steps[3] = $context->replaceRequest('GET', 'http://localhost:8057/json/2');
            $steps[5] = $context->replaceRequest('GET', 'http://localhost:8057/json/3');

            yield $context->createChunk(array_shift($steps));

            $context->replaceResponse(array_shift($steps));
            $context->passthru(static function (ChunkInterface $chunk, AsyncContext $context) use (&$steps) {
                if ($chunk->isFirst()) {
                    return;
                }

                if ($steps && $chunk->isLast()) {
                    $chunk = $context->createChunk(array_shift($steps));
                    $context->replaceResponse(array_shift($steps));
                }

                yield $chunk;
            });
        });

        $response = $client->request('GET', 'http://localhost:8057/json');

        $this->assertSame('{"documents":[{"title":"\/json\/1"},{"title":"\/json\/2"},{"title":"\/json\/3"}]}', $response->getContent());
    }

    public function testPreflightRequest()
    {
        $client = new class(parent::getHttpClient(__FUNCTION__)) implements HttpClientInterface {
            use AsyncDecoratorTrait;

            public function request(string $method, string $url, array $options = []): ResponseInterface
            {
                $chunkFilter = static function (ChunkInterface $chunk, AsyncContext $context) use ($method, $url, $options) {
                    $context->replaceRequest($method, $url, $options);
                    $context->passthru();
                };

                return new AsyncResponse($this->client, 'GET', 'http://localhost:8057', $options, $chunkFilter);
            }
        };

        $response = $client->request('GET', 'http://localhost:8057/json');

        $this->assertSame('{"documents":[{"id":"\/json\/1"},{"id":"\/json\/2"},{"id":"\/json\/3"}]}', $response->getContent());
        $this->assertSame('http://localhost:8057/', $response->getInfo('previous_info')[0]['url']);
    }

    public function testProcessingHappensOnce()
    {
        $lastChunks = 0;
        $client = $this->getHttpClient(__FUNCTION__, function (ChunkInterface $chunk, AsyncContext $context) use (&$lastChunks) {
            $lastChunks += $chunk->isLast();

            yield $chunk;
        });

        $response = $client->request('GET', 'http://localhost:8057/');

        foreach ($client->stream($response) as $chunk) {
        }
        $this->assertTrue($chunk->isLast());
        $this->assertSame(1, $lastChunks);

        $chunk = null;
        foreach ($client->stream($response) as $chunk) {
        }
        $this->assertTrue($chunk->isLast());
        $this->assertSame(1, $lastChunks);
    }

    public function testLastChunkIsYieldOnHttpExceptionAtDestructTime()
    {
        $lastChunk = null;
        $client = $this->getHttpClient(__FUNCTION__, function (ChunkInterface $chunk, AsyncContext $context) use (&$lastChunk) {
            $lastChunk = $chunk;

            yield $chunk;
        });

        try {
            $client->request('GET', 'http://localhost:8057/404');
            $this->fail(ClientExceptionInterface::class.' expected');
        } catch (ClientExceptionInterface $e) {
        }

        $this->assertTrue($lastChunk->isLast());
    }

    public function testBufferPurePassthru()
    {
        $client = $this->getHttpClient(__FUNCTION__, function (ChunkInterface $chunk, AsyncContext $context) {
            $context->passthru();

            yield $chunk;
        });

        $response = $client->request('GET', 'http://localhost:8057/');

        $this->assertStringContainsString('SERVER_PROTOCOL', $response->getContent());
        $this->assertStringContainsString('HTTP_HOST', $response->getContent());
    }

    public function testRetryTimeout()
    {
        $client = $this->getHttpClient(__FUNCTION__, function (ChunkInterface $chunk, AsyncContext $context) {
            static $cpt = 0;
            try {
                $this->assertTrue($chunk->isTimeout());
                yield $chunk;
            } catch (TransportExceptionInterface $e) {
                if ($cpt++ < 3) {
                    $context->getResponse()->cancel();
                    $context->replaceRequest('GET', 'http://localhost:8057/timeout-header', ['timeout' => 0.1]);
                } else {
                    $context->passthru();
                    $context->getResponse()->cancel();
                    $context->replaceRequest('GET', 'http://localhost:8057/timeout-header', ['timeout' => 10]);
                }
            }
        });

        $response = $client->request('GET', 'http://localhost:8057/timeout-header', ['timeout' => 0.1]);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testRecurciveStream()
    {
        $client = new class(parent::getHttpClient(__FUNCTION__)) implements HttpClientInterface {
            use AsyncDecoratorTrait;

            public function request(string $method, string $url, array $options = []): ResponseInterface
            {
                return new AsyncResponse($this->client, $method, $url, $options);
            }
        };

        $response = $client->request('GET', 'http://localhost:8057/json');
        $content = '';
        foreach ($client->stream($response) as $chunk) {
            $content .= $chunk->getContent();
            foreach ($client->stream($response) as $chunk) {
                $content .= $chunk->getContent();
            }
        }

        $this->assertSame('{"documents":[{"id":"\/json\/1"},{"id":"\/json\/2"},{"id":"\/json\/3"}]}', $content);
    }

    public function testInfoPassToDecorator()
    {
        $lastInfo = null;
        $options = ['on_progress' => function (int $dlNow, int $dlSize, array $info) use (&$lastInfo) {
            $lastInfo = $info;
        }];
        $client = $this->getHttpClient(__FUNCTION__, function (ChunkInterface $chunk, AsyncContext $context) use ($options) {
            $context->setInfo('foo', 'test');
            $context->getResponse()->cancel();
            $context->replaceRequest('GET', 'http://localhost:8057/', $options);
            $context->passthru();
        });

        $client->request('GET', 'http://localhost:8057')->getContent();
        $this->assertArrayHasKey('foo', $lastInfo);
        $this->assertSame('test', $lastInfo['foo']);
        $this->assertArrayHasKey('previous_info', $lastInfo);
    }

    public function testMultipleYieldInInitializer()
    {
        $client = $this->getHttpClient(__FUNCTION__, function (ChunkInterface $chunk, AsyncContext $context) {
            static $first;
            if ($chunk->isFirst()) {
                $first = $chunk;

                return;
            }
            $context->passthru();
            yield $first;
            yield $context->createChunk('injectedFoo');
            yield $chunk;
        });

        $response = $client->request('GET', 'http://localhost:8057/404', ['timeout' => 0.1]);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringContainsString('injectedFoo', $response->getContent(false));
    }

    public function testConsumingDecoratedClient()
    {
        $client = $this->getHttpClient(__FUNCTION__, null, new class(parent::getHttpClient(__FUNCTION__)) implements HttpClientInterface {
            use DecoratorTrait;

            public function request(string $method, string $url, array $options = []): ResponseInterface
            {
                $response = $this->client->request($method, $url, $options);
                $response->getStatusCode(); // should  be avoided and breaks compatibility with AsyncDecoratorTrait

                return $response;
            }
        });

        $response = $client->request('GET', 'http://localhost:8057/');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Instance of "Symfony\Component\HttpClient\Response\NativeResponse" is already consumed and cannot be managed by "Symfony\Component\HttpClient\Response\AsyncResponse". A decorated client should not call any of the response\'s methods in its "request()" method.');
        $response->getStatusCode();
    }

    public function testMaxDuration()
    {
        $client = $this->getHttpClient(__FUNCTION__, function (ChunkInterface $chunk, AsyncContext $context) {
            static $sawFirst = false;
            try {
                if (!$chunk->isFirst() || !$sawFirst) {
                    $sawFirst = $sawFirst || $chunk->isFirst();
                    yield $chunk;
                }
            } catch (TransportExceptionInterface $e) {
                $context->getResponse()->cancel();
                $context->replaceRequest('GET', 'http://localhost:8057/timeout-body', ['timeout' => 0.4]);
            }
        });

        $response = $client->request('GET', 'http://localhost:8057/timeout-body', ['max_duration' => 0.75, 'timeout' => 0.4]);

        $this->assertSame(0.75, $response->getInfo('max_duration'));

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Max duration was reached for "http://localhost:8057/timeout-body".');
        $response->getContent();
    }
}
