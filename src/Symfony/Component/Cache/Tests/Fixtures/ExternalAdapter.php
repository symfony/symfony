<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Fixtures;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Adapter not implementing the {@see \Symfony\Component\Cache\Adapter\AdapterInterface}.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class ExternalAdapter implements CacheItemPoolInterface
{
    private $cache;

    public function __construct(int $defaultLifetime = 0)
    {
        $this->cache = new ArrayAdapter($defaultLifetime);
    }

    public function getItem($key): CacheItemInterface
    {
        return $this->cache->getItem($key);
    }

    public function getItems(array $keys = []): iterable
    {
        return $this->cache->getItems($keys);
    }

    public function hasItem($key): bool
    {
        return $this->cache->hasItem($key);
    }

    public function clear(): bool
    {
        return $this->cache->clear();
    }

    public function deleteItem($key): bool
    {
        return $this->cache->deleteItem($key);
    }

    public function deleteItems(array $keys): bool
    {
        return $this->cache->deleteItems($keys);
    }

    public function save(CacheItemInterface $item): bool
    {
        return $this->cache->save($item);
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->cache->saveDeferred($item);
    }

    public function commit(): bool
    {
        return $this->cache->commit();
    }
}
