<?php

declare(strict_types=1);


namespace Symfony\Component\HttpClient;

use Symfony\Component\HttpClient\Middleware\MiddlewareInterface;
use Symfony\Component\HttpClient\Middleware\MiddlewareStack;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

class MiddlewareHttpClient implements HttpClientInterface
{
    /**
     * @var HttpClientInterface
     */
    private $httpClient;
    /**
     * @var MiddlewareStack
     */
    private $requestMiddlewareStack;

    public function __construct(HttpClientInterface $httpClient, MiddlewareStack $requestMiddlewareStack)
    {
        $this->httpClient = $httpClient;
        $this->requestMiddlewareStack = $requestMiddlewareStack;
    }

    /**
     * Requests an HTTP resource.
     *
     * Responses MUST be lazy, but their status code MUST be
     * checked even if none of their public methods are called.
     *
     * Implementations are not required to support all options described above; they can also
     * support more custom options; but in any case, they MUST throw a TransportExceptionInterface
     * when an unsupported option is passed.
     *
     * @throws TransportExceptionInterface When an unsupported option is passed
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $core = function (string $method, string $url, array $options = []) {
            return $this->httpClient->request($method, $url, $options);
        };

        $layerBuilder = function (callable $next, MiddlewareInterface $current) {
            return function (string $method, string $url, array $options = []) use ($next, $current) {
                return $current($method, $url, $options, $next);
            };
        };

        return \call_user_func($this->requestMiddlewareStack->build($core, $layerBuilder), $method, $url, $options);
    }

    /**
     * Yields responses chunk by chunk as they complete.
     *
     * @param ResponseInterface|ResponseInterface[]|iterable $responses One or more responses created by the current HTTP client
     * @param float|null $timeout The inactivity timeout before exiting the iterator
     */
    public function stream($responses, float $timeout = null): ResponseStreamInterface
    {
        return $this->httpClient->stream($responses, $timeout);
    }
}