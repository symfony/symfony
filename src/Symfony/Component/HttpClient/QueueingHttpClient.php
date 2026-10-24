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

use Symfony\Component\HttpClient\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\Internal\QueueingClientState;
use Symfony\Component\HttpClient\Response\QueuedResponse;
use Symfony\Component\HttpClient\Response\ResponseStream;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Queues requests to limit the number of active responses.
 *
 * Requests beyond the limit start when their responses are read.
 * Queued requests are validated when they start.
 * Reading responses out of order can store earlier bodies in temporary files.
 * The limit does not bound memory used by buffered responses.
 */
class QueueingHttpClient implements HttpClientInterface, ResetInterface
{
    use DecoratorTrait {
        withOptions as private withClientOptions;
    }

    private QueueingClientState $state;
    private array $defaultOptions = [];

    public function __construct(HttpClientInterface $client, int $maxConcurrentRequests = 100)
    {
        if (1 > $maxConcurrentRequests) {
            throw new InvalidArgumentException('The maximum number of concurrent requests must be greater than zero.');
        }

        $this->client = $client;
        $this->state = new QueueingClientState($maxConcurrentRequests);
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $response = new QueuedResponse($this->state, $this->client, $method, $url, $options + $this->defaultOptions);

        try {
            $this->state->add($response);
        } catch (\Throwable $e) {
            $response->fail($e);
            throw $e;
        }

        return $response;
    }

    public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
    {
        return new ResponseStream(QueuedResponse::stream($responses instanceof ResponseInterface ? [$responses] : $responses, $timeout));
    }

    public function withOptions(array $options): static
    {
        $clone = $this->withClientOptions($options);
        $clone->defaultOptions = array_intersect_key($options, ['buffer' => true, 'user_data' => true, 'max_duration' => true, 'timeout' => true]) + $this->defaultOptions;

        return $clone;
    }
}
