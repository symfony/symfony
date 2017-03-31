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
use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Exception\LockExpiredException;
use Symfony\Component\Lock\Exception\LockReleasingException;

/**
 * Lock is the default implementation of the LockInterface.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
final class Lock implements LockInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private $store;
    private $key;
    private $ttl;
    private $dirty = false;

    /**
     * @param Key            $key   Resource to lock
     * @param StoreInterface $store Store used to handle lock persistence
     * @param float|null     $ttl   Maximum expected lock duration in seconds
     */
    public function __construct(Key $key, StoreInterface $store, $ttl = null)
    {
        $this->store = $store;
        $this->key = $key;
        $this->ttl = $ttl;

        $this->logger = new NullLogger();
    }

    /**
     * Automatically release the underlying lock when the object is destructed.
     */
    public function __destruct()
    {
        try {
            if ($this->dirty && $this->isAcquired()) {
                $this->release();
            }
        } catch (\Throwable $e) {
        } catch (\Exception $e) {
        }
    }

    /**
     * {@inheritdoc}
     */
    public function acquire($blocking = false)
    {
        try {
            if (!$blocking) {
                $this->store->save($this->key);
            } else {
                $this->store->waitAndSave($this->key);
            }

            $this->dirty = true;
            $this->logger->info('Successfully acquired the "{resource}" lock.', array('resource' => $this->key));

            if ($this->ttl) {
                $this->refresh();
            }

            if ($this->key->isExpired()) {
                throw new LockExpiredException(sprintf('Failed to store the "%s" lock.', $this->key));
            }

            return true;
        } catch (LockConflictedException $e) {
            $this->dirty = false;
            $this->logger->warning('Failed to acquire the "{resource}" lock. Someone else already acquired the lock.', array('resource' => $this->key));

            if ($blocking) {
                throw $e;
            }

            return false;
        } catch (\Exception $e) {
            $this->logger->warning('Failed to acquire the "{resource}" lock.', array('resource' => $this->key, 'exception' => $e));
            throw new LockAcquiringException(sprintf('Failed to acquire the "%s" lock.', $this->key), 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function refresh()
    {
        if (!$this->ttl) {
            throw new InvalidArgumentException('You have to define an expiration duration.');
        }

        try {
            $this->key->resetLifetime();
            $this->store->putOffExpiration($this->key, $this->ttl);
            $this->dirty = true;

            if ($this->key->isExpired()) {
                throw new LockExpiredException(sprintf('Failed to put off the expiration of the "%s" lock within the specified time.', $this->key));
            }

            $this->logger->info('Expiration defined for "{resource}" lock for "{ttl}" seconds.', array('resource' => $this->key, 'ttl' => $this->ttl));
        } catch (LockConflictedException $e) {
            $this->dirty = false;
            $this->logger->warning('Failed to define an expiration for the "{resource}" lock, someone else acquired the lock.', array('resource' => $this->key));
            throw $e;
        } catch (\Exception $e) {
            $this->logger->warning('Failed to define an expiration for the "{resource}" lock.', array('resource' => $this->key, 'exception' => $e));
            throw new LockAcquiringException(sprintf('Failed to define an expiration for the "%s" lock.', $this->key), 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isAcquired()
    {
        return $this->dirty = $this->store->exists($this->key);
    }

    /**
     * {@inheritdoc}
     */
    public function release()
    {
        $this->store->delete($this->key);
        $this->dirty = false;

        if ($this->store->exists($this->key)) {
            $this->logger->warning('Failed to release the "{resource}" lock.', array('resource' => $this->key));
            throw new LockReleasingException(sprintf('Failed to release the "%s" lock.', $this->key));
        }
    }

    /**
     * @return bool
     */
    public function isExpired()
    {
        return $this->key->isExpired();
    }

    /**
     * Returns the remaining lifetime.
     *
     * @return float|null Remaining lifetime in seconds. Null when the lock won't expire.
     */
    public function getRemainingLifetime()
    {
        return $this->key->getRemainingLifetime();
    }
}
