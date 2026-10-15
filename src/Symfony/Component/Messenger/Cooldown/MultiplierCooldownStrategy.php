<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Cooldown;

use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Exception\RecoverableExceptionInterface;
use Symfony\Component\Messenger\Exception\RejectRedeliveredMessageException;
use Symfony\Component\Messenger\Exception\UnrecoverableExceptionInterface;
use Symfony\Component\Messenger\Middleware\RejectRedeliveredMessageMiddleware;
use Symfony\Component\Messenger\Retry\RetryDecider;

/**
 * Cools a transport down once messages received from it, or fetching from it, failed a number of times in a row.
 *
 * For example, with $failureThreshold=3, $delayMilliseconds=1000 and $multiplier=2:
 *      * 3 failures in a row: the transport cools down for 1 second
 *      * 4 failures in a row: 2 seconds (1000 * 2 = 2000)
 *      * 5 failures in a row: 4 seconds (2000 * 2 = 4000)
 *
 * A cooldown lasts at least the retry delay that the failure asks for through {@see RecoverableExceptionInterface::getRetryDelay()}, up to the maximum duration.
 *
 * A message handled successfully resets the count. Some failures are not counted:
 *      * the ones that forbid a retry, as an {@see UnrecoverableExceptionInterface} does, because the message itself caused them
 *      * the redeliveries rejected by {@see RejectRedeliveredMessageMiddleware}, because no handler ran
 *      * the ones during a cooldown, because their messages were in flight when it started
 *
 * The count and the cooldowns are kept in memory, so each worker counts its own failures and cools down on its own.
 */
final class MultiplierCooldownStrategy implements CooldownStrategyInterface
{
    /**
     * @var array<string, int>
     */
    private array $failureCounts = [];
    /**
     * @var array<string, float>
     */
    private array $cooldownEnds = [];

    /**
     * @param int   $failureThreshold     Number of failures in a row that start a cooldown
     * @param int   $delayMilliseconds    Duration of the first cooldown (or the initial value when multiplier is used)
     * @param float $multiplier           Multiplier to apply to the duration for each further failure in a row
     * @param int   $maxDelayMilliseconds Maximum duration to allow (0 means no maximum)
     * @param float $jitter               Randomness to apply to the duration (between 0 and 1)
     */
    public function __construct(
        private int $failureThreshold = 5,
        private int $delayMilliseconds = 1000,
        private float $multiplier = 2,
        private int $maxDelayMilliseconds = 60000,
        private float $jitter = 0.1,
        private ?LoggerInterface $logger = null,
        private ClockInterface $clock = new Clock(),
    ) {
        if ($failureThreshold < 1) {
            throw new InvalidArgumentException(\sprintf('Failure threshold must be greater than zero: "%s" given.', $failureThreshold));
        }

        if ($delayMilliseconds < 0) {
            throw new InvalidArgumentException(\sprintf('Delay must be greater than or equal to zero: "%s" given.', $delayMilliseconds));
        }

        if ($multiplier < 1) {
            throw new InvalidArgumentException(\sprintf('Multiplier must be greater than or equal to one: "%s" given.', $multiplier));
        }

        if ($maxDelayMilliseconds < 0) {
            throw new InvalidArgumentException(\sprintf('Max delay must be greater than or equal to zero: "%s" given.', $maxDelayMilliseconds));
        }

        if ($jitter < 0 || $jitter > 1) {
            throw new InvalidArgumentException(\sprintf('Jitter must be between 0 and 1: "%s" given.', $jitter));
        }
    }

    public function checkStatus(string $transportName): CooldownStatus
    {
        if (null === $end = $this->findCooldownEnd($transportName)) {
            unset($this->cooldownEnds[$transportName]);

            return CooldownStatus::inactive();
        }

        return CooldownStatus::until(\DateTimeImmutable::createFromTimestamp($end));
    }

    public function recordSuccess(string $transportName, Envelope $envelope): void
    {
        unset($this->failureCounts[$transportName]);
    }

    public function recordFailure(string $transportName, ?Envelope $envelope, \Throwable $throwable): void
    {
        if ($throwable instanceof RejectRedeliveredMessageException || false === RetryDecider::decideFromException($throwable) || null !== $this->findCooldownEnd($transportName)) {
            return;
        }

        $failureCount = $this->failureCounts[$transportName] = ($this->failureCounts[$transportName] ?? 0) + 1;

        if ($failureCount < $this->failureThreshold) {
            return;
        }

        if (0 >= $delay = $this->getDelay($failureCount - $this->failureThreshold, $throwable)) {
            return;
        }

        $this->cooldownEnds[$transportName] = $this->now() + $delay / 1000;

        $this->logger?->warning('Transport {transport} cools down for {delay} ms after {count} failure(s) in a row.', [
            'transport' => $transportName,
            'delay' => $delay,
            'count' => $failureCount,
        ]);
    }

    private function getDelay(int $furtherFailures, \Throwable $throwable): int
    {
        $delay = $this->delayMilliseconds * $this->multiplier ** $furtherFailures;

        if ($this->jitter > 0) {
            $randomness = (int) min(\PHP_INT_MAX, $delay * $this->jitter);
            $delay += random_int(-$randomness, +$randomness);
        }

        // the retry delay asked by the failure, like the "Retry-After" header of an API, is a minimum
        $delay = max($delay, RetryDecider::getDelayFromException($throwable) ?? 0);

        if ($delay > $this->maxDelayMilliseconds && 0 !== $this->maxDelayMilliseconds) {
            return $this->maxDelayMilliseconds;
        }

        return (int) min(\PHP_INT_MAX, ceil($delay));
    }

    private function findCooldownEnd(string $transportName): ?float
    {
        return ($this->cooldownEnds[$transportName] ?? 0) > $this->now() ? $this->cooldownEnds[$transportName] : null;
    }

    private function now(): float
    {
        $now = $this->clock->now();

        return \PHP_INT_SIZE >= 8 ? $now->getTimestamp() + $now->getMicrosecond() / 1e6 : (float) $now->format('U.u');
    }
}
