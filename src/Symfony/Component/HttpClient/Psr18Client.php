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

use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * An adapter to turn a Symfony HttpClientInterface into a PSR-18 ClientInterface.
 *
 * Run "composer require psr/http-client" to install the base ClientInterface. Run
 * "composer require nyholm/psr7" to install an efficient implementation of response
 * and stream factories with flex-provided autowiring aliases.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @experimental in 4.3
 */
final class Psr18Client implements ClientInterface
{
    private $client;
    private $responseFactory;
    private $streamFactory;

    public function __construct(HttpClientInterface $client, ResponseFactoryInterface $responseFactory, StreamFactoryInterface $streamFactory)
    {
        $this->client = $client;
        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        try {
            $response = $this->client->request($request->getMethod(), (string) $request->getUri(), [
                'headers' => $request->getHeaders(),
                'body' => (string) $request->getBody(),
                'http_version' => '1.0' === $request->getProtocolVersion() ? '1.0' : null,
            ]);

            $psrResponse = $this->responseFactory->createResponse($response->getStatusCode());

            foreach ($response->getHeaders() as $name => $values) {
                foreach ($values as $value) {
                    $psrResponse = $psrResponse->withAddedHeader($name, $value);
                }
            }

            return $psrResponse->withBody($this->streamFactory->createStream($response->getContent()));
        } catch (TransportExceptionInterface $e) {
            if ($e instanceof \InvalidArgumentException) {
                throw new Psr18RequestException($e, $request);
            }

            throw new Psr18NetworkException($e, $request);
        }
    }
}

/**
 * @internal
 */
trait Psr18ExceptionTrait
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
class Psr18NetworkException extends \RuntimeException implements NetworkExceptionInterface
{
    use Psr18ExceptionTrait;
}

/**
 * @internal
 */
class Psr18RequestException extends \InvalidArgumentException implements RequestExceptionInterface
{
    use Psr18ExceptionTrait;
}
