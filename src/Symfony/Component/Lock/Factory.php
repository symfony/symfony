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

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

/**
 * Factory provides method to create locks.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class Factory implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private $store;

    public function __construct(StoreInterface $store)
    {
        $this->store = $store;

        $this->logger = new NullLogger();
    }

    /**
     * Creates a lock for the given resource.
     *
     * @param string $resource The resource to lock
     * @param float  $ttl      Maximum expected lock duration in seconds
     *
     * @return Lock
     */
    public function createLock($resource, $ttl = 300.0)
    {
        $lock = new Lock(new Key($resource), $this->store, $ttl);
        $lock->setLogger($this->logger);

        return $lock;
    }

    /**
     * Create a scoped lock for the given resource.
     *
     * @param string $resource The resource to lock
     * @param float  $ttl      Maximum expected lock duration in seconds
     *
     * @return ScopedLock
     */
    public function createScopedLock($resource, $ttl = 300.0)
    {
        return new ScopedLock($this->createLock($resource, $ttl));
    }
}
