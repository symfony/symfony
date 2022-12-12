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
 * A clock that relies the system time.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class NativeClock implements ClockInterface
{
    private \DateTimeZone $timezone;

    public function __construct(\DateTimeZone|string $timezone = null)
    {
        if (\is_string($timezone ??= date_default_timezone_get())) {
            $this->timezone = new \DateTimeZone($timezone);
        } else {
            $this->timezone = $timezone;
        }
    }

    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', $this->timezone);
    }

    public function sleep(float|int $seconds): void
    {
        if (0 < $s = (int) $seconds) {
            sleep($s);
        }

        if (0 < $us = $seconds - $s) {
            usleep((int) ($us * 1E6));
        }
    }

    public function withTimeZone(\DateTimeZone|string $timezone): static
    {
        $clone = clone $this;
        $clone->timezone = \is_string($timezone) ? new \DateTimeZone($timezone) : $timezone;

        return $clone;
    }
}
