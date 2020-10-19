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
 *
 * @deprecated "Symfony\Component\Lock\Factory" is deprecated since Symfony 4.4 and will be removed in 5.0 use "Symfony\Component\Lock\LockFactory" instead
 */
class Factory implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private $store;

    public function __construct(PersistingStoreInterface $store)
    {
        $this->store = $store;

        $this->logger = new NullLogger();
    }

    /**
     * Creates a lock for the given resource.
     *
     * @param string     $resource    The resource to lock
     * @param float|null $ttl         Maximum expected lock duration in seconds
     * @param bool       $autoRelease Whether to automatically release the lock or not when the lock instance is destroyed
     *
     * @return Lock
     */
    public function createLock($resource, $ttl = 300.0, $autoRelease = true)
    {
        $lock = new Lock(new Key($resource), $this->store, $ttl, $autoRelease);
        $lock->setLogger($this->logger);

        return $lock;
    }
}
