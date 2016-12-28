<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Store;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Exception\NotSupportedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\QuorumInterface;
use Symfony\Component\Lock\StoreInterface;

/**
 * CombinedStore is a StoreInterface implementation able to manage and synchronize several StoreInterfaces.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class CombinedStore implements StoreInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /** @var StoreInterface[] */
    private $stores;
    /** @var QuorumInterface */
    private $quorum;

    /**
     * @param StoreInterface[] $stores The list of synchronized stores
     * @param QuorumInterface  $quorum
     *
     * @throws InvalidArgumentException
     */
    public function __construct(array $stores, QuorumInterface $quorum)
    {
        foreach ($stores as $store) {
            if (!$store instanceof StoreInterface) {
                throw new InvalidArgumentException(sprintf('The store must implement "%s". Got "%s".', StoreInterface::class, get_class($store)));
            }
        }

        $this->stores = $stores;
        $this->quorum = $quorum;
        $this->logger = new NullLogger();
    }

    /**
     * {@inheritdoc}
     */
    public function save(Key $key)
    {
        $successCount = 0;
        $failureCount = 0;
        $storesCount = count($this->stores);

        foreach ($this->stores as $store) {
            try {
                $store->save($key);
                ++$successCount;
            } catch (\Exception $e) {
                $this->logger->warning('One store failed to save the "{resource}" lock.', array('resource' => $key, 'store' => $store, 'exception' => $e));
                ++$failureCount;
            }

            if (!$this->quorum->canBeMet($failureCount, $storesCount)) {
                break;
            }
        }

        if ($this->quorum->isMet($successCount, $storesCount)) {
            return;
        }

        $this->logger->warning('Failed to store the "{resource}" lock. Quorum has not been met', array('resource' => $key, 'success' => $successCount, 'failure' => $failureCount));

        // clean up potential locks
        $this->delete($key);

        throw new LockConflictedException();
    }

    public function waitAndSave(Key $key)
    {
        throw new NotSupportedException(sprintf('The store "%s" does not supports blocking locks', get_class($this)));
    }

    /**
     * {@inheritdoc}
     */
    public function putOffExpiration(Key $key, $ttl)
    {
        $successCount = 0;
        $failureCount = 0;
        $storesCount = count($this->stores);

        foreach ($this->stores as $store) {
            try {
                $store->putOffExpiration($key, $ttl);
                ++$successCount;
            } catch (\Exception $e) {
                $this->logger->warning('One store failed to put off the expiration of the "{resource}" lock.', array('resource' => $key, 'store' => $store, 'exception' => $e));
                ++$failureCount;
            }

            if (!$this->quorum->canBeMet($failureCount, $storesCount)) {
                break;
            }
        }

        if ($this->quorum->isMet($successCount, $storesCount)) {
            return;
        }

        $this->logger->warning('Failed to define the expiration for the "{resource}" lock. Quorum has not been met', array('resource' => $key, 'success' => $successCount, 'failure' => $failureCount));

        // clean up potential locks
        $this->delete($key);

        throw new LockConflictedException();
    }

    /**
     * {@inheritdoc}
     */
    public function delete(Key $key)
    {
        foreach ($this->stores as $store) {
            try {
                $store->delete($key);
            } catch (\Exception $e) {
                $this->logger->notice('One store failed to delete the "{resource}" lock.', array('resource' => $key, 'store' => $store, 'exception' => $e));
            } catch (\Throwable $e) {
                $this->logger->notice('One store failed to delete the "{resource}" lock.', array('resource' => $key, 'store' => $store, 'exception' => $e));
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function exists(Key $key)
    {
        $successCount = 0;
        $failureCount = 0;
        $storesCount = count($this->stores);

        foreach ($this->stores as $store) {
            if ($store->exists($key)) {
                ++$successCount;
            } else {
                ++$failureCount;
            }

            if ($this->quorum->isMet($successCount, $storesCount)) {
                return true;
            }
            if (!$this->quorum->canBeMet($failureCount, $storesCount)) {
                return false;
            }
        }

        return false;
    }
}
