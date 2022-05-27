<?php

namespace Symfony\Component\DependencyInjection\MemoizeProxy;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

class Interceptor
{
    private CacheItemInterface $item;

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly KeyGeneratorInterface $keyGenerator,
        private readonly ?int $ttl = null
    )
    {
    }

    public function getPrefixInterceptor($proxy, $instance, $method, $params, &$returnEarly) {
        $this->item = $this->cache->getItem(($this->keyGenerator)(\get_class($instance), $method, $params));
        if ($this->item->isHit()) {
            $returnEarly = true;

            return $this->item->get();
        }
    }

    public function getSuffixInterceptor($proxy, $instance, $method, $params, $returnValue) {
        $this->item->expiresAfter($this->ttl);
        $this->item->set($returnValue);
        $this->cache->save($this->item);
    }
}
