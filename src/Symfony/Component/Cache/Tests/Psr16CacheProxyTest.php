<?php

namespace Symfony\Component\Cache\Tests;

use Cache\IntegrationTests\SimpleCacheTest;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\ProxyAdapter;
use Symfony\Component\Cache\Psr16Cache;

class Psr16CacheProxyTest extends SimpleCacheTest
{
    public function createSimpleCache(int $defaultLifetime = 0): CacheInterface
    {
        return new Psr16Cache(new ProxyAdapter(new ArrayAdapter($defaultLifetime), 'my-namespace.'));
    }

    public function testProxy()
    {
        $pool = new ArrayAdapter();
        $cache = new Psr16Cache(new ProxyAdapter($pool, 'my-namespace.'));

        $this->assertNull($cache->get('some-key'));
        $this->assertTrue($cache->set('some-other-key', 'value'));

        $item = $pool->getItem('my-namespace.some-other-key', 'value');
        $this->assertTrue($item->isHit());
        $this->assertSame('value', $item->get());
    }
}
