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

use Symfony\Component\HttpClient\Chunk\DataChunk;
use Symfony\Component\HttpClient\Chunk\ErrorChunk;
use Symfony\Component\HttpClient\Chunk\FirstChunk;
use Symfony\Component\HttpClient\Chunk\LastChunk;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\Response\ResponseStream;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
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
    /**
     * Predefined responses. Throw a TransportExceptionInterface when none provided.
     *
     * @var ResponseInterface[]
     */
    private $responses = [];

    /**
     * MockClient constructor.
     *
     * @param iterable|ResponseInterface[] $responses
     */
    public function __construct(iterable $responses = [])
    {
        foreach ($responses as $response) {
            if (!$response instanceof ResponseInterface) {
                throw new \InvalidArgumentException(sprintf('All responses must implement "%s"', ResponseInterface::class));
            }

            $this->responses[] = $response;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        return $this->getNextResponse();
    }

    /**
     * {@inheritdoc}
     */
    public function stream($responses, float $timeout = null): ResponseStreamInterface
    {
        return new ResponseStream($this->streamNext());
    }

    public function addResponse(ResponseInterface $response): self
    {
        $this->responses[] = $response;

        return $this;
    }

    /**
     * Clear all predefined responses and requests.
     */
    public function clear(): void
    {
        $this->responses = [];
    }

    private function getNextResponse(): ResponseInterface
    {
        if (!\count($this->responses)) {
            throw new TransportException('No predefined response to send. Please add one or more using "addResponse" method.');
        }

        return \array_shift($this->responses);
    }

    private function streamNext(): \Generator
    {
        $response = $this->getNextResponse();

        try {
            $response->getHeaders(true);
        } catch (TransportExceptionInterface $e) {
            yield new ErrorChunk($didThrow, 0, $e);
        }

        try {
            $content = $response->getContent(true);

            yield new FirstChunk(0, $content);
            yield new DataChunk(1, $content);
            yield new LastChunk(2, $content);
        } catch (TransportExceptionInterface $e) {
            yield new ErrorChunk($didThrow, 0, $e);
        }
    }
}
