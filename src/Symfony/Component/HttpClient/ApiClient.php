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

use Symfony\Component\HttpClient\Response\ApiResponse;
use Symfony\Component\HttpClient\Response\ResponseIterator;
use Symfony\Contracts\HttpClient\ApiClientInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseIteratorInterface;

/**
 * ApiClient helps to interact with common HTTP APIs.
 *
 * When responses need to be streamed, an HttpClientInterface implementation should be used instead.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class ApiClient implements ApiClientInterface
{
    use HttpClientTrait;

    private $client;
    private $defaultOptions = [
        'headers' => [
            'accept' => ['application/json'],
        ],
    ] + ApiClientInterface::OPTIONS_DEFAULTS;

    /**
     * A factory to instantiate the best possible API client for the runtime.
     *
     * @param array $defaultOptions     Default requests' options
     * @param int   $maxHostConnections The maximum number of connections to a single host
     *
     * @see HttpClientInterface::OPTIONS_DEFAULTS for available options
     */
    public static function create(array $defaultOptions = [], int $maxHostConnections = 6): ApiClientInterface
    {
        return new self(HttpClient::create($defaultOptions, $maxHostConnections));
    }

    public function __construct(HttpClientInterface $client, array $defaultOptions = [])
    {
        $this->client = $client;

        if ($defaultOptions) {
            [, $this->defaultOptions] = self::prepareRequest(null, null, $defaultOptions, $this->defaultOptions);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $url, array $options = []): ResponseInterface
    {
        return $this->requestApi('GET', $url, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function head(string $url, array $options = []): ResponseInterface
    {
        return $this->requestApi('HEAD', $url, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function post(string $url, array $options = []): ResponseInterface
    {
        return $this->requestApi('POST', $url, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function put(string $url, array $options = []): ResponseInterface
    {
        return $this->requestApi('PUT', $url, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function patch(string $url, array $options = []): ResponseInterface
    {
        return $this->requestApi('PATCH', $url, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $url, array $options = []): ResponseInterface
    {
        return $this->requestApi('DELETE', $url, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function options(string $url, array $options = []): ResponseInterface
    {
        return $this->requestApi('OPTIONS', $url, $options);
    }

    /**
     * {@inheritdoc}
     *
     * @param ApiResponse|ApiResponse[] $responses
     */
    public function complete($responses, float $timeout = null): ResponseIteratorInterface
    {
        if ($responses instanceof ApiResponse) {
            $responses = [$responses];
        } elseif (!\is_iterable($responses)) {
            throw new \TypeError(sprintf('%s() expects parameter 1 to be iterable of ApiResponse objects, %s given.', __METHOD__, \is_object($responses) ? \get_class($responses) : \gettype($responses)));
        }

        return new ResponseIterator(ApiResponse::complete($this->client, $responses, $timeout));
    }

    private function requestApi(string $method, string $url, array $options): ApiResponse
    {
        $options['buffer'] = true;
        [, $options] = self::prepareRequest(null, null, $options, $this->defaultOptions);

        return new ApiResponse($this->client->request($method, $url, $options));
    }
}
