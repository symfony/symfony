<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Semaphore;

use Symfony\Component\Semaphore\Exception\SemaphoreAcquiringException;
use Symfony\Component\Semaphore\Exception\SemaphoreExpiredException;
use Symfony\Component\Semaphore\Exception\SemaphoreReleasingException;

/**
 * SemaphoreInterface defines an interface to manipulate the status of a semaphore.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
interface SemaphoreInterface
{
    /**
     * Acquires the semaphore. If the semaphore has reached its limit.
     *
     * @throws SemaphoreAcquiringException If the semaphore cannot be acquired
     */
    public function acquire(): bool;

    /**
     * Increase the duration of an acquired semaphore.
     *
     * @return void
     *
     * @throws SemaphoreExpiredException If the semaphore has expired
     */
    public function refresh(float $ttlInSecond = null);

    /**
     * Returns whether or not the semaphore is acquired.
     */
    public function isAcquired(): bool;

    /**
     * Release the semaphore.
     *
     * @return void
     *
     * @throws SemaphoreReleasingException If the semaphore cannot be released
     */
    public function release();

    public function isExpired(): bool;

    /**
     * Returns the remaining lifetime.
     */
    public function getRemainingLifetime(): ?float;
}
