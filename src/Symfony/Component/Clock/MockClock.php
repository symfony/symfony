<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Clock;

/**
 * A clock that always returns the same date, suitable for testing time-sensitive logic.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class MockClock implements ClockInterface
{
    private \DateTimeImmutable $now;

    public function __construct(\DateTimeImmutable|string $now = 'now', \DateTimeZone|string $timezone = null)
    {
        if (\is_string($timezone)) {
            $timezone = new \DateTimeZone($timezone);
        }

        if (\is_string($now)) {
            $now = new \DateTimeImmutable($now, $timezone ?? new \DateTimeZone('UTC'));
        }

        $this->now = null !== $timezone ? $now->setTimezone($timezone) : $now;
    }

    public function now(): \DateTimeImmutable
    {
        return clone $this->now;
    }

    public function sleep(float|int $seconds): void
    {
        $now = explode('.', $this->now->format('U.u'));

        if (0 < $s = (int) $seconds) {
            $now[0] += $s;
        }

        if (0 < ($us = $seconds - $s) && 1E6 <= $now[1] += $us * 1E6) {
            ++$now[0];
            $now[1] -= 1E6;
        }

        $datetime = '@'.$now[0].'.'.str_pad($now[1], 6, '0', \STR_PAD_LEFT);
        $timezone = $this->now->getTimezone();

        $this->now = (new \DateTimeImmutable($datetime, $timezone))->setTimezone($timezone);
    }

    public function withTimeZone(\DateTimeZone|string $timezone): static
    {
        $clone = clone $this;
        $clone->now = $clone->now->setTimezone(\is_string($timezone) ? new \DateTimeZone($timezone) : $timezone);

        return $clone;
    }
}
