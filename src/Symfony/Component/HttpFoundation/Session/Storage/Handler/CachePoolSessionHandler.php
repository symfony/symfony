<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Session\Storage\Handler;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;

/**
 * Stores PHP sessions in a {@see CacheItemPoolInterface PSR-6 cache item pool}.
 *
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 */
class CachePoolSessionHandler implements \SessionHandlerInterface
{
    private $cachePool;
    private $expiresAfter;

    /**
     * Creates a session handler that wraps a cache item pool using the given options.
     *
     * Available options are:
     *  * expires_after: The maximum lifetime of a session in seconds
     *
     * @param CacheItemPoolInterface $cachePool
     * @param array                  $options
     */
    public function __construct(CacheItemPoolInterface $cachePool, array $options = array())
    {
        if (count($diff = array_diff(array_keys($options), array('expires_after'))) > 0) {
            throw new \InvalidArgumentException(sprintf('The following options are not supported "%s".', implode('", "', $diff)));
        }

        $this->cachePool = $cachePool;
        $this->expiresAfter = isset($options['expires_after']) ? (int) $options['expires_after'] : (int) ini_get('session.gc_maxlifetime');
    }

    /**
     * {@inheritdoc}
     */
    public function open($savePath, $sessionId)
    {
        try {
            $this->cachePool->getItem($sessionId);

            return true;
        } catch (InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        // there is nothing like a close operation for PSR-6 cache pools
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function read($sessionId)
    {
        try {
            $item = $this->cachePool->getItem($sessionId);

            return $item->isHit() ? $item->get() : '';
        } catch (InvalidArgumentException $e) {
            return '';
        }
    }

    /**
     * {@inheritdoc}
     */
    public function write($sessionId, $sessionData)
    {
        try {
            $item = $this->cachePool->getItem($sessionId);
            $item->set($sessionData);
            $item->expiresAfter($this->expiresAfter);

            return $this->cachePool->save($item);
        } catch (InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function destroy($sessionId)
    {
        try {
            return $this->cachePool->deleteItem($sessionId);
        } catch (InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function gc($maxLifetime)
    {
        // cache pools must implement garbage collection on their own
        return true;
    }
}
