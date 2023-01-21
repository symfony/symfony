<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Session\Flash;

use Symfony\Contracts\Service\ResetInterface;

/**
 * FlashBag decorator to allow multiple reads from a wrapped FlashBag.
 * Intended to be used for a complete rendering of a response and disposed afterwards to keep the FlashBag behaviour.
 *
 * @author Joshua Behrens <code@joshua-behrens.de>
 */
class CachedFlashBag implements FlashBagInterface, ResetInterface
{
    /**
     * @var array<string, array>
     */
    private array $cache = [];

    public function __construct(private readonly FlashBagInterface $decorated)
    {
    }

    public function getName(): string
    {
        return $this->getDecorated()->getName();
    }

    public function initialize(array &$flashes)
    {
        return $this->getDecorated()->initialize($flashes);
    }

    public function add(string $type, mixed $message)
    {
        return $this->getDecorated()->add($type, $message);
    }

    public function peek(string $type, array $default = []): array
    {
        $uncached = $this->getDecorated()->peek($type, $default);

        return $this->mergeWithCached([$type => $uncached], $type);
    }

    public function peekAll(): array
    {
        return $this->mergeWithAllCached($this->getDecorated()->peekAll());
    }

    public function get(string $type, array $default = []): array
    {
        $uncached = $this->getDecorated()->get($type, $default);
        $result = $this->mergeWithCached([$type => $uncached], $type);
        $this->addToCache([$type => $uncached]);

        return $result;
    }

    public function all(): array
    {
        return $this->mergeWithAllCached($this->getDecorated()->all());
    }

    public function set(string $type, string|array $messages)
    {
        $this->removeFromCache([$type]);

        return $this->getDecorated()->set($type, $messages);
    }

    public function setAll(array $messages)
    {
        $this->removeFromCache(\array_keys($messages));

        return $this->getDecorated()->setAll($messages);
    }

    public function has(string $type): bool
    {
        return $this->getDecorated()->has($type) || isset($this->cache[$type]);
    }

    public function keys(): array
    {
        return $this->mergeWithCachedKeys($this->getDecorated()->keys());
    }

    public function getStorageKey(): string
    {
        return $this->getDecorated()->getStorageKey();
    }

    public function clear(): mixed
    {
        return $this->getDecorated()->clear();
    }

    public function reset(): void
    {
        $this->cache = [];
    }

    public function getDecorated(): FlashBagInterface
    {
        return $this->decorated;
    }

    /**
     * @param array<string, mixed> $array
     * @return array<string, mixed>
     */
    private function mergeWithCached(array $array, string $type): array
    {
        return \array_merge_recursive($array, $this->cache[$type] ?? []);
    }

    /**
     * @param array<string, array> $array
     * @return array<string, array>
     */
    private function mergeWithAllCached(array $array): array
    {
        $result = $array;

        foreach ($this->cache as $type => $messages) {
            $result[$type] = $this->mergeWithCached($messages, $type);
        }

        return $result;
    }

    /**
     * @param string[] $keys
     * @return string[]
     */
    private function mergeWithCachedKeys(array $keys): array
    {
        return \array_unique(\array_merge($keys, \array_keys($this->cache)));
    }

    /**
     * @param string[] $cacheKeys
     */
    private function removeFromCache(array $cacheKeys): void
    {
        foreach ($cacheKeys as $key) {
            unset($this->cache[$key]);
        }
    }

    /**
     * @param array<string, array> $messages
     */
    private function addToCache(array $messages): void
    {
        foreach ($messages as $key => $value) {
            $this->cache[$key] = \array_merge_recursive($value, $this->cache[$key] ?? []);
        }
    }
}
