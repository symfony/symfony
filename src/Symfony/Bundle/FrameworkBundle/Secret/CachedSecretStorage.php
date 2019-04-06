<?php

namespace Symfony\Bundle\FrameworkBundle\Secret;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

class CachedSecretStorage implements SecretStorageInterface
{
    /**
     * @var SecretStorageInterface
     */
    private $decoratedStorage;
    /**
     * @var CacheItemPoolInterface
     */
    private $cache;

    public function __construct(SecretStorageInterface $decoratedStorage, CacheItemPoolInterface $cache)
    {
        $this->decoratedStorage = $decoratedStorage;
        $this->cache = $cache;
    }

    public function getSecret(string $key): string
    {
        $cacheItem = $this->cache->getItem('secrets.php');

        if ($cacheItem->isHit()) {
            $secrets = $cacheItem->get();
            if (isset($secrets[$key])) {
                return $secrets[$key];
            }
        }

        $this->regenerateCache($cacheItem);

        return $this->decoratedStorage->getSecret($key);
    }

    public function putSecret(string $key, string $secret): void
    {
        $this->decoratedStorage->putSecret($key, $secret);
        $this->regenerateCache();
    }

    public function deleteSecret(string $key): void
    {
        $this->decoratedStorage->deleteSecret($key);
        $this->regenerateCache();
    }

    public function listSecrets(): iterable
    {
        $cacheItem = $this->cache->getItem('secrets.php');

        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        return $this->regenerateCache($cacheItem);
    }

    private function regenerateCache(?CacheItemInterface $cacheItem = null): array
    {
        $cacheItem = $cacheItem ?? $this->cache->getItem('secrets.php');

        $secrets = [];
        foreach ($this->decoratedStorage->listSecrets() as $key => $secret) {
            $secrets[$key] = $secret;
        }

        $cacheItem->set($secrets);
        $this->cache->save($cacheItem);

        return $secrets;
    }
}
