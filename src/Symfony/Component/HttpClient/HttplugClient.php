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
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Promise\Utils;
use Http\Client\Exception\NetworkException;
use Http\Client\Exception\RequestException;
use Http\Client\HttpAsyncClient;
use Http\Discovery\Psr17Factory;
use Http\Discovery\Psr17FactoryDiscovery;
use Nyholm\Psr7\Factory\Psr17Factory as NyholmPsr17Factory;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Uri;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as Psr7ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use Symfony\Component\HttpClient\Internal\HttplugWaitLoop;
use Symfony\Component\HttpClient\Response\HttplugPromise;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\Service\ResetInterface;

if (!interface_exists(HttpAsyncClient::class)) {
    throw new \LogicException('You cannot use "Symfony\Component\HttpClient\HttplugClient" as the "php-http/httplug" package is not installed. Try running "composer require php-http/discovery php-http/async-client-implementation:*".');
}

if (!interface_exists(RequestFactoryInterface::class)) {
    throw new \LogicException('You cannot use the "Symfony\Component\HttpClient\HttplugClient" as the "psr/http-factory" package is not installed. Try running "composer require php-http/discovery psr/http-factory-implementation:*".');
}

/**
 * An adapter to turn a Symfony HttpClientInterface into an Httplug client.
 *
 * In comparison to Psr18Client, this client supports asynchronous requests.
 *
 * Run "composer require php-http/discovery php-http/async-client-implementation:*"
 * to get the required dependencies.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class HttplugClient implements ClientInterface, HttpAsyncClient, RequestFactoryInterface, StreamFactoryInterface, UriFactoryInterface, ResetInterface
{
    private HttpClientInterface $client;
    private ResponseFactoryInterface $responseFactory;
    private StreamFactoryInterface $streamFactory;

    /**
     * @var \SplObjectStorage<ResponseInterface, array{RequestInterface, Promise}>|null
     */
    private ?\SplObjectStorage $promisePool;

    private HttplugWaitLoop $waitLoop;

    public function __construct(HttpClientInterface $client = null, ResponseFactoryInterface $responseFactory = null, StreamFactoryInterface $streamFactory = null)
    {
        $this->client = $client ?? HttpClient::create();
        $streamFactory ??= $responseFactory instanceof StreamFactoryInterface ? $responseFactory : null;
        $this->promisePool = class_exists(Utils::class) ? new \SplObjectStorage() : null;

        if (null === $responseFactory || null === $streamFactory) {
            if (class_exists(Psr17Factory::class)) {
                $psr17Factory = new Psr17Factory();
            } elseif (class_exists(NyholmPsr17Factory::class)) {
                $psr17Factory = new NyholmPsr17Factory();
            } else {
                throw new \LogicException('You cannot use the "Symfony\Component\HttpClient\HttplugClient" as no PSR-17 factories have been provided. Try running "composer require php-http/discovery psr/http-factory-implementation:*".');
            }

            $responseFactory ??= $psr17Factory;
            $streamFactory ??= $psr17Factory;
        }

        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory;
        $this->waitLoop = new HttplugWaitLoop($this->client, $this->promisePool, $this->responseFactory, $this->streamFactory);
    }

    public function withOptions(array $options): static
    {
        $clone = clone $this;
        $clone->client = $clone->client->withOptions($options);

        return $clone;
    }

    public function sendRequest(RequestInterface $request): Psr7ResponseInterface
    {
        try {
            return HttplugWaitLoop::createPsr7Response($this->responseFactory, $this->streamFactory, $this->client, $this->sendPsr7Request($request), true);
        } catch (TransportExceptionInterface $e) {
            throw new NetworkException($e->getMessage(), $request, $e);
        }
    }

    public function sendAsyncRequest(RequestInterface $request): HttplugPromise
    {
        if (!$promisePool = $this->promisePool) {
            throw new \LogicException(sprintf('You cannot use "%s()" as the "guzzlehttp/promises" package is not installed. Try running "composer require guzzlehttp/promises".', __METHOD__));
        }

        try {
            $response = $this->sendPsr7Request($request, true);
        } catch (NetworkException $e) {
            return new HttplugPromise(new RejectedPromise($e));
        }

        $waitLoop = $this->waitLoop;

        $promise = new GuzzlePromise(static function () use ($response, $waitLoop) {
            $waitLoop->wait($response);
        }, static function () use ($response, $promisePool) {
            $response->cancel();
            unset($promisePool[$response]);
        });

        $promisePool[$response] = [$request, $promise];

        return new HttplugPromise($promise);
    }

    /**
     * Resolves pending promises that complete before the timeouts are reached.
     *
     * When $maxDuration is null and $idleTimeout is reached, promises are rejected.
     *
     * @return int The number of remaining pending promises
     */
    public function wait(float $maxDuration = null, float $idleTimeout = null): int
    {
        return $this->waitLoop->wait(null, $maxDuration, $idleTimeout);
    }

    /**
     * @param UriInterface|string $uri
     */
    public function createRequest(string $method, $uri = ''): RequestInterface
    {
        if ($this->responseFactory instanceof RequestFactoryInterface) {
            $request = $this->responseFactory->createRequest($method, $uri);
        } elseif (class_exists(Psr17FactoryDiscovery::class)) {
            $request = Psr17FactoryDiscovery::findRequestFactory()->createRequest($method, $uri);
        } elseif (class_exists(Request::class)) {
            $request = new Request($method, $uri);
        } else {
            throw new \LogicException(sprintf('You cannot use "%s()" as no PSR-17 factories have been found. Try running "composer require php-http/discovery psr/http-factory-implementation:*".', __METHOD__));
        }

        return $request;
    }

    public function createStream(string $content = ''): StreamInterface
    {
        return $this->streamFactory->createStream($content);
    }

    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        return $this->streamFactory->createStreamFromFile($filename, $mode);
    }

    public function createStreamFromResource($resource): StreamInterface
    {
        return $this->streamFactory->createStreamFromResource($resource);
    }

    public function createUri(string $uri = ''): UriInterface
    {
        if ($this->responseFactory instanceof UriFactoryInterface) {
            return $this->responseFactory->createUri($uri);
        }

        if (class_exists(Psr17FactoryDiscovery::class)) {
            return Psr17FactoryDiscovery::findUriFactory()->createUri($uri);
        }

        if (class_exists(Uri::class)) {
            return new Uri($uri);
        }

        throw new \LogicException(sprintf('You cannot use "%s()" as no PSR-17 factories have been found. Try running "composer require php-http/discovery psr/http-factory-implementation:*".', __METHOD__));
    }

    public function __sleep(): array
    {
        throw new \BadMethodCallException('Cannot serialize '.__CLASS__);
    }

    public function __wakeup(): void
    {
        throw new \BadMethodCallException('Cannot unserialize '.__CLASS__);
    }

    public function __destruct()
    {
        $this->wait();
    }

    public function reset(): void
    {
        if ($this->client instanceof ResetInterface) {
            $this->client->reset();
        }
    }

    private function sendPsr7Request(RequestInterface $request, bool $buffer = null): ResponseInterface
    {
        try {
            $body = $request->getBody();

            if ($body->isSeekable()) {
                $body->seek(0);
            }

            $options = [
                'headers' => $request->getHeaders(),
                'body' => $body->getContents(),
                'buffer' => $buffer,
            ];

            if ('1.0' === $request->getProtocolVersion()) {
                $options['http_version'] = '1.0';
            }

            return $this->client->request($request->getMethod(), (string) $request->getUri(), $options);
        } catch (\InvalidArgumentException $e) {
            throw new RequestException($e->getMessage(), $request, $e);
        } catch (TransportExceptionInterface $e) {
            throw new NetworkException($e->getMessage(), $request, $e);
        }
    }
}
