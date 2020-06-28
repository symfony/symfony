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

use Http\Discovery\Exception\NotFoundException;
use Http\Discovery\Psr17FactoryDiscovery;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Uri;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use Symfony\Component\HttpClient\Response\ResponseTrait;
use Symfony\Component\HttpClient\Response\StreamWrapper;
use Symfony\Component\HttpClient\Response\TraceableResponse;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

if (!interface_exists(RequestFactoryInterface::class)) {
    throw new \LogicException('You cannot use the "Symfony\Component\HttpClient\Psr18Client" as the "psr/http-factory" package is not installed. Try running "composer require nyholm/psr7".');
}

if (!interface_exists(ClientInterface::class)) {
    throw new \LogicException('You cannot use the "Symfony\Component\HttpClient\Psr18Client" as the "psr/http-client" package is not installed. Try running "composer require psr/http-client".');
}

/**
 * An adapter to turn a Symfony HttpClientInterface into a PSR-18 ClientInterface.
 *
 * Run "composer require psr/http-client" to install the base ClientInterface. Run
 * "composer require nyholm/psr7" to install an efficient implementation of response
 * and stream factories with flex-provided autowiring aliases.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class Psr18Client implements ClientInterface, RequestFactoryInterface, StreamFactoryInterface, UriFactoryInterface
{
    private $client;
    private $responseFactory;
    private $streamFactory;

    public function __construct(HttpClientInterface $client = null, ResponseFactoryInterface $responseFactory = null, StreamFactoryInterface $streamFactory = null)
    {
        $this->client = $client ?? HttpClient::create();
        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory ?? ($responseFactory instanceof StreamFactoryInterface ? $responseFactory : null);

        if (null !== $this->responseFactory && null !== $this->streamFactory) {
            return;
        }

        if (!class_exists(Psr17Factory::class) && !class_exists(Psr17FactoryDiscovery::class)) {
            throw new \LogicException('You cannot use the "Symfony\Component\HttpClient\Psr18Client" as no PSR-17 factories have been provided. Try running "composer require nyholm/psr7".');
        }

        try {
            $psr17Factory = class_exists(Psr17Factory::class, false) ? new Psr17Factory() : null;
            $this->responseFactory = $this->responseFactory ?? $psr17Factory ?? Psr17FactoryDiscovery::findResponseFactory();
            $this->streamFactory = $this->streamFactory ?? $psr17Factory ?? Psr17FactoryDiscovery::findStreamFactory();
        } catch (NotFoundException $e) {
            throw new \LogicException('You cannot use the "Symfony\Component\HttpClient\HttplugClient" as no PSR-17 factories have been found. Try running "composer require nyholm/psr7".', 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        try {
            $body = $request->getBody();

            if ($body->isSeekable()) {
                $body->seek(0);
            }

            $response = $this->client->request($request->getMethod(), (string) $request->getUri(), [
                'headers' => $request->getHeaders(),
                'body' => $body->getContents(),
                'http_version' => '1.0' === $request->getProtocolVersion() ? '1.0' : null,
            ]);

            $psrResponse = $this->responseFactory->createResponse($response->getStatusCode());

            foreach ($response->getHeaders(false) as $name => $values) {
                foreach ($values as $value) {
                    $psrResponse = $psrResponse->withAddedHeader($name, $value);
                }
            }

            $body = $response instanceof TraceableResponse || isset(class_uses($response)[ResponseTrait::class]) ? $response->toStream(false) : StreamWrapper::createResource($response, $this->client);
            $body = $this->streamFactory->createStreamFromResource($body);

            if ($body->isSeekable()) {
                $body->seek(0);
            }

            return $psrResponse->withBody($body);
        } catch (TransportExceptionInterface $e) {
            if ($e instanceof \InvalidArgumentException) {
                throw new Psr18RequestException($e, $request);
            }

            throw new Psr18NetworkException($e, $request);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createRequest(string $method, $uri): RequestInterface
    {
        if ($this->responseFactory instanceof RequestFactoryInterface) {
            return $this->responseFactory->createRequest($method, $uri);
        }

        if (class_exists(Request::class)) {
            return new Request($method, $uri);
        }

        if (class_exists(Psr17FactoryDiscovery::class)) {
            return Psr17FactoryDiscovery::findRequestFactory()->createRequest($method, $uri);
        }

        throw new \LogicException(sprintf('You cannot use "%s()" as the "nyholm/psr7" package is not installed. Try running "composer require nyholm/psr7".', __METHOD__));
    }

    /**
     * {@inheritdoc}
     */
    public function createStream(string $content = ''): StreamInterface
    {
        $stream = $this->streamFactory->createStream($content);

        if ($stream->isSeekable()) {
            $stream->seek(0);
        }

        return $stream;
    }

    /**
     * {@inheritdoc}
     */
    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        return $this->streamFactory->createStreamFromFile($filename, $mode);
    }

    /**
     * {@inheritdoc}
     */
    public function createStreamFromResource($resource): StreamInterface
    {
        return $this->streamFactory->createStreamFromResource($resource);
    }

    /**
     * {@inheritdoc}
     */
    public function createUri(string $uri = ''): UriInterface
    {
        if ($this->responseFactory instanceof UriFactoryInterface) {
            return $this->responseFactory->createUri($uri);
        }

        if (class_exists(Uri::class)) {
            return new Uri($uri);
        }

        if (class_exists(Psr17FactoryDiscovery::class)) {
            return Psr17FactoryDiscovery::findUrlFactory()->createUri($uri);
        }

        throw new \LogicException(sprintf('You cannot use "%s()" as the "nyholm/psr7" package is not installed. Try running "composer require nyholm/psr7".', __METHOD__));
    }
}

/**
 * @internal
 */
class Psr18NetworkException extends \RuntimeException implements NetworkExceptionInterface
{
    private $request;

    public function __construct(TransportExceptionInterface $e, RequestInterface $request)
    {
        parent::__construct($e->getMessage(), 0, $e);
        $this->request = $request;
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}

/**
 * @internal
 */
class Psr18RequestException extends \InvalidArgumentException implements RequestExceptionInterface
{
    private $request;

    public function __construct(TransportExceptionInterface $e, RequestInterface $request)
    {
        parent::__construct($e->getMessage(), 0, $e);
        $this->request = $request;
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
