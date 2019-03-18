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
                throw new \TypeError(sprintf('Each predefined response must an instance of %s, %s given.', ResponseInterface::class, \is_object($response) ? \get_class($response) : \gettype($response)));
            }

            $this->responses[] = $response;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        if (!\count($this->responses)) {
            throw new TransportException('No predefined response to send. Please add one or more using "addResponse" method.');
        }

        return \array_shift($this->responses);
    }

    /**
     * {@inheritdoc}
     */
    public function stream($responses, float $timeout = null): ResponseStreamInterface
    {
        if ($responses instanceof ResponseInterface) {
            $responses = [$responses];
        } elseif (!\is_iterable($responses)) {
            throw new \TypeError(sprintf('%s() expects parameter 1 to be an iterable of ResponseInterface objects, %s given.', __METHOD__, \is_object($responses) ? \get_class($responses) : \gettype($responses)));
        }

        return new ResponseStream($this->streamResponses($responses));
    }

    /**
     * @return $this
     */
    public function addResponse(ResponseInterface $response)
    {
        $this->responses[] = $response;

        return $this;
    }

    /**
     * Clears all predefined responses.
     */
    public function clear(): void
    {
        $this->responses = [];
    }

    private function streamResponses(iterable $responses): \Generator
    {
        foreach ($responses as $response) {
            try {
                $response->getHeaders(true);

                yield $response => new FirstChunk();
                yield $response => new DataChunk(0, $content = $response->getContent(true));
                yield $response => new LastChunk(\strlen($content));
            } catch (TransportExceptionInterface $e) {
                $didThrow = false;

                yield $response => new ErrorChunk($didThrow, 0, $e);
            }
        }
    }
}
