<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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

        $cache->invalidateTags(['tag2']);

        $this->assertFalse($cache->getItem('foo')->isHit());
    }

    public function testIntegrationUsingProxiedAdapterForTagsPool()
    {
        $arrayAdapter = new ArrayAdapter();
        $cache = new TagAwareAdapter($arrayAdapter, new ProxyAdapter($arrayAdapter));

        $item = $cache->getItem('foo');
        $item->expiresAfter(600);
        $item->tag(['baz']);
        $item->set('bar');
        $cache->save($item);

        $this->assertSame('bar', $cache->getItem('foo')->get());
        $this->assertTrue($cache->getItem('foo')->isHit());

        $cache->invalidateTags(['baz']);

        $this->assertFalse($cache->getItem('foo')->isHit());
    }

    public static function dataProvider(): array
    {
        return [
            [new ArrayAdapter()],
            // also testing with a non-AdapterInterface implementation
            // because the ProxyAdapter behaves slightly different for those
            [new ExternalAdapter()],
        ];
    }
}
