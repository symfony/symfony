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
use Symfony\Component\Cache\Exception\BadMethodCallException;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Cache\ResettableInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\NamespacedPoolInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * An adapter that collects data about all cache calls.
 *
 * @author Aaron Scherer <aequasi@gmail.com>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
class TraceableAdapter implements AdapterInterface, CacheInterface, NamespacedPoolInterface, PruneableInterface, ResettableInterface
{
    private string $namespace = '';
    private array $calls = [];

    public function __construct(
        protected AdapterInterface $pool,
        protected readonly ?\Closure $disabled = null,
    ) {
    }

    /**
     * @throws BadMethodCallException When the item pool is not a CacheInterface
     */
    public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null): mixed
    {
        if (!$this->pool instanceof CacheInterface) {
            throw new BadMethodCallException(\sprintf('Cannot call "%s::get()": this class doesn\'t implement "%s".', get_debug_type($this->pool), CacheInterface::class));
        }
        if ($this->disabled?->__invoke()) {
            return $this->pool->get($key, $callback, $beta, $metadata);
        }

        $isHit = true;
        $callback = function (CacheItem $item, bool &$save) use ($callback, &$isHit) {
            $isHit = $item->isHit();

            return $callback($item, $save);
        };

        $event = $this->start(__FUNCTION__);
        try {
            $value = $this->pool->get($key, $callback, $beta, $metadata);
            $event->result[$key] = get_debug_type($value);
        } finally {
            $event->end = microtime(true);
        }
        if ($isHit) {
            ++$event->hits;
        } else {
            ++$event->misses;
        }

        return $value;
    }

    public function getItem(mixed $key): CacheItem
    {
        if ($this->disabled?->__invoke()) {
            return $this->pool->getItem($key);
        }
        $event = $this->start(__FUNCTION__);
        try {
            $item = $this->pool->getItem($key);
        } finally {
            $event->end = microtime(true);
        }
        if ($event->result[$key] = $item->isHit()) {
            ++$event->hits;
        } else {
            ++$event->misses;
        }

        return $item;
    }

    public function hasItem(mixed $key): bool
    {
        if ($this->disabled?->__invoke()) {
            return $this->pool->hasItem($key);
        }
        $event = $this->start(__FUNCTION__);
        try {
            return $event->result[$key] = $this->pool->hasItem($key);
        } finally {
            $event->end = microtime(true);
        }
    }

    public function deleteItem(mixed $key): bool
    {
        if ($this->disabled?->__invoke()) {
            return $this->pool->deleteItem($key);
        }
        $event = $this->start(__FUNCTION__);
        try {
            return $event->result[$key] = $this->pool->deleteItem($key);
        } finally {
            $event->end = microtime(true);
        }
    }

    public function save(CacheItemInterface $item): bool
    {
        if ($this->disabled?->__invoke()) {
            return $this->pool->save($item);
        }
        $event = $this->start(__FUNCTION__);
        try {
            return $event->result[$item->getKey()] = $this->pool->save($item);
        } finally {
            $event->end = microtime(true);
        }
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        if ($this->disabled?->__invoke()) {
            return $this->pool->saveDeferred($item);
        }
        $event = $this->start(__FUNCTION__);
        try {
            return $event->result[$item->getKey()] = $this->pool->saveDeferred($item);
        } finally {
            $event->end = microtime(true);
        }
    }

    public function getItems(array $keys = []): iterable
    {
        if ($this->disabled?->__invoke()) {
            return $this->pool->getItems($keys);
        }
        $event = $this->start(__FUNCTION__);
        try {
            $result = $this->pool->getItems($keys);
        } finally {
            $event->end = microtime(true);
        }
        $f = function () use ($result, $event) {
            $event->result = [];
            foreach ($result as $key => $item) {
                if ($event->result[$key] = $item->isHit()) {
                    ++$event->hits;
                } else {
                    ++$event->misses;
                }
                yield $key => $item;
            }
        };

        return $f();
    }

    public function clear(string $prefix = ''): bool
    {
        if ($this->disabled?->__invoke()) {
            return $this->pool->clear($prefix);
        }
        $event = $this->start(__FUNCTION__);
        try {
            if ($this->pool instanceof AdapterInterface) {
                return $event->result = $this->pool->clear($prefix);
            }

            return $event->result = $this->pool->clear();
        } finally {
            $event->end = microtime(true);
        }
    }

    public function deleteItems(array $keys): bool
    {
        if ($this->disabled?->__invoke()) {
            return $this->pool->deleteItems($keys);
        }
        $event = $this->start(__FUNCTION__);
        $event->result['keys'] = $keys;
        try {
            return $event->result['result'] = $this->pool->deleteItems($keys);
        } finally {
            $event->end = microtime(true);
        }
    }

    public function commit(): bool
    {
        if ($this->disabled?->__invoke()) {
            return $this->pool->commit();
        }
        $event = $this->start(__FUNCTION__);
        try {
            return $event->result = $this->pool->commit();
        } finally {
            $event->end = microtime(true);
        }
    }

    public function prune(): bool
    {
        if (!$this->pool instanceof PruneableInterface) {
            return false;
        }
        if ($this->disabled?->__invoke()) {
            return $this->pool->prune();
        }
        $event = $this->start(__FUNCTION__);
        try {
            return $event->result = $this->pool->prune();
        } finally {
            $event->end = microtime(true);
        }
    }

    public function reset(): void
    {
        if ($this->pool instanceof ResetInterface) {
            $this->pool->reset();
        }

        $this->clearCalls();
    }

    public function delete(string $key): bool
    {
        if ($this->disabled?->__invoke()) {
            return $this->pool->deleteItem($key);
        }
        $event = $this->start(__FUNCTION__);
        try {
            return $event->result[$key] = $this->pool->deleteItem($key);
        } finally {
            $event->end = microtime(true);
        }
    }

    public function getCalls(): array
    {
        return $this->calls;
    }

    public function clearCalls(): void
    {
        $this->calls = [];
    }

    public function getPool(): AdapterInterface
    {
        return $this->pool;
    }

    /**
     * @throws BadMethodCallException When the item pool is not a NamespacedPoolInterface
     */
    public function withSubNamespace(string $namespace): static
    {
        if (!$this->pool instanceof NamespacedPoolInterface) {
            throw new BadMethodCallException(\sprintf('Cannot call "%s::withSubNamespace()": this class doesn\'t implement "%s".', get_debug_type($this->pool), NamespacedPoolInterface::class));
        }

        $calls = &$this->calls; // ensures clones share the same array
        $clone = clone $this;
        $clone->namespace .= CacheItem::validateKey($namespace).':';
        $clone->pool = $this->pool->withSubNamespace($namespace);

        return $clone;
    }

    protected function start(string $name): TraceableAdapterEvent
    {
        $this->calls[] = $event = new TraceableAdapterEvent();
        $event->name = $name;
        $event->start = microtime(true);
        $event->namespace = $this->namespace;

        return $event;
    }
}

/**
 * @internal
 */
class TraceableAdapterEvent
{
    public string $name;
    public float $start;
    public float $end;
    public array|bool $result;
    public int $hits = 0;
    public int $misses = 0;
    public string $namespace;
}
