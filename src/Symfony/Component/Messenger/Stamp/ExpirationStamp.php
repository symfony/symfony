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
    /**
     * @var float The deadline as a Unix timestamp, so that it survives every transport serializer
     */
    private float $expiresAt;

    public function __construct(\DateTimeInterface $expiresAt)
    {
        $this->expiresAt = (float) $expiresAt->format('U.u');
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromFormat('U.u', \sprintf('%.6F', $this->expiresAt));
    }

    public function isExpired(\DateTimeImmutable $now): bool
    {
        return $this->expiresAt <= (float) $now->format('U.u');
    }
}
