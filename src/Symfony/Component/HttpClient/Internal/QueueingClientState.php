<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Internal;

use Symfony\Component\HttpClient\Chunk\ErrorChunk;
use Symfony\Component\HttpClient\Chunk\LastChunk;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\Response\QueuedResponse;
use Symfony\Contracts\HttpClient\ChunkInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * @internal
 */
final class QueueingClientState
{
    /** @var \WeakMap<QueuedResponse, true> */
    private \WeakMap $active;
    private int $generation = 0;
    private ?float $lastTimeout = null;

    public function __construct(private int $maxConcurrentRequests)
    {
        $this->active = new \WeakMap();
    }

    public function add(QueuedResponse $response): void
    {
        if ($response->isComplete() || isset($this->active[$response]) || \count($this->active) >= $this->maxConcurrentRequests) {
            return;
        }

        $this->active[$response] = true;
        ++$this->generation;

        try {
            $response->start();
            $this->lastTimeout = null;
        } catch (\Throwable $e) {
            $this->release($response);
            throw $e;
        }
    }

    public function release(QueuedResponse $response): void
    {
        if (isset($this->active[$response])) {
            unset($this->active[$response]);
            ++$this->generation;
        }
    }

    public function stream(iterable $responses, ?float $timeout): \Generator
    {
        $resumeTimeout = 0 > $timeout || (0.0 === $timeout && '-0' === (string) $timeout);

        if ($resumeTimeout) {
            $timeout = $timeout ? -$timeout : null;
        }

        $requested = [];
        $pending = new \SplQueue();
        $idleTimeout = $timeout ?? \INF;
        $seen = [];

        foreach ($responses as $response) {
            if (isset($seen[$id = spl_object_id($response)])) {
                continue;
            }

            $seen[$id] = true;
            $requested[$id] = $response;
            $idleTimeout = min($idleTimeout, $timeout ?? $response->getTimeout());

            while (null !== $chunk = $response->shift()) {
                if (null === $chunk->getError()) {
                    $this->lastTimeout = null;
                }

                if (self::isTerminal($chunk)) {
                    unset($requested[$id]);
                }

                yield $response => $chunk;
            }

            if (!isset($requested[$id])) {
                continue;
            }

            if ($response->isComplete()) {
                unset($requested[$id]);
                $error = $response->getInfo('error');
                yield $response => null !== $error ? new ErrorChunk($response->getOffset(), new TransportException($error)) : new LastChunk($response->getOffset());
            } elseif (null === $response->getResponse()) {
                $pending->enqueue($response);
            }
        }

        unset($responses, $response);
        $lastActivity = $resumeTimeout && null !== $this->lastTimeout ? $this->lastTimeout : hrtime(true) / 1E9;
        $polled = false;
        $activity = true;
        $timedOut = false;

        while ($requested) {
            if ($polled && (0.0 === $timeout ? $timedOut || !$activity : hrtime(true) / 1E9 - $lastActivity >= $idleTimeout)) {
                $idleTimeout = $timeout ?? \INF;

                foreach ($requested as $id => $response) {
                    if (hrtime(true) / 1E9 - $lastActivity < ($timeout ?? $response->getTimeout())) {
                        $idleTimeout = min($idleTimeout, $response->getTimeout());
                        continue;
                    }

                    unset($requested[$id]);
                    $this->lastTimeout ??= $lastActivity;
                    yield $response => new ErrorChunk($response->getOffset(), \sprintf('Idle timeout reached for "%s".', $response->getInfo('url')));
                }
            }

            while (!$pending->isEmpty() && \count($this->active) < $this->maxConcurrentRequests) {
                $response = $pending->dequeue();

                if (!isset($requested[spl_object_id($response)])) {
                    continue;
                }

                if ($response->isComplete()) {
                    unset($requested[spl_object_id($response)]);
                    yield $response => new ErrorChunk($response->getOffset(), new TransportException($response->getInfo('error')));
                    continue;
                }

                try {
                    $this->add($response);
                    $lastActivity = hrtime(true) / 1E9;
                } catch (\Throwable $e) {
                    $response->fail($e);
                    unset($requested[spl_object_id($response)]);
                    yield $response => new ErrorChunk($response->getOffset(), $e);
                }
            }

            if (!$requested) {
                return;
            }

            $groups = [];
            $map = [];

            foreach ($this->active as $response => $unused) {
                if (null === $inner = $response->getResponse()) {
                    throw new TransportException('Cannot read a queued response while another request is being started.');
                }

                $client = $response->getClient();
                $groups[$id = spl_object_id($client)][0] = $client;
                $groups[$id][1][] = $inner;
                $map[spl_object_id($inner)] = $response;
            }

            if (!$groups) {
                return;
            }

            $generation = $this->generation;
            $activity = false;
            $polled = true;
            $timedOut = false;

            foreach ($groups as [$client, $innerResponses]) {
                $wait = 0.0 === $timeout ? 0.0 : min(0.01, max(0, $idleTimeout - (hrtime(true) / 1E9 - $lastActivity)));

                foreach ($client->stream($innerResponses, $wait) as $inner => $chunk) {
                    $response = $map[spl_object_id($inner)];
                    $id = spl_object_id($response);
                    $terminal = self::isTerminal($chunk);
                    $isTimeout = null !== $chunk->getError() && !$terminal;

                    if (null === $chunk->getError() && $chunk->isFirst()) {
                        $inner->getStatusCode();
                    }

                    if ($isTimeout) {
                        if (0.0 === $timeout) {
                            $timedOut = true;

                            if (isset($requested[$id])) {
                                unset($requested[$id]);
                                $this->lastTimeout ??= $lastActivity;
                                yield $response => $chunk;
                            }
                        }

                        if (0.0 !== $timeout && hrtime(true) / 1E9 - $lastActivity >= $idleTimeout) {
                            continue 3;
                        }

                        continue;
                    }

                    if (isset($requested[$id])) {
                        $activity = true;
                        $lastActivity = hrtime(true) / 1E9;
                        $this->lastTimeout = null;
                    }

                    if ($terminal) {
                        $response->complete();
                        $this->release($response);
                    }

                    if (isset($requested[$id])) {
                        if ($terminal) {
                            unset($requested[$id]);
                        }

                        if ($chunk instanceof ErrorChunk) {
                            $chunk->didThrow(false);
                        }

                        yield $response => $chunk;
                    } else {
                        $response->push($chunk);
                    }

                    if ($response->getInfo('canceled')) {
                        unset($requested[$id]);
                    }

                    if ($generation !== $this->generation || (0.0 !== $timeout && hrtime(true) / 1E9 - $lastActivity >= $idleTimeout)) {
                        continue 3;
                    }
                }
            }

            if (!$activity && 0.0 !== $timeout) {
                usleep(1000);
            }
        }
    }

    private static function isTerminal(ChunkInterface $chunk): bool
    {
        if (null === $chunk->getError()) {
            return $chunk->isLast();
        }

        try {
            return !$chunk->isTimeout();
        } catch (TransportExceptionInterface) {
            return true;
        }
    }
}
