<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Internal;

use Http\Client\Exception\NetworkException;
use Http\Promise\Promise;
use Psr\Http\Message\RequestInterface as Psr7RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as Psr7ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Component\HttpClient\Response\StreamableInterface;
use Symfony\Component\HttpClient\Response\StreamWrapper;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
final class HttplugWaitLoop
{
    private HttpClientInterface $client;
    private ?\SplObjectStorage $promisePool;
    private ResponseFactoryInterface $responseFactory;
    private StreamFactoryInterface $streamFactory;

    /**
     * @param \SplObjectStorage<ResponseInterface, array{Psr7RequestInterface, Promise}>|null $promisePool
     */
    public function __construct(HttpClientInterface $client, ?\SplObjectStorage $promisePool, ResponseFactoryInterface $responseFactory, StreamFactoryInterface $streamFactory)
    {
        $this->client = $client;
        $this->promisePool = $promisePool;
        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory;
    }

    public function wait(?ResponseInterface $pendingResponse, float $maxDuration = null, float $idleTimeout = null): int
    {
        if (!$this->promisePool) {
            return 0;
        }

        $guzzleQueue = \GuzzleHttp\Promise\Utils::queue();

        if (0.0 === $remainingDuration = $maxDuration) {
            $idleTimeout = 0.0;
        } elseif (null !== $maxDuration) {
            $startTime = hrtime(true) / 1E9;
            $idleTimeout = max(0.0, min($maxDuration / 5, $idleTimeout ?? $maxDuration));
        }

        do {
            foreach ($this->client->stream($this->promisePool, $idleTimeout) as $response => $chunk) {
                try {
                    if (null !== $maxDuration && $chunk->isTimeout()) {
                        goto check_duration;
                    }

                    if ($chunk->isFirst()) {
                        // Deactivate throwing on 3/4/5xx
                        $response->getStatusCode();
                    }

                    if (!$chunk->isLast()) {
                        goto check_duration;
                    }

                    if ([, $promise] = $this->promisePool[$response] ?? null) {
                        unset($this->promisePool[$response]);
                        $promise->resolve(self::createPsr7Response($this->responseFactory, $this->streamFactory, $this->client, $response, true));
                    }
                } catch (\Exception $e) {
                    if ([$request, $promise] = $this->promisePool[$response] ?? null) {
                        unset($this->promisePool[$response]);

                        if ($e instanceof TransportExceptionInterface) {
                            $e = new NetworkException($e->getMessage(), $request, $e);
                        }

                        $promise->reject($e);
                    }
                }

                $guzzleQueue->run();

                if ($pendingResponse === $response) {
                    return $this->promisePool->count();
                }

                check_duration:
                if (null !== $maxDuration && $idleTimeout && $idleTimeout > $remainingDuration = max(0.0, $maxDuration - hrtime(true) / 1E9 + $startTime)) {
                    $idleTimeout = $remainingDuration / 5;
                    break;
                }
            }

            if (!$count = $this->promisePool->count()) {
                return 0;
            }
        } while (null === $maxDuration || 0 < $remainingDuration);

        return $count;
    }

    public static function createPsr7Response(ResponseFactoryInterface $responseFactory, StreamFactoryInterface $streamFactory, HttpClientInterface $client, ResponseInterface $response, bool $buffer): Psr7ResponseInterface
    {
        $responseParameters = [$response->getStatusCode()];

        foreach ($response->getInfo('response_headers') as $h) {
            if (11 <= \strlen($h) && '/' === $h[4] && preg_match('#^HTTP/\d+(?:\.\d+)? (?:\d\d\d) (.+)#', $h, $m)) {
                $responseParameters[1] = $m[1];
            }
        }

        $psrResponse = $responseFactory->createResponse(...$responseParameters);

        foreach ($response->getHeaders(false) as $name => $values) {
            foreach ($values as $value) {
                try {
                    $psrResponse = $psrResponse->withAddedHeader($name, $value);
                } catch (\InvalidArgumentException $e) {
                    // ignore invalid header
                }
            }
        }

        if ($response instanceof StreamableInterface) {
            $body = $streamFactory->createStreamFromResource($response->toStream(false));
        } elseif (!$buffer) {
            $body = $streamFactory->createStreamFromResource(StreamWrapper::createResource($response, $client));
        } else {
            $body = $streamFactory->createStream($response->getContent(false));
        }

        if ($body->isSeekable()) {
            $body->seek(0);
        }

        return $psrResponse->withBody($body);
    }
}
