<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\DependencyInjection\CachePoolPass;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class CachePoolPassTest extends TestCase
{
    private $cachePoolPass;

    protected function setUp(): void
    {
        $this->cachePoolPass = new CachePoolPass();
    }

    public function testNamespaceArgumentIsReplaced()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.container_class', 'app');
        $container->setParameter('kernel.project_dir', 'foo');
        $adapter = new Definition();
        $adapter->setAbstract(true);
        $adapter->addTag('cache.pool');
        $container->setDefinition('app.cache_adapter', $adapter);
        $container->setAlias('app.cache_adapter_alias', 'app.cache_adapter');
        $cachePool = new ChildDefinition('app.cache_adapter_alias');
        $cachePool->addArgument(null);
        $cachePool->addTag('cache.pool');
        $container->setDefinition('app.cache_pool', $cachePool);

        $this->cachePoolPass->process($container);

        $this->assertSame('z3X945Jbf5', $cachePool->getArgument(0));
    }

    public function testNamespaceArgumentIsSeededWithAdapterClassName()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.container_class', 'app');
        $container->setParameter('kernel.project_dir', 'foo');
        $adapter = new Definition();
        $adapter->setAbstract(true);
        $adapter->addTag('cache.pool');
        $adapter->setClass(RedisAdapter::class);
        $container->setDefinition('app.cache_adapter', $adapter);
        $container->setAlias('app.cache_adapter_alias', 'app.cache_adapter');
        $cachePool = new ChildDefinition('app.cache_adapter_alias');
        $cachePool->addArgument(null);
        $cachePool->addTag('cache.pool');
        $container->setDefinition('app.cache_pool', $cachePool);

        $this->cachePoolPass->process($container);

        $this->assertSame('xmOJ8gqF-Y', $cachePool->getArgument(0));
    }

    public function testNamespaceArgumentIsSeededWithAdapterClassNameWithoutAffectingOtherCachePools()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.container_class', 'app');
        $container->setParameter('kernel.project_dir', 'foo');
        $adapter = new Definition();
        $adapter->setAbstract(true);
        $adapter->addTag('cache.pool');
        $adapter->setClass(RedisAdapter::class);
        $container->setDefinition('app.cache_adapter', $adapter);
        $container->setAlias('app.cache_adapter_alias', 'app.cache_adapter');

        $otherCachePool = new ChildDefinition('app.cache_adapter_alias');
        $otherCachePool->addArgument(null);
        $otherCachePool->addTag('cache.pool');
        $container->setDefinition('app.other_cache_pool', $otherCachePool);

        $cachePool = new ChildDefinition('app.cache_adapter_alias');
        $cachePool->addArgument(null);
        $cachePool->addTag('cache.pool');
        $container->setDefinition('app.cache_pool', $cachePool);

        $this->cachePoolPass->process($container);

        $this->assertSame('xmOJ8gqF-Y', $cachePool->getArgument(0));
    }

    public function testNamespaceArgumentIsNotReplacedIfArrayAdapterIsUsed()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.container_class', 'app');
        $container->setParameter('kernel.project_dir', 'foo');

        $container->register('cache.adapter.array', ArrayAdapter::class)->addArgument(0);

        $cachePool = new ChildDefinition('cache.adapter.array');
        $cachePool->addTag('cache.pool');
        $container->setDefinition('app.cache_pool', $cachePool);

        $this->cachePoolPass->process($container);

        $this->assertCount(0, $container->getDefinition('app.cache_pool')->getArguments());
    }

    public function testArgsAreReplaced()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.container_class', 'app');
        $container->setParameter('cache.prefix.seed', 'foo');
        $cachePool = new Definition();
        $cachePool->addTag('cache.pool', [
            'provider' => 'foobar',
            'default_lifetime' => 3,
        ]);
        $cachePool->addArgument(null);
        $cachePool->addArgument(null);
        $cachePool->addArgument(null);
        $container->setDefinition('app.cache_pool', $cachePool);

        $this->cachePoolPass->process($container);

        $this->assertInstanceOf(Reference::class, $cachePool->getArgument(0));
        $this->assertSame('foobar', (string) $cachePool->getArgument(0));
        $this->assertSame('tQNhcV-8xa', $cachePool->getArgument(1));
        $this->assertSame(3, $cachePool->getArgument(2));
    }

    public function testWithNameAttribute()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.container_class', 'app');
        $container->setParameter('cache.prefix.seed', 'foo');
        $cachePool = new Definition();
        $cachePool->addTag('cache.pool', [
            'name' => 'foobar',
            'provider' => 'foobar',
        ]);
        $cachePool->addArgument(null);
        $cachePool->addArgument(null);
        $cachePool->addArgument(null);
        $container->setDefinition('app.cache_pool', $cachePool);

        $this->cachePoolPass->process($container);

        $this->assertSame('+naTpPa4Sm', $cachePool->getArgument(1));
    }

    public function testThrowsExceptionWhenCachePoolTagHasUnknownAttributes()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Invalid "cache.pool" tag for service "app.cache_pool": accepted attributes are');
        $container = new ContainerBuilder();
        $container->setParameter('kernel.container_class', 'app');
        $container->setParameter('kernel.project_dir', 'foo');
        $adapter = new Definition();
        $adapter->setAbstract(true);
        $adapter->addTag('cache.pool');
        $container->setDefinition('app.cache_adapter', $adapter);
        $cachePool = new ChildDefinition('app.cache_adapter');
        $cachePool->addTag('cache.pool', ['foobar' => 123]);
        $container->setDefinition('app.cache_pool', $cachePool);

        $this->cachePoolPass->process($container);
    }
}
