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

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * Calls callbacks as responses complete.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class CallbackHttpClient implements HttpClientInterface, \Countable
{
    private $client;
    private $onHeaders;
    private $onContent;
    private $onError;
    private $responses;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
        $this->responses = new \SplObjectStorage();
    }

    public function withCallbacks(?callable $onHeaders, callable $onContent = null, callable $onError = null): self
    {
        $new = clone $this;

        $new->responses = $this->responses;
        $new->onHeaders = $onHeaders;
        $new->onContent = $onContent;
        $new->onError = $onError;

        return $new;
    }

    /**
     * Adds a response to the monitored set.
     *
     * The response must be created with the same client that was passed to the constructor.
     */
    public function request(string $method, string $url, array $options): ResponseInterface
    {
        $response = $this->client->request($method, $url, $options);

        if (null !== $this->onHeaders || null !== $this->onContent || null !== $this->onError) {
            $this->responses[$response] = [$onHeaders, $onContent, $onError];
            $this->tick();
        }

        return $response;
    }

    /**
     * {@inheritdoc}
     */
    public function stream($responses, float $timeout = null): ResponseStreamInterface
    {
        return new CallbackResponseStream($this->client->stream($responses, $timeout));
    }

    /**
     * Monitors pending responses, moving them forward as network activity happens.
     *
     * @param float $timeout The maximum duration of the tick
     *
     * @return int The number of responses remaining in the set after the tick
     */
    public function tick(float $timeout = 0.0): int
    {
        return $this->wait($timeout, false);
    }

    /**
     * Completes all pending responses.
     *
     * @param float|null $idleTimeout The maximum inactivy timeout before erroring idle responses
     */
    public function complete(float $idleTimeout = null): void
    {
        $this->wait($idleTimeout, true);
    }

    /**
     * Cancels all pending responses.
     */
    public function cancel(): void
    {
        foreach ($this->responses as $response) {
            $response->cancel();
        }

        $this->responses->removeAll($this->responses);
    }

    /**
     * Returns the number of pending responses.
     */
    public function count(): int
    {
        return \count($this->responses);
    }

    public function __destruct()
    {
        $this->wait(null, true);
    }

    private function wait(?float $timeout, bool $errorOnTimeout): int
    {
        $error = null;
        $remainingTimeout = $timeout;

        if (!$errorOnTimeout && $remainingTimeout) {
            $startTime = microtime(true);
        }

        $stream = $this->client->stream($this->responses, $remainingTimeout);
        $stream = new CallbackResponseStream($stream, true);

        foreach ($stream as $chunk) {
            try {
                if ($chunk->isTimeout() && $errorOnTimeout) {
                    // throw an exception on timeout
                    $chunk->isFirst();
                }
            } catch (ExceptionInterface $e) {
                $error = $error ?? $e;
            } finally {
                if (!$errorOnTimeout && $remainingTimeout) {
                    $remainingTimeout = max(0.0, $timeout - microtime(true) + $startTime);
                }
            }
        }

        if (null !== $error) {
            throw $error;
        }

        return \count($this->responses);
    }
}
