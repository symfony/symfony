<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RateLimiter;

use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\ClockInterface as SymfonyClockInterface;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
final class Reservation
{
    /**
     * @param float $timeToAct Unix timestamp in seconds when this reservation should act
     */
    public function __construct(
        private float $timeToAct,
        private RateLimit $rateLimit,
        private ?ClockInterface $clock = null,
    ) {
    }

    public function getTimeToAct(): float
    {
        return $this->timeToAct;
    }

    public function getWaitDuration(): float
    {
        $now = $this->clock ? (float) $this->clock->now()->format('U.u') : microtime(true);

        return max(0, (-$now) + $this->timeToAct);
    }

    public function getRateLimit(): RateLimit
    {
        return $this->rateLimit;
    }

    public function wait(): void
    {
        if ($this->clock instanceof SymfonyClockInterface) {
            $this->clock->sleep($this->getWaitDuration());

            return;
        }

        usleep((int) ($this->getWaitDuration() * 1e6));
    }
}
