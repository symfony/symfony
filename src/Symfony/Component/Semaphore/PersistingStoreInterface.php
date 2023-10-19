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
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
interface PersistingStoreInterface
{
    /**
     * Stores the resource if the semaphore is not full.
     *
     * @return void
     *
     * @throws SemaphoreAcquiringException
     */
    public function save(Key $key, float $ttlInSecond);

    /**
     * Removes a resource from the storage.
     *
     * @return void
     *
     * @throws SemaphoreReleasingException
     */
    public function delete(Key $key);

    /**
     * Returns whether or not the resource exists in the storage.
     */
    public function exists(Key $key): bool;

    /**
     * Extends the TTL of a resource.
     *
     * @return void
     *
     * @throws SemaphoreExpiredException
     */
    public function putOffExpiration(Key $key, float $ttlInSecond);
}
