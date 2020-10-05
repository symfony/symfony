<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Retry;

use Symfony\Component\HttpClient\Exception\InvalidArgumentException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * A retry backOff with a constant or exponential retry delay.
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
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
final class ExponentialBackOff implements RetryBackOffInterface
{
    private $delayMilliseconds;
    private $multiplier;
    private $maxDelayMilliseconds;

    /**
     * @param int   $delayMilliseconds    Amount of time to delay (or the initial value when multiplier is used)
     * @param float $multiplier           Multiplier to apply to the delay each time a retry occurs
     * @param int   $maxDelayMilliseconds Maximum delay to allow (0 means no maximum)
     */
    public function __construct(int $delayMilliseconds = 1000, float $multiplier = 2.0, int $maxDelayMilliseconds = 0)
    {
        if ($delayMilliseconds < 0) {
            throw new InvalidArgumentException(sprintf('Delay must be greater than or equal to zero: "%s" given.', $delayMilliseconds));
        }
        $this->delayMilliseconds = $delayMilliseconds;

        if ($multiplier < 1) {
            throw new InvalidArgumentException(sprintf('Multiplier must be greater than or equal to one: "%s" given.', $multiplier));
        }
        $this->multiplier = $multiplier;

        if ($maxDelayMilliseconds < 0) {
            throw new InvalidArgumentException(sprintf('Max delay must be greater than or equal to zero: "%s" given.', $maxDelayMilliseconds));
        }
        $this->maxDelayMilliseconds = $maxDelayMilliseconds;
    }

    public function getDelay(int $retryCount, string $requestMethod, string $requestUrl, array $requestOptions, int $responseStatusCode, array $responseHeaders, ?string $responseContent, ?TransportExceptionInterface $exception): int
    {
        $delay = $this->delayMilliseconds * $this->multiplier ** $retryCount;

        if ($delay > $this->maxDelayMilliseconds && 0 !== $this->maxDelayMilliseconds) {
            return $this->maxDelayMilliseconds;
        }

        return (int) $delay;
    }
}
