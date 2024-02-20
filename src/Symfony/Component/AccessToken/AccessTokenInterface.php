<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare (strict_types=1);

namespace Symfony\Component\AccessToken;

/**
 * @author Pierre Rineau <pierre.rineau@processus.org>
 */
interface AccessTokenInterface
{
    /**
     * Get token type.
     */
    public function getType(): string;

    /**
     * Get access token value.
     */
    public function getValue(): string;

    /**
     * Get original lifetime in seconds.
     */
    public function getExpiresIn(): int;

    /**
     * Get date at which this access token was issued.
     */
    public function getIssuedAt(): \DateTimeImmutable;

    /**
     * Get expiry time, computed from issued at and lifetime.
     */
    public function getExpiresAt(): \DateTimeImmutable;

    /**
     * Has this token expired.
     */
    public function hasExpired(): bool;

    /**
     * Has this token expired at the given date.
     */
    public function hasExpiredAt(\DateTimeInterface $date): bool;

    /**
     * Get credentials identifier that were used for generating this token.
     */
    public function getCredentialsId(): string;

    /**
     * Alias of getValue().
     */
    public function __toString(): string;
}
