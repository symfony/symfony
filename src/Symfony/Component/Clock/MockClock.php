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
        $now = (float) $this->now->format('Uu') + $seconds * 1e6;
        $now = substr_replace(sprintf('@%07.0F', $now), '.', -6, 0);
        $timezone = $this->now->getTimezone();

        $this->now = (new \DateTimeImmutable($now, $timezone))->setTimezone($timezone);
    }

    public function modify(string $modifier): void
    {
        if (false === $modifiedNow = @$this->now->modify($modifier)) {
            throw new \InvalidArgumentException(sprintf('Invalid modifier: "%s". Could not modify MockClock.', $modifier));
        }

        $this->now = $modifiedNow;
    }

    public function withTimeZone(\DateTimeZone|string $timezone): static
    {
        $clone = clone $this;
        $clone->now = $clone->now->setTimezone(\is_string($timezone) ? new \DateTimeZone($timezone) : $timezone);

        return $clone;
    }
}
