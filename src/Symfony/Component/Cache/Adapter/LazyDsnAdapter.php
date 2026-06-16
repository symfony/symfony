<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Adapter;

use Psr\Cache\CacheItemInterface;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\Marshaller\MarshallerInterface;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Cache\ResettableInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Defers building the real adapter to first use, resolving the backend from the DSN scheme at runtime.
 *
 * The constructor argument order (namespace, default lifetime first) matches the positional injection
 * performed by {@see \Symfony\Component\Cache\DependencyInjection\CachePoolPass}, so a DSN-configured
 * pool reuses the exact same wiring as any other "cache.pool".
 *
 * @author Ousama Ben Younes <benyounes.ousama@gmail.com>
 *
 * @internal
 */
class LazyDsnAdapter implements AdapterInterface, PruneableInterface, ResettableInterface
{
    private ?AdapterInterface $pool = null;

    public function __construct(
        private readonly string $namespace,
        private readonly int $defaultLifetime,
        private readonly AdapterFactoryInterface $factory,
        #[\SensitiveParameter] private readonly string $dsn,
        private readonly ?MarshallerInterface $marshaller = null,
    ) {
    }

    public function getItem(mixed $key): CacheItem
    {
        return $this->getPool()->getItem($key);
    }

    public function getItems(array $keys = []): iterable
    {
        return $this->getPool()->getItems($keys);
    }

    public function hasItem(string $key): bool
    {
        return $this->getPool()->hasItem($key);
    }

    public function clear(string $prefix = ''): bool
    {
        return $this->getPool()->clear($prefix);
    }

    public function deleteItem(string $key): bool
    {
        return $this->getPool()->deleteItem($key);
    }

    public function deleteItems(array $keys): bool
    {
        return $this->getPool()->deleteItems($keys);
    }

    public function save(CacheItemInterface $item): bool
    {
        return $this->getPool()->save($item);
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->getPool()->saveDeferred($item);
    }

    public function commit(): bool
    {
        return $this->getPool()->commit();
    }

    public function prune(): bool
    {
        $pool = $this->getPool();

        return $pool instanceof PruneableInterface && $pool->prune();
    }

    public function reset(): void
    {
        // Only reset an adapter that has actually been built, so an unused pool stays untouched between requests.
        if ($this->pool instanceof ResetInterface) {
            $this->pool->reset();
        }
    }

    private function getPool(): AdapterInterface
    {
        return $this->pool ??= $this->factory->createAdapter($this->dsn, $this->namespace, $this->defaultLifetime, $this->marshaller);
    }
}
