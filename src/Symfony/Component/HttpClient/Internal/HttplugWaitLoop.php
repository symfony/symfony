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
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as Psr7ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Component\HttpClient\Response\CommonResponseTrait;
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
    private $client;
    private $promisePool;
    private $responseFactory;
    private $streamFactory;

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

        $guzzleQueue = \GuzzleHttp\Promise\queue();

        if (0.0 === $remainingDuration = $maxDuration) {
            $idleTimeout = 0.0;
        } elseif (null !== $maxDuration) {
            $startTime = microtime(true);
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
                        $promise->resolve($this->createPsr7Response($response, true));
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
                if (null !== $maxDuration && $idleTimeout && $idleTimeout > $remainingDuration = max(0.0, $maxDuration - microtime(true) + $startTime)) {
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

    public function createPsr7Response(ResponseInterface $response, bool $buffer = false): Psr7ResponseInterface
    {
        $psrResponse = $this->responseFactory->createResponse($response->getStatusCode());

        foreach ($response->getHeaders(false) as $name => $values) {
            foreach ($values as $value) {
                $psrResponse = $psrResponse->withAddedHeader($name, $value);
            }
        }

        if (isset(class_uses($response)[CommonResponseTrait::class])) {
            $body = $this->streamFactory->createStreamFromResource($response->toStream(false));
        } elseif (!$buffer) {
            $body = $this->streamFactory->createStreamFromResource(StreamWrapper::createResource($response, $this->client));
        } else {
            $body = $this->streamFactory->createStream($response->getContent(false));
        }

        if ($body->isSeekable()) {
            $body->seek(0);
        }

        return $psrResponse->withBody($body);
    }
}
