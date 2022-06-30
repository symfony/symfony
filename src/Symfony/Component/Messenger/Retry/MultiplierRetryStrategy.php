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
    private $maxRetries;
    private $delayMilliseconds;
    private $multiplier;
    private $maxDelayMilliseconds;

    /**
     * @param int   $maxRetries           The maximum number of times to retry
     * @param int   $delayMilliseconds    Amount of time to delay (or the initial value when multiplier is used)
     * @param float $multiplier           Multiplier to apply to the delay each time a retry occurs
     * @param int   $maxDelayMilliseconds Maximum delay to allow (0 means no maximum)
     */
    public function __construct(int $maxRetries = 3, int $delayMilliseconds = 1000, float $multiplier = 1, int $maxDelayMilliseconds = 0)
    {
        $this->maxRetries = $maxRetries;

        if ($delayMilliseconds < 0) {
            throw new InvalidArgumentException(sprintf('Delay must be greater than or equal to zero: "%s" given.', $delayMilliseconds));
        }
        $this->delayMilliseconds = $delayMilliseconds;

        if ($multiplier < 1) {
            throw new InvalidArgumentException(sprintf('Multiplier must be greater than zero: "%s" given.', $multiplier));
        }
        $this->multiplier = $multiplier;

        if ($maxDelayMilliseconds < 0) {
            throw new InvalidArgumentException(sprintf('Max delay must be greater than or equal to zero: "%s" given.', $maxDelayMilliseconds));
        }
        $this->maxDelayMilliseconds = $maxDelayMilliseconds;
    }

    /**
     * @param \Throwable|null $throwable The cause of the failed handling
     */
    public function isRetryable(Envelope $message, \Throwable $throwable = null): bool
    {
        $retries = RedeliveryStamp::getRetryCountFromEnvelope($message);

        return $retries < $this->maxRetries;
    }

    /**
     * @param \Throwable|null $throwable The cause of the failed handling
     */
    public function getWaitingTime(Envelope $message, \Throwable $throwable = null): int
    {
        $retries = RedeliveryStamp::getRetryCountFromEnvelope($message);

        $delay = $this->delayMilliseconds * $this->multiplier ** $retries;

        if ($delay > $this->maxDelayMilliseconds && 0 !== $this->maxDelayMilliseconds) {
            return $this->maxDelayMilliseconds;
        }

        return (int) ceil($delay);
    }
}
