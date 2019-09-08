<?php

namespace Symfony\Component\Cache\Tests\Adapter;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\ProxyAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Tests\Fixtures\ExternalAdapter;

class TagAwareAndProxyAdapterIntegrationTest extends TestCase
{
    /**
     * @dataProvider dataProvider
     */
    public function testIntegrationUsingProxiedAdapter(CacheItemPoolInterface $proxiedAdapter)
    {
        $cache = new TagAwareAdapter(new ProxyAdapter($proxiedAdapter));

        $item = $cache->getItem('foo');
        $item->tag(['tag1', 'tag2']);
        $item->set('bar');
        $cache->save($item);

        $this->assertSame('bar', $cache->getItem('foo')->get());
    }

    public function dataProvider(): array
    {
        return [
            [new ArrayAdapter()],
            // also testing with a non-AdapterInterface implementation
            // because the ProxyAdapter behaves slightly different for those
            [new ExternalAdapter()],
        ];
    }
}
