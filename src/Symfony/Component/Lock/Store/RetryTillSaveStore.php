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
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\StoreInterface;

/**
 * RetryTillSaveStore is a StoreInterface implementation which decorate a non blocking StoreInterface to provide a
 * blocking storage.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class RetryTillSaveStore implements StoreInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private $decorated;
    private $retrySleep;
    private $retryCount;

    /**
     * @param StoreInterface $decorated  The decorated StoreInterface
     * @param int            $retrySleep Duration in ms between 2 retry
     * @param int            $retryCount Maximum amount of retry
     */
    public function __construct(StoreInterface $decorated, $retrySleep = 100, $retryCount = PHP_INT_MAX)
    {
        $this->decorated = $decorated;
        $this->retrySleep = $retrySleep;
        $this->retryCount = $retryCount;

        $this->logger = new NullLogger();
    }

    /**
     * {@inheritdoc}
     */
    public function save(Key $key)
    {
        $this->decorated->save($key);
    }

    /**
     * {@inheritdoc}
     */
    public function waitAndSave(Key $key)
    {
        $retry = 0;
        $sleepRandomness = (int) ($this->retrySleep / 10);
        do {
            try {
                $this->decorated->save($key);

                return;
            } catch (LockConflictedException $e) {
                usleep(($this->retrySleep + random_int(-$sleepRandomness, $sleepRandomness)) * 1000);
            }
        } while (++$retry < $this->retryCount);

        $this->logger->warning('Failed to store the "{resource}" lock. Abort after {retry} retry.', array('resource' => $key, 'retry' => $retry));

        throw new LockConflictedException();
    }

    /**
     * {@inheritdoc}
     */
    public function putOffExpiration(Key $key, $ttl)
    {
        $this->decorated->putOffExpiration($key, $ttl);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(Key $key)
    {
        $this->decorated->delete($key);
    }

    /**
     * {@inheritdoc}
     */
    public function exists(Key $key)
    {
        return $this->decorated->exists($key);
    }
}
