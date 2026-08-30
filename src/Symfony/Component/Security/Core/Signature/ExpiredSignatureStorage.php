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
    public function __construct(
        private CacheItemPoolInterface $cache,
        private int $lifetime,
    ) {
    }

    public function countUsages(string $hash): int
    {
        $key = rawurlencode($hash);
        if (!$this->cache->hasItem($key)) {
            return 0;
        }

        return $this->cache->getItem($key)->get();
    }

    /**
     * @return int The number of usages once this one is accounted for
     */
    public function incrementUsages(string $hash): int
    {
        $item = $this->cache->getItem(rawurlencode($hash));
        $usages = ($item->get() ?? 0) + 1;

        // a fetched item does not carry its expiration, it has to be set on every save
        $item->set($usages);
        $item->expiresAfter($this->lifetime);
        $this->cache->save($item);

        return $usages;
    }
}
