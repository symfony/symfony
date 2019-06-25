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

/**
 * Monitors a set of responses and triggers callbacks as they complete.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class ResponseSetMonitor implements \Countable
{
    private $client;
    private $onHeaders;
    private $onBody;
    private $onError;
    private $responses;

    public function __construct(HttpClientInterface $client, callable $onHeaders = null, callable $onBody = null, callable $onError = null)
    {
        $this->client = $client;
        $this->onHeaders = $onHeaders;
        $this->onBody = $onBody;
        $this->onError = $onError;
        $this->responses = new \SplObjectStorage();
    }

    /**
     * Adds a response to the monitored set.
     *
     * The response must be created with the same client that was passed to the constructor.
     */
    public function add(ResponseInterface $response, callable $onHeaders = null, callable $onBody = null, callable $onError = null): void
    {
        $this->responses[$response] = [$onHeaders, $onBody, $onError];
        $this->tick();
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

        $this->responses = new \SplObjectStorage();
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

        foreach ($this->client->stream($this->responses, $remainingTimeout) as $response => $chunk) {
            try {
                if ($chunk->isTimeout() && !$errorOnTimeout) {
                    continue;
                }

                if (!$chunk->isFirst() && !$chunk->isLast()) {
                    continue;
                }

                [$onHeaders, $onBody] = $this->responses[$response];
                $onHeaders = $onHeaders ?? $this->onHeaders;
                $onBody = $onBody ?? $this->onBody;

                if (null !== $onHeaders && $chunk->isFirst()) {
                    $onHeaders($response);
                }

                if (null !== $onBody && $chunk->isLast()) {
                    $onBody($response);
                }

                if (null === $onBody || $chunk->isLast()) {
                    unset($this->responses[$response]);
                }
            } catch (ExceptionInterface $e) {
                [, , $onError] = $this->responses[$response];
                $onError = $onError ?? $this->onError;
                unset($this->responses[$response]);

                if (null !== $onError) {
                    $onError($e, $response);
                } else {
                    $error = $error ?? $e;
                }
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
