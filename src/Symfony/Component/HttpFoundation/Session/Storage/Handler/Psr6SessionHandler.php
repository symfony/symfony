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

/**
 * Session handler that supports a PSR6 cache implementation.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class Psr6SessionHandler implements \SessionHandlerInterface
{
    /**
     * @var CacheItemPoolInterface
     */
    private $cache;

    /**
     * @var int Time to live in seconds
     */
    private $ttl;

    /**
     * @var string Key prefix for shared environments.
     */
    private $prefix;

    /**
     * List of available options:
     *  * prefix: The prefix to use for the cache keys in order to avoid collision
     *  * ttl: The time to live in seconds.
     *
     * @param CacheItemPoolInterface $cache   A Cache instance
     * @param array                  $options An associative array of cache options
     */
    public function __construct(CacheItemPoolInterface $cache, array $options = array())
    {
        $this->cache = $cache;

        $this->ttl = isset($options['ttl']) ? (int) $options['ttl'] : 86400;
        $this->prefix = isset($options['prefix']) ? $options['prefix'] : 'sfPsr6sess_';
    }

    /**
     * {@inheritdoc}
     */
    public function open($savePath, $sessionName)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function read($sessionId)
    {
        $item = $this->getCacheItem($sessionId);
        if ($item->isHit()) {
            return $item->get();
        }

        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function write($sessionId, $data)
    {
        $item = $this->getCacheItem($sessionId);
        $item->set($data)
            ->expiresAfter($this->ttl);

        return $this->cache->save($item);
    }

    /**
     * {@inheritdoc}
     */
    public function destroy($sessionId)
    {
        return $this->cache->deleteItem($this->prefix.$sessionId);
    }

    /**
     * {@inheritdoc}
     */
    public function gc($lifetime)
    {
        // not required here because cache will auto expire the records anyhow.
        return true;
    }

    /**
     * @param string $sessionId
     *
     * @return \Psr\Cache\CacheItemInterface
     */
    private function getCacheItem(string $sessionId)
    {
        return $this->cache->getItem($this->prefix.$sessionId);
    }
}
