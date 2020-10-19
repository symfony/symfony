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
 * Factory provides method to create locks.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 * @author Hamza Amrouche <hamza.simperfit@gmail.com>
 */
class LockFactory extends Factory
{
    /**
     * Creates a lock for the given resource.
     *
     * @param string     $resource    The resource to lock
     * @param float|null $ttl         Maximum expected lock duration in seconds
     * @param bool       $autoRelease Whether to automatically release the lock or not when the lock instance is destroyed
     */
    public function createLock($resource, $ttl = 300.0, $autoRelease = true): LockInterface
    {
        return parent::createLock($resource, $ttl, $autoRelease);
    }
}
