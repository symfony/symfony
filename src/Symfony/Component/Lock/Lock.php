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

    /**
     * @param Key            $key
     * @param StoreInterface $store
     * @param float|null     $ttl
     */
    public function __construct(Key $key, StoreInterface $store, $ttl = null)
    {
        $this->store = $store;
        $this->key = $key;
        $this->ttl = $ttl;

        $this->logger = new NullLogger();
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

            $this->logger->info('Lock successfully acquired for "{resource}".', array('resource' => $this->key));

            if ($this->ttl) {
                $this->refresh();
            }

            return true;
        } catch (LockConflictedException $e) {
            $this->logger->warning('Failed to lock the "{resource}". Someone else already acquired the lock.', array('resource' => $this->key));

            if ($blocking) {
                throw $e;
            }

            return false;
        } catch (\Exception $e) {
            $this->logger->warning('Failed to lock the "{resource}".', array('resource' => $this->key, 'exception' => $e));
            throw new LockAcquiringException('', 0, $e);
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
            $this->store->putOffExpiration($this->key, $this->ttl);
            $this->logger->info('Expiration defined for "{resource}" lock for "{ttl}" seconds.', array('resource' => $this->key, 'ttl' => $this->ttl));
        } catch (LockConflictedException $e) {
            $this->logger->warning('Failed to define an expiration for the "{resource}" lock, someone else acquired the lock.', array('resource' => $this->key));
            throw $e;
        } catch (\Exception $e) {
            $this->logger->warning('Failed to define an expiration for the "{resource}" lock.', array('resource' => $this->key, 'exception' => $e));
            throw new LockAcquiringException('', 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isAcquired()
    {
        return $this->store->exists($this->key);
    }

    /**
     * {@inheritdoc}
     */
    public function release()
    {
        $this->store->delete($this->key);

        if ($this->store->exists($this->key)) {
            $this->logger->warning('Failed to release the "{resource}" lock.', array('resource' => $this->key));
            throw new LockReleasingException();
        }
    }
}
