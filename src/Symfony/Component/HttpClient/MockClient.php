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

use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * Provides a way to tests the HttpClient without making actual HTTP requests.
 *
 * @author Gary PEGEOT <garypegeot@gmail.com>
 */
class MockClient implements HttpClientInterface
{
    use HttpClientTrait;

    /**
     * Predefined responses. Throw a TransportExceptionInterface when none provided.
     *
     * @var ResponseInterface[]
     */
    private $responses = [];

    /**
     * Predefined streamed responses. Throw a TransportExceptionInterface when none provided.
     *
     * @var ResponseStreamInterface[]
     */
    private $streams = [];

    private $requests = [];

    private $streamedRequests = [];

    /**
     * {@inheritdoc}
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        if (!\count($this->responses)) {
            throw new TransportException('No predefined response to send. Please add one or more using "addResponse" method.');
        }

        [$url, $options] = static::prepareRequest($method, $url, $options, [], true);
        $this->requests[] = compact('method', 'url', 'options');

        return \array_shift($this->responses);
    }

    /**
     * {@inheritdoc}
     */
    public function stream($responses, float $timeout = null): ResponseStreamInterface
    {
        if (!\count($this->streams)) {
            throw new TransportException('No predefined response to send. Please add one or more using "addResponseStream" method.');
        }

        $this->streamedRequests[] = $responses;

        return \array_shift($this->streams);
    }

    public function addResponse(ResponseInterface $response): self
    {
        $this->responses[] = $response;

        return $this;
    }

    public function addResponseStream(ResponseStreamInterface $response): self
    {
        $this->streams[] = $response;

        return $this;
    }

    /**
     * Get all the call to ::request() made by the client.
     */
    public function getRequests(): array
    {
        return $this->requests;
    }

    /**
     * Get all the call to ::stream() made by the client.
     */
    public function getStreamedRequests(): array
    {
        return $this->streamedRequests;
    }

    /**
     * Clear all predefined responses and requests.
     */
    public function clear(): void
    {
        $this->responses = [];
        $this->streams = [];
        $this->requests = [];
        $this->streamedRequests = [];
    }
}
