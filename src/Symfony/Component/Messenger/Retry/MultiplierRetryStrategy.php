<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Retry;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

/**
 * A retry strategy with a constant or exponential retry delay.
 *
 * For example, if $delayMilliseconds=10000 & $multiplier=1 (default),
 * each retry will wait exactly 10 seconds.
 *
 * But if $delayMilliseconds=10000 & $multiplier=2:
 *      * Retry 1: 10 second delay
 *      * Retry 2: 20 second delay (10000 * 2 = 20000)
 *      * Retry 3: 40 second delay (20000 * 2 = 40000)
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 *
 * @final
 */
class MultiplierRetryStrategy implements RetryStrategyInterface
{
    /**
     * @param int   $maxRetries           The maximum number of times to retry
     * @param int   $delayMilliseconds    Amount of time to delay (or the initial value when multiplier is used)
     * @param float $multiplier           Multiplier to apply to the delay each time a retry occurs
     * @param int   $maxDelayMilliseconds Maximum delay to allow (0 means no maximum)
     * @param float $jitter               Randomness to apply to the delay (between 0 and 1)
     */
    public function __construct(
        private int $maxRetries = 3,
        private int $delayMilliseconds = 1000,
        private float $multiplier = 1,
        private int $maxDelayMilliseconds = 0,
        private float $jitter = 0.1,
    ) {
        if ($delayMilliseconds < 0) {
            throw new InvalidArgumentException(\sprintf('Delay must be greater than or equal to zero: "%s" given.', $delayMilliseconds));
        }

        if ($multiplier < 1) {
            throw new InvalidArgumentException(\sprintf('Multiplier must be greater than zero: "%s" given.', $multiplier));
        }

        if ($maxDelayMilliseconds < 0) {
            throw new InvalidArgumentException(\sprintf('Max delay must be greater than or equal to zero: "%s" given.', $maxDelayMilliseconds));
        }

        if ($jitter < 0 || $jitter > 1) {
            throw new InvalidArgumentException(\sprintf('Jitter must be between 0 and 1: "%s" given.', $jitter));
        }
    }

    /**
     * @param \Throwable|null $throwable The cause of the failed handling
     */
    public function isRetryable(Envelope $message, ?\Throwable $throwable = null): bool
    {
        $retries = RedeliveryStamp::getRetryCountFromEnvelope($message);

        return $retries < $this->maxRetries;
    }

    /**
     * @param \Throwable|null $throwable The cause of the failed handling
     */
    public function getWaitingTime(Envelope $message, ?\Throwable $throwable = null): int
    {
        $retries = RedeliveryStamp::getRetryCountFromEnvelope($message);

        $delay = $this->delayMilliseconds * $this->multiplier ** $retries;

        if ($this->jitter > 0) {
            $randomness = (int) min(\PHP_INT_MAX, $delay * $this->jitter);
            $delay += random_int(-$randomness, +$randomness);
        }

        if ($delay > $this->maxDelayMilliseconds && 0 !== $this->maxDelayMilliseconds) {
            return $this->maxDelayMilliseconds;
        }

        return (int) min(\PHP_INT_MAX, ceil($delay));
    }
}
