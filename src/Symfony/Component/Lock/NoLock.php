<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock;

/**
 * A non locking lock.
 *
 * This can be used to disable locking in classes
 * requiring a lock.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
final class NoLock implements LockInterface
{
    public function acquire(bool $blocking = false): bool
    {
        return true;
    }

    public function refresh(float $ttl = null): void
    {
    }

    public function isAcquired(): bool
    {
        return true;
    }

    public function release(): void
    {
    }

    public function isExpired(): bool
    {
        return false;
    }

    public function getRemainingLifetime(): ?float
    {
        return null;
    }
}
