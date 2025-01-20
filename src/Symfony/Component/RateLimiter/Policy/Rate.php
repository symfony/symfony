<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RateLimiter\Policy;

use Symfony\Component\RateLimiter\Util\TimeUtil;

/**
 * Data object representing the fill rate of a token bucket.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
final class Rate
{
    public function __construct(
        private \DateInterval $refillTime,
        private int $refillAmount = 1,
    ) {
    }

    public static function perSecond(int $rate = 1): self
    {
        return new static(new \DateInterval('PT1S'), $rate);
    }

    public static function perMinute(int $rate = 1): self
    {
        return new static(new \DateInterval('PT1M'), $rate);
    }

    public static function perHour(int $rate = 1): self
    {
        return new static(new \DateInterval('PT1H'), $rate);
    }

    public static function perDay(int $rate = 1): self
    {
        return new static(new \DateInterval('P1D'), $rate);
    }

    public static function perMonth(int $rate = 1): self
    {
        return new static(new \DateInterval('P1M'), $rate);
    }

    public static function perYear(int $rate = 1): self
    {
        return new static(new \DateInterval('P1Y'), $rate);
    }

    /**
     * @param string $string using the format: "%interval_spec%-%rate%", {@see DateInterval}
     */
    public static function fromString(string $string): self
    {
        [$interval, $rate] = explode('-', $string, 2);

        return new static(new \DateInterval($interval), $rate);
    }

    /**
     * Calculates the time needed to free up the provided number of tokens in seconds.
     */
    public function calculateTimeForTokens(int $tokens): int
    {
        $cyclesRequired = ceil($tokens / $this->refillAmount);

        return TimeUtil::dateIntervalToSeconds($this->refillTime) * $cyclesRequired;
    }

    /**
     * Calculates the next moment of token availability.
     */
    public function calculateNextTokenAvailability(): \DateTimeImmutable
    {
        return (new \DateTimeImmutable())->add($this->refillTime);
    }

    /**
     * Calculates the number of new free tokens during $duration.
     *
     * @param float $duration interval in seconds
     */
    public function calculateNewTokensDuringInterval(float $duration): int
    {
        $cycles = floor($duration / TimeUtil::dateIntervalToSeconds($this->refillTime));

        return $cycles * $this->refillAmount;
    }

    /**
     * Calculates total amount in seconds of refill intervals during $duration (for maintain strict refill frequency).
     *
     * @param float $duration interval in seconds
     */
    public function calculateRefillInterval(float $duration): int
    {
        $cycleTime = TimeUtil::dateIntervalToSeconds($this->refillTime);

        return floor($duration / $cycleTime) * $cycleTime;
    }

    public function __toString(): string
    {
        return $this->refillTime->format('P%yY%mM%dDT%HH%iM%sS').'-'.$this->refillAmount;
    }
}
