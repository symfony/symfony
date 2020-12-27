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
 * @author Jérémy Derussé <jeremy@derusse.com>
 * @author Hamza Amrouche <hamza.simperfit@gmail.com>
 */
class LockFactory implements LoggerAwareInterface, LockFactoryInterface
{
    use LoggerAwareTrait;

    private $store;

    public function __construct(PersistingStoreInterface $store)
    {
        $this->store = $store;

        $this->logger = new NullLogger();
    }

    public function createLock(string $resource, ?float $ttl = 300.0, bool $autoRelease = true): LockInterface
    {
        return $this->createLockFromKey(new Key($resource), $ttl, $autoRelease);
    }

    public function createLockFromKey(Key $key, ?float $ttl = 300.0, bool $autoRelease = true): LockInterface
    {
        $lock = new Lock($key, $this->store, $ttl, $autoRelease);
        $lock->setLogger($this->logger);

        return $lock;
    }
}
