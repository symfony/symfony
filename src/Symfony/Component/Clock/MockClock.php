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
 * Consider using ClockSensitiveTrait in your test cases instead of using this class directly.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class MockClock implements ClockInterface
{
    private DatePoint $now;

    /**
     * @throws \DateMalformedStringException When $now is invalid
     * @throws \DateInvalidTimeZoneException When $timezone is invalid
     */
    public function __construct(\DateTimeImmutable|string $now = 'now', \DateTimeZone|string|null $timezone = null)
    {
        if (\is_string($timezone)) {
            $timezone = new \DateTimeZone($timezone);
        }

        if (\is_string($now)) {
            $now = new DatePoint($now, $timezone ?? new \DateTimeZone('UTC'));
        } elseif (!$now instanceof DatePoint) {
            $now = DatePoint::createFromInterface($now);
        }

        $this->now = null !== $timezone ? $now->setTimezone($timezone) : $now;
    }

    public function now(): DatePoint
    {
        return clone $this->now;
    }

    public function sleep(float|int $seconds): void
    {
        if (0 >= $seconds) {
            return;
        }

        $s = (float) $this->now->format('U') + floor($seconds);
        $us = (int) $this->now->format('u') + (int) round(fmod($seconds, 1) * 1e6);

        if (1000000 <= $us) {
            ++$s;
            $us -= 1000000;
        }

        $this->now = DatePoint::createFromFormat('U.u', \sprintf('%.0F.%06d', $s, $us))->setTimezone($this->now->getTimezone());
    }

    /**
     * @throws \DateMalformedStringException When $modifier is invalid
     */
    public function modify(string $modifier): void
    {
        $this->now = $this->now->modify($modifier);
    }

    /**
     * @throws \DateInvalidTimeZoneException When the timezone name is invalid
     */
    public function withTimeZone(\DateTimeZone|string $timezone): static
    {
        if (\is_string($timezone)) {
            $timezone = new \DateTimeZone($timezone);
        }

        $clone = clone $this;
        $clone->now = $clone->now->setTimezone($timezone);

        return $clone;
    }
}
