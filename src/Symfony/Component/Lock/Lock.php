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
    private $autoRelease;
    private $dirty = false;

    /**
     * @param Key            $key         Resource to lock
     * @param StoreInterface $store       Store used to handle lock persistence
     * @param float|null     $ttl         Maximum expected lock duration in seconds
     * @param bool           $autoRelease Whether to automatically release the lock or not when the lock instance is destroyed
     */
    public function __construct(Key $key, StoreInterface $store, float $ttl = null, bool $autoRelease = true)
    {
        $this->store = $store;
        $this->key = $key;
        $this->ttl = $ttl;
        $this->autoRelease = $autoRelease;

        $this->logger = new NullLogger();
    }

    /**
     * Automatically releases the underlying lock when the object is destructed.
     */
    public function __destruct()
    {
        if (!$this->autoRelease || !$this->dirty || !$this->isAcquired()) {
            return;
        }

        $this->release();
    }

    /**
     * {@inheritdoc}
     */
    public function acquire($blocking = false)
    {
        try {
            if ($blocking) {
                $this->store->waitAndSave($this->key);
            } else {
                $this->store->save($this->key);
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
            $this->logger->notice('Failed to acquire the "{resource}" lock. Someone else already acquired the lock.', array('resource' => $this->key));

            if ($blocking) {
                throw $e;
            }

            return false;
        } catch (\Exception $e) {
            $this->logger->notice('Failed to acquire the "{resource}" lock.', array('resource' => $this->key, 'exception' => $e));
            throw new LockAcquiringException(sprintf('Failed to acquire the "%s" lock.', $this->key), 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function refresh($ttl = null)
    {
        if (null === $ttl) {
            $ttl = $this->ttl;
        }
        if (!$ttl) {
            throw new InvalidArgumentException('You have to define an expiration duration.');
        }

        try {
            $this->key->resetLifetime();
            $this->store->putOffExpiration($this->key, $ttl);
            $this->dirty = true;

            if ($this->key->isExpired()) {
                throw new LockExpiredException(sprintf('Failed to put off the expiration of the "%s" lock within the specified time.', $this->key));
            }

            $this->logger->info('Expiration defined for "{resource}" lock for "{ttl}" seconds.', array('resource' => $this->key, 'ttl' => $ttl));
        } catch (LockConflictedException $e) {
            $this->dirty = false;
            $this->logger->notice('Failed to define an expiration for the "{resource}" lock, someone else acquired the lock.', array('resource' => $this->key));
            throw $e;
        } catch (\Exception $e) {
            $this->logger->notice('Failed to define an expiration for the "{resource}" lock.', array('resource' => $this->key, 'exception' => $e));
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
        try {
            try {
                $this->store->delete($this->key);
                $this->dirty = false;
            } catch (LockReleasingException $e) {
                throw $e;
            } catch (\Exception $e) {
                throw new LockReleasingException(sprintf('Failed to release the "%s" lock.', $this->key), 0, $e);
            }

            if ($this->store->exists($this->key)) {
                throw new LockReleasingException(sprintf('Failed to release the "%s" lock, the resource is still locked.', $this->key));
            }
        } catch (LockReleasingException $e) {
            $this->logger->notice('Failed to release the "{resource}" lock.', array('resource' => $this->key));
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isExpired()
    {
        return $this->key->isExpired();
    }

    /**
     * {@inheritdoc}
     */
    public function getRemainingLifetime()
    {
        return $this->key->getRemainingLifetime();
    }
}
