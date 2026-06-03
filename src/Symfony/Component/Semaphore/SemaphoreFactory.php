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

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * Factory provides method to create semaphores.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Jérémy Derussé <jeremy@derusse.com>
 * @author Hamza Amrouche <hamza.simperfit@gmail.com>
 */
class SemaphoreFactory implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private PersistingStoreInterface $store,
    ) {
    }

    /**
     * @param float|null $ttlInSecond Maximum expected semaphore duration in seconds
     * @param bool       $autoRelease Whether to automatically release the semaphore or not when the semaphore instance is destroyed
     */
    public function createSemaphore(string $resource, int $limit, int $weight = 1, ?float $ttlInSecond = 300.0, bool $autoRelease = true): SemaphoreInterface
    {
        return $this->createSemaphoreFromKey(new Key($resource, $limit, $weight), $ttlInSecond, $autoRelease);
    }

    /**
     * @param float|null $ttlInSecond Maximum expected semaphore duration in seconds
     * @param bool       $autoRelease Whether to automatically release the semaphore or not when the semaphore instance is destroyed
     */
    public function createSemaphoreFromKey(Key $key, ?float $ttlInSecond = 300.0, bool $autoRelease = true): SemaphoreInterface
    {
        $semaphore = new Semaphore($key, $this->store, $ttlInSecond, $autoRelease);
        if ($this->logger) {
            $semaphore->setLogger($this->logger);
        }

        return $semaphore;
    }
}
