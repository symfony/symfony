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

use Http\Client\Exception\NetworkException;
use Http\Client\Exception\RequestException;
use Http\Client\HttpClient;
use Http\Message\RequestFactory;
use Http\Message\StreamFactory;
use Http\Message\UriFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

if (!interface_exists(HttpClient::class)) {
    throw new \LogicException('You cannot use "Symfony\Component\HttpClient\HttplugClient" as the "php-http/httplug" package is not installed. Try running "composer require php-http/httplug".');
}

if (!interface_exists(ClientInterface::class)) {
    throw new \LogicException('You cannot use "Symfony\Component\HttpClient\HttplugClient" as the "psr/http-client" package is not installed. Try running "composer require psr/http-client".');
}

if (!interface_exists(RequestFactory::class)) {
    throw new \LogicException('You cannot use "Symfony\Component\HttpClient\HttplugClient" as the "php-http/message-factory" package is not installed. Try running "composer require nyholm/psr7".');
}

/**
 * An adapter to turn a Symfony HttpClientInterface into an Httplug client.
 *
 * Run "composer require psr/http-client" to install the base ClientInterface. Run
 * "composer require nyholm/psr7" to install an efficient implementation of response
 * and stream factories with flex-provided autowiring aliases.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class HttplugClient implements HttpClient, RequestFactory, StreamFactory, UriFactory
{
    private $client;

    public function __construct(HttpClientInterface $client = null, ResponseFactoryInterface $responseFactory = null, StreamFactoryInterface $streamFactory = null)
    {
        $this->client = new Psr18Client($client, $responseFactory, $streamFactory);
    }

    /**
     * {@inheritdoc}
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        try {
            return $this->client->sendRequest($request);
        } catch (RequestExceptionInterface $e) {
            throw new RequestException($e->getMessage(), $request, $e);
        } catch (NetworkExceptionInterface $e) {
            throw new NetworkException($e->getMessage(), $request, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createRequest($method, $uri, array $headers = [], $body = null, $protocolVersion = '1.1'): RequestInterface
    {
        $request = $this->client
            ->createRequest($method, $uri)
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
            $body = $this->client->createStream($body ?? '');

            if ($body->isSeekable()) {
                $body->seek(0);
            }

            return $body;
        }

        if (\is_resource($body)) {
            return $this->client->createStreamFromResource($body);
        }

        throw new \InvalidArgumentException(sprintf('%s() expects string, resource or StreamInterface, %s given.', __METHOD__, \gettype($body)));
    }

    /**
     * {@inheritdoc}
     */
    public function createUri($uri = ''): UriInterface
    {
        return $uri instanceof UriInterface ? $uri : $this->client->createUri($uri);
    }
}
