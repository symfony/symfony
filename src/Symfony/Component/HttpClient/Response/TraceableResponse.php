<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Response;

use Symfony\Component\HttpClient\Chunk\ErrorChunk;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\Exception\RedirectionException;
use Symfony\Component\HttpClient\Exception\ServerException;
use Symfony\Component\HttpClient\TraceableHttpClient;
use Symfony\Component\Stopwatch\StopwatchEvent;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class TraceableResponse implements ResponseInterface, StreamableInterface
{
    private HttpClientInterface $client;
    private ResponseInterface $response;
    private mixed $content;
    private ?StopwatchEvent $event;

    public function __construct(HttpClientInterface $client, ResponseInterface $response, &$content, StopwatchEvent $event = null)
    {
        $this->client = $client;
        $this->response = $response;
        $this->content = &$content;
        $this->event = $event;
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
        try {
            if (method_exists($this->response, '__destruct')) {
                $this->response->__destruct();
            }
        } finally {
            if ($this->event?->isStarted()) {
                $this->event->stop();
            }
        }
    }

    public function getStatusCode(): int
    {
        try {
            return $this->response->getStatusCode();
        } finally {
            if ($this->event?->isStarted()) {
                $this->event->lap();
            }
        }
    }

    public function getHeaders(bool $throw = true): array
    {
        try {
            return $this->response->getHeaders($throw);
        } finally {
            if ($this->event?->isStarted()) {
                $this->event->lap();
            }
        }
    }

    public function getContent(bool $throw = true): string
    {
        try {
            if (false === $this->content) {
                return $this->response->getContent($throw);
            }

            return $this->content = $this->response->getContent(false);
        } finally {
            if ($this->event?->isStarted()) {
                $this->event->stop();
            }
            if ($throw) {
                $this->checkStatusCode($this->response->getStatusCode());
            }
        }
    }

    public function toArray(bool $throw = true): array
    {
        try {
            if (false === $this->content) {
                return $this->response->toArray($throw);
            }

            return $this->content = $this->response->toArray(false);
        } finally {
            if ($this->event?->isStarted()) {
                $this->event->stop();
            }
            if ($throw) {
                $this->checkStatusCode($this->response->getStatusCode());
            }
        }
    }

    public function cancel(): void
    {
        $this->response->cancel();

        if ($this->event?->isStarted()) {
            $this->event->stop();
        }
    }

    public function getInfo(string $type = null): mixed
    {
        return $this->response->getInfo($type);
    }

    /**
     * Casts the response to a PHP stream resource.
     *
     * @return resource
     *
     * @throws TransportExceptionInterface   When a network error occurs
     * @throws RedirectionExceptionInterface On a 3xx when $throw is true and the "max_redirects" option has been reached
     * @throws ClientExceptionInterface      On a 4xx when $throw is true
     * @throws ServerExceptionInterface      On a 5xx when $throw is true
     */
    public function toStream(bool $throw = true)
    {
        if ($throw) {
            // Ensure headers arrived
            $this->response->getHeaders(true);
        }

        if ($this->response instanceof StreamableInterface) {
            return $this->response->toStream(false);
        }

        return StreamWrapper::createResource($this->response, $this->client);
    }

    /**
     * @internal
     */
    public static function stream(HttpClientInterface $client, iterable $responses, ?float $timeout): \Generator
    {
        $wrappedResponses = [];
        $traceableMap = new \SplObjectStorage();

        foreach ($responses as $r) {
            if (!$r instanceof self) {
                throw new \TypeError(sprintf('"%s::stream()" expects parameter 1 to be an iterable of TraceableResponse objects, "%s" given.', TraceableHttpClient::class, get_debug_type($r)));
            }

            $traceableMap[$r->response] = $r;
            $wrappedResponses[] = $r->response;
            if ($r->event && !$r->event->isStarted()) {
                $r->event->start();
            }
        }

        foreach ($client->stream($wrappedResponses, $timeout) as $r => $chunk) {
            if ($traceableMap[$r]->event && $traceableMap[$r]->event->isStarted()) {
                try {
                    if ($chunk->isTimeout() || !$chunk->isLast()) {
                        $traceableMap[$r]->event->lap();
                    } else {
                        $traceableMap[$r]->event->stop();
                    }
                } catch (TransportExceptionInterface $e) {
                    $traceableMap[$r]->event->stop();
                    if ($chunk instanceof ErrorChunk) {
                        $chunk->didThrow(false);
                    } else {
                        $chunk = new ErrorChunk($chunk->getOffset(), $e);
                    }
                }
            }
            yield $traceableMap[$r] => $chunk;
        }
    }

    private function checkStatusCode(int $code): void
    {
        if (500 <= $code) {
            throw new ServerException($this);
        }

        if (400 <= $code) {
            throw new ClientException($this);
        }

        if (300 <= $code) {
            throw new RedirectionException($this);
        }
    }
}
