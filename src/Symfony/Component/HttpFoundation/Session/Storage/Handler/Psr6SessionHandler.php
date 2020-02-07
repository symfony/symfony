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
 * @author Ahmed TAILOULOUTE <ahmed.tailouloute@gmail.com>
 */
class Psr6SessionHandler extends AbstractSessionHandler
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
     * @var string Key prefix for shared environments
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
    public function __construct(CacheItemPoolInterface $cache, array $options = [])
    {
        $this->cache = $cache;

        if ($diff = array_diff(array_keys($options), ['prefix', 'ttl'])) {
            throw new \InvalidArgumentException(sprintf('The following options are not supported by %s: "%s".', static::class, implode('", "', $diff)));
        }

        $this->ttl = $options['ttl'] ?? null;
        $this->prefix = $options['prefix'] ?? 'sf_s';
    }

    /**
     * {@inheritdoc}
     */
    protected function doRead(string $sessionId)
    {
        $item = $this->cache->getItem($this->prefix.$sessionId);

        return $item->isHit() ? $item->get() : '';
    }

    /**
     * {@inheritdoc}
     */
    protected function doWrite(string $sessionId, string $data)
    {
        $item = $this->cache->getItem($this->prefix.$sessionId);
        $item->set($data)
            ->expiresAfter($this->ttl ?? ini_get('session.gc_maxlifetime'));

        return $this->cache->save($item);
    }

    /**
     * {@inheritdoc}
     */
    protected function doDestroy(string $sessionId)
    {
        return $this->cache->deleteItem($this->prefix.$sessionId);
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
    public function gc($lifetime)
    {
        // not required here because cache will auto expire the records anyhow.
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function updateTimestamp($sessionId, $data)
    {
        $cacheItem = $this->cache->getItem($this->prefix.$sessionId);
        $cacheItem->expiresAfter((int) ($this->ttl ?? ini_get('session.gc_maxlifetime')));

        return $this->cache->save($cacheItem);
    }
}
