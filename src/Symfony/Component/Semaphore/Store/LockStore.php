<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Semaphore\Store;

use Symfony\Component\Lock\Exception\LockReleasingException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Semaphore\Exception\SemaphoreAcquiringException;
use Symfony\Component\Semaphore\Exception\SemaphoreExpiredException;
use Symfony\Component\Semaphore\Exception\SemaphoreReleasingException;
use Symfony\Component\Semaphore\Key;
use Symfony\Component\Semaphore\PersistingStoreInterface;

/**
 * @author Alexander Schranz <alexander@sulu.io>
 */
final class LockStore implements PersistingStoreInterface
{
    public function __construct(
        private readonly LockFactory $lockFactory,
    ) {
    }

    public function save(Key $key, float $ttlInSecond): void
    {
        if ($this->getExistingLocks($key)) {
            return;
        }

        $locks = $this->createLocks($key, $ttlInSecond);

        $key->setState(__CLASS__, $locks);
        $key->markUnserializable();
    }

    public function delete(Key $key): void
    {
        $this->releaseLocks($this->getExistingLocks($key), $key);
    }

    public function exists(Key $key): bool
    {
        return \count($this->getExistingLocks($key)) >= $key->getWeight();
    }

    public function putOffExpiration(Key $key, float $ttlInSecond): void
    {
        $locks = $this->getExistingLocks($key);

        if (\count($locks) !== $key->getWeight()) {
            $this->releaseLocks($locks, $key);

            throw new SemaphoreExpiredException($key, 'One or multiple locks were not even acquired.');
        }

        foreach ($locks as $lock) {
            $lock->refresh($ttlInSecond);

            if ($lock->isExpired()) {
                $this->releaseLocks($locks, $key);

                throw new SemaphoreExpiredException($key, 'Failed to refresh one or multiple locks.');
            }
        }
    }

    /**
     * @param array<LockInterface> $locks
     */
    private function releaseLocks(array $locks, Key $key): void
    {
        $lockReleasingException = null;
        foreach ($locks as $lock) {
            try {
                $lock->release();
            } catch (LockReleasingException $e) {
                $lockReleasingException ??= $e;
            }
        }

        $key->removeState(__CLASS__);

        if ($lockReleasingException) {
            throw new SemaphoreReleasingException($key, $lockReleasingException->getMessage());
        }
    }

    /**
     * @return array<LockInterface>
     */
    private function getExistingLocks(Key $key): array
    {
        return $key->hasState(__CLASS__) ? $key->getState(__CLASS__) : [];
    }

    /**
     * @return array<LockInterface>
     */
    private function createLocks(Key $key, float $ttlInSecond): array
    {
        $locks = [];
        $lockName = (string) $key;
        $limit = $key->getLimit();

        // use a random start point to have a higher chance to catch a free slot directly
        $startPoint = random_int(0, $limit - 1);

        $previousException = null;
        try {
            for ($i = 0; $i < $limit; ++$i) {
                $index = ($startPoint + $i) % $limit;

                $lock = $this->lockFactory->createLock($lockName.'_'.$index, $ttlInSecond, false);
                if ($lock->acquire(false)) { // use lock if we can acquire it else try to catch next lock
                    $locks[] = $lock;

                    if (\count($locks) >= $key->getWeight()) {
                        break;
                    }

                    continue;
                }

                if (\count($locks) + $key->getLimit() - $i < $key->getWeight()) { // no chance to get enough locks
                    break;
                }
            }
        } catch (\Exception $e) {
            $previousException = $e;
        }

        if (\count($locks) === $key->getWeight()) {
            return $locks;
        }

        try {
            // release already acquired locks because not got the amount of locks which were required
            $this->releaseLocks($locks, $key);
        } catch (SemaphoreReleasingException) {
            // ignore releasing exception because we will throw an acquiring exception in the end
        }

        throw $previousException ?? new SemaphoreAcquiringException($key, 'There were no free locks found');
    }
}
