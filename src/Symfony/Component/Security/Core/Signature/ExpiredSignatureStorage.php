<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Signature;

use Psr\Cache\CacheItemPoolInterface;

/**
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
final class ExpiredSignatureStorage
{
    private $cache;
    private $lifetime;

    public function __construct(CacheItemPoolInterface $cache, int $lifetime)
    {
        $this->cache = $cache;
        $this->lifetime = $lifetime;
    }

    public function countUsages(string $hash): int
    {
        $key = rawurlencode($hash);
        if (!$this->cache->hasItem($key)) {
            return 0;
        }

        return $this->cache->getItem($key)->get();
    }

    public function incrementUsages(string $hash): void
    {
        $item = $this->cache->getItem(rawurlencode($hash));

        if (!$item->isHit()) {
            $item->expiresAfter($this->lifetime);
        }

        $item->set($this->countUsages($hash) + 1);
        $this->cache->save($item);
    }
}
