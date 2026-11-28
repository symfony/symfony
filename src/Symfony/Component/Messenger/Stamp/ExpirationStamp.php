<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Stamp;

/**
 * Marks the moment a message stops being worth handling.
 *
 * @see \Symfony\Component\Messenger\Middleware\ExpirationMiddleware
 */
final class ExpirationStamp implements StampInterface
{
    private float $timestamp;

    /**
     * @param \DateTimeInterface|float $timestamp The deadline, as a date or as a Unix timestamp
     */
    public function __construct(\DateTimeInterface|float $timestamp)
    {
        $this->timestamp = $timestamp instanceof \DateTimeInterface ? (float) $timestamp->format('U.u') : $timestamp;
    }

    /**
     * The deadline as a Unix timestamp, which is how it goes through every transport serializer too.
     */
    public function getTimestamp(): float
    {
        return $this->timestamp;
    }

    public function isExpired(\DateTimeImmutable $now): bool
    {
        return $this->timestamp <= (float) $now->format('U.u');
    }
}
