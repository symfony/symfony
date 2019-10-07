<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient;

use GuzzleHttp\Promise\Promise as GuzzlePromise;
use Http\Client\Exception\NetworkException;
use Http\Client\Exception\RequestException;
use Http\Client\HttpAsyncClient;
use Http\Client\HttpClient as HttplugInterface;
use Http\Message\RequestFactory;
use Http\Message\StreamFactory;
use Http\Message\UriFactory;
use Http\Promise\Promise;
use Http\Promise\RejectedPromise;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Uri;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as Psr7ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use Symfony\Component\HttpClient\Response\HttplugPromise;
use Symfony\Component\HttpClient\Response\ResponseTrait;
use Symfony\Component\HttpClient\Response\StreamWrapper;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

if (!interface_exists(HttplugInterface::class)) {
    throw new \LogicException('You cannot use "Symfony\Component\HttpClient\HttplugClient" as the "php-http/httplug" package is not installed. Try running "composer require php-http/httplug".');
}

if (!interface_exists(RequestFactory::class)) {
    throw new \LogicException('You cannot use "Symfony\Component\HttpClient\HttplugClient" as the "php-http/message-factory" package is not installed. Try running "composer require nyholm/psr7".');
}

/**
 * An adapter to turn a Symfony HttpClientInterface into an Httplug client.
 *
 * Run "composer require nyholm/psr7" to install an efficient implementation of response
 * and stream factories with flex-provided autowiring aliases.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class HttplugClient implements HttplugInterface, HttpAsyncClient, RequestFactory, StreamFactory, UriFactory
{
    private $client;
    private $responseFactory;
    private $streamFactory;
    private $promisePool = [];
    private $pendingResponse;

    public function __construct(HttpClientInterface $client = null, ResponseFactoryInterface $responseFactory = null, StreamFactoryInterface $streamFactory = null)
    {
        $this->client = $client ?? HttpClient::create();
        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory ?? ($responseFactory instanceof StreamFactoryInterface ? $responseFactory : null);
        $this->promisePool = new \SplObjectStorage();

        if (null !== $this->responseFactory && null !== $this->streamFactory) {
            return;
        }

        if (!class_exists(Psr17Factory::class)) {
            throw new \LogicException('You cannot use the "Symfony\Component\HttpClient\HttplugClient" as no PSR-17 factories have been provided. Try running "composer require nyholm/psr7".');
        }

        $psr17Factory = new Psr17Factory();
        $this->responseFactory = $this->responseFactory ?? $psr17Factory;
        $this->streamFactory = $this->streamFactory ?? $psr17Factory;
    }

    /**
     * {@inheritdoc}
     */
    public function sendRequest(RequestInterface $request): Psr7ResponseInterface
    {
        try {
            return $this->createPsr7Response($this->sendPsr7Request($request));
        } catch (TransportExceptionInterface $e) {
            throw new NetworkException($e->getMessage(), $request, $e);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @return HttplugPromise
     */
    public function sendAsyncRequest(RequestInterface $request): Promise
    {
        if (!class_exists(GuzzlePromise::class)) {
            throw new \LogicException(sprintf('You cannot use "%s()" as the "guzzlehttp/promises" package is not installed. Try running "composer require guzzlehttp/promises".', __METHOD__));
        }

        try {
            $response = $this->sendPsr7Request($request, true);
        } catch (NetworkException $e) {
            return new RejectedPromise($e);
        }

        $cancel = function () use ($response) {
            $response->cancel();
            unset($this->promisePool[$response]);
        };

        $promise = new GuzzlePromise(function () use ($response) {
            $this->pendingResponse = $response;
            $this->wait();
        }, $cancel);

        $this->promisePool[$response] = [$request, $promise];

        return new HttplugPromise($promise, $cancel);
    }

    /**
     * Resolve pending promises that complete before the timeouts are reached.
     *
     * When $maxDuration is null and $idleTimeout is reached, promises are rejected.
     *
     * @return int The number of remaining pending promises
     */
    public function wait(float $maxDuration = null, float $idleTimeout = null): int
    {
        $pendingResponse = $this->pendingResponse;
        $this->pendingResponse = null;

        if (null !== $maxDuration) {
            $startTime = microtime(true);
            $idleTimeout = max(0.0, min($maxDuration / 5, $idleTimeout ?? $maxDuration));
            $remainingDuration = $maxDuration;
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

                    if ([$request, $promise] = $this->promisePool[$response] ?? null) {
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

                if ($pendingResponse === $response) {
                    return \count($this->promisePool);
                }

                check_duration:
                if (null !== $maxDuration && $idleTimeout && $idleTimeout > $remainingDuration = max(0.0, $maxDuration - microtime(true) + $startTime)) {
                    $idleTimeout = $remainingDuration / 5;
                    break;
                }
            }

            if (!$count = \count($this->promisePool)) {
                return 0;
            }
        } while (null !== $maxDuration && 0 < $remainingDuration);

        return $count;
    }

    /**
     * {@inheritdoc}
     */
    public function createRequest($method, $uri, array $headers = [], $body = null, $protocolVersion = '1.1'): RequestInterface
    {
        if ($this->responseFactory instanceof RequestFactoryInterface) {
            $request = $this->responseFactory->createRequest($method, $uri);
        } elseif (!class_exists(Request::class)) {
            throw new \LogicException(sprintf('You cannot use "%s()" as the "nyholm/psr7" package is not installed. Try running "composer require nyholm/psr7".', __METHOD__));
        } else {
            $request = new Request($method, $uri);
        }

        $request = $request
            ->withProtocolVersion($protocolVersion)
            ->withBody($this->createStream($body))
        ;

        foreach ($headers as $name => $value) {
            $request = $request->withAddedHeader($name, $value);
        }

        return $request;
    }

    /**
     * {@inheritdoc}
     */
    public function createStream($body = null): StreamInterface
    {
        if ($body instanceof StreamInterface) {
            return $body;
        }

        if (\is_string($body ?? '')) {
            $stream = $this->streamFactory->createStream($body ?? '');
        } elseif (\is_resource($body)) {
            $stream = $this->streamFactory->createStreamFromResource($body);
        } else {
            throw new \InvalidArgumentException(sprintf('%s() expects string, resource or StreamInterface, %s given.', __METHOD__, \gettype($body)));
        }

        if ($stream->isSeekable()) {
            $stream->seek(0);
        }

        return $stream;
    }

    /**
     * {@inheritdoc}
     */
    public function createUri($uri): UriInterface
    {
        if ($uri instanceof UriInterface) {
            return $uri;
        }

        if ($this->responseFactory instanceof UriFactoryInterface) {
            return $this->responseFactory->createUri($uri);
        }

        if (!class_exists(Uri::class)) {
            throw new \LogicException(sprintf('You cannot use "%s()" as the "nyholm/psr7" package is not installed. Try running "composer require nyholm/psr7".', __METHOD__));
        }

        return new Uri($uri);
    }

    private function sendPsr7Request(RequestInterface $request, bool $buffer = null): ResponseInterface
    {
        try {
            $body = $request->getBody();

            if ($body->isSeekable()) {
                $body->seek(0);
            }

            return $this->client->request($request->getMethod(), (string) $request->getUri(), [
                'headers' => $request->getHeaders(),
                'body' => $body->getContents(),
                'http_version' => '1.0' === $request->getProtocolVersion() ? '1.0' : null,
                'buffer' => $buffer,
            ]);
        } catch (\InvalidArgumentException $e) {
            throw new RequestException($e->getMessage(), $request, $e);
        } catch (TransportExceptionInterface $e) {
            throw new NetworkException($e->getMessage(), $request, $e);
        }
    }

    private function createPsr7Response(ResponseInterface $response, bool $buffer = false): Psr7ResponseInterface
    {
        $psrResponse = $this->responseFactory->createResponse($response->getStatusCode());

        foreach ($response->getHeaders(false) as $name => $values) {
            foreach ($values as $value) {
                $psrResponse = $psrResponse->withAddedHeader($name, $value);
            }
        }

        if (isset(class_uses($response)[ResponseTrait::class])) {
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
