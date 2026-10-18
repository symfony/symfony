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
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\ChainAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\FilesystemTagAwareAdapter;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Cache\Adapter\ParameterNormalizer;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\DependencyInjection\CachePoolPass;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\CacheClearer\Psr6CacheClearer;

class CachePoolPassTest extends TestCase
{
    private CachePoolPass $cachePoolPass;

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

    /**
     * @dataProvider providerAdaptersNotNamespace
     */
    public function testNamespaceArgumentIsNotReplacedIfAdapterWithoutNamespace(string $adapterClass)
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.container_class', 'app');
        $container->setParameter('kernel.project_dir', 'foo');

        $container->register('cache.adapter', $adapterClass)->addArgument(0);

        $cachePool = new ChildDefinition('cache.adapter');
        $cachePool->addTag('cache.pool');
        $container->setDefinition('app.cache_pool', $cachePool);

        $this->cachePoolPass->process($container);

        $this->assertCount(0, $container->getDefinition('app.cache_pool')->getArguments());
    }

    public static function providerAdaptersNotNamespace(): iterable
    {
        yield [ArrayAdapter::class];
        yield [NullAdapter::class];
        yield [TagAwareAdapter::class];
    }

    public function testUnusedProviderIsIgnored()
    {
        foreach ([ArrayAdapter::class, NullAdapter::class] as $adapterClass) {
            foreach ([false, true] as $chained) {
                $container = new ContainerBuilder();
                $container->setParameter('cache.prefix.seed', 'test');
                $container->register('cache.adapter', $adapterClass)
                    ->setAbstract(true)
                    ->setArguments(ArrayAdapter::class === $adapterClass ? [0] : [])
                    ->addTag('cache.pool');

                if ($chained) {
                    $container->register('cache.adapter.chain', ChainAdapter::class)->setAbstract(true)->setArguments([[], 0]);
                    $pool = new ChildDefinition('cache.adapter.chain');
                    $pool->addArgument(['unused.provider' => 'cache.adapter']);
                } else {
                    $pool = new ChildDefinition('cache.adapter');
                }

                $pool->setPublic(true)->addTag('cache.pool', ['provider' => 'unused.provider', 'default_lifetime' => 30]);
                $container->setDefinition('cache.pool', $pool);
                $container->addCompilerPass($this->cachePoolPass);
                $container->compile();

                $cache = $container->get('cache.pool');
                $this->assertInstanceOf($chained ? ChainAdapter::class : $adapterClass, $cache);
                $this->assertFalse($cache->getItem('missing')->isHit());
            }
        }
    }

    public function testEnvVarsOfIgnoredAttributesAreNotReportedAsUnused()
    {
        $container = new ContainerBuilder();
        $container->setParameter('cache.prefix.seed', 'test');
        $container->register('cache.adapter.array', ArrayAdapter::class)->setAbstract(true)->addArgument(0)->addTag('cache.pool');
        $container->register('cache.adapter.null', NullAdapter::class)->setAbstract(true)->addTag('cache.pool');

        $bag = $container->getParameterBag();
        $container->setDefinition('array.pool', new ChildDefinition('cache.adapter.array'))
            ->setPublic(true)
            ->addTag('cache.pool', ['provider' => $bag->get('env(ARRAY_DSN)'), 'default_lifetime' => 30]);
        $container->setDefinition('null.pool', new ChildDefinition('cache.adapter.null'))
            ->setPublic(true)
            ->addTag('cache.pool', ['provider' => $bag->get('env(NULL_DSN)'), 'default_lifetime' => $bag->get('env(NULL_LIFETIME)')]);

        $container->addCompilerPass($this->cachePoolPass);
        $container->compile();

        $this->assertStringNotContainsString('getEnv', (new PhpDumper($container))->dump());
    }

    public function testProviderIsIgnoredByAdaptersThatTakeANamespaceFirst()
    {
        $dir = sys_get_temp_dir().'/symfony-cache';
        $container = new ContainerBuilder();
        $container->setParameter('cache.prefix.seed', 'test');
        $container->register('app.provider', \stdClass::class);
        $container->register('cache.adapter.apcu', ApcuAdapter::class)->setAbstract(true)->setArguments(['', 0, 'version'])->addTag('cache.pool');
        $container->register('cache.adapter.filesystem', FilesystemAdapter::class)->setAbstract(true)->setArguments(['', 0, $dir])->addTag('cache.pool');
        $container->register('cache.adapter.filesystem_tag_aware', FilesystemTagAwareAdapter::class)->setAbstract(true)->setArguments(['', 0, $dir])->addTag('cache.pool');
        $container->register('cache.adapter.php_files', PhpFilesAdapter::class)->setAbstract(true)->setArguments(['', 0, $dir])->addTag('cache.pool');
        $container->register('cache.adapter.system', AdapterInterface::class)->setAbstract(true)->setFactory([AbstractAdapter::class, 'createSystemCache'])->setArguments(['', 0, 'version', $dir])->addTag('cache.pool');

        $pools = ['apcu.pool' => 'cache.adapter.apcu', 'filesystem.pool' => 'cache.adapter.filesystem', 'filesystem_tag_aware.pool' => 'cache.adapter.filesystem_tag_aware', 'php_files.pool' => 'cache.adapter.php_files', 'system.pool' => 'cache.adapter.system'];
        foreach ($pools as $id => $adapter) {
            $container->setDefinition($id, new ChildDefinition($adapter))->setPublic(true)->addTag('cache.pool', ['provider' => 'app.provider', 'default_lifetime' => 30]);
        }
        $container->register('chain.pool', ChainAdapter::class)->setPublic(true)->setArguments([['app.provider' => 'cache.adapter.filesystem'], 0])->addTag('cache.pool', ['default_lifetime' => 30]);

        $container->addCompilerPass($this->cachePoolPass);
        $container->compile();

        $this->assertInstanceOf(FilesystemAdapter::class, $container->get('filesystem.pool'));
        $this->assertInstanceOf(FilesystemTagAwareAdapter::class, $container->get('filesystem_tag_aware.pool'));
        $this->assertInstanceOf(PhpFilesAdapter::class, $container->get('php_files.pool'));
        $this->assertInstanceOf(AdapterInterface::class, $container->get('system.pool'));
        $this->assertInstanceOf(ChainAdapter::class, $container->get('chain.pool'));
        $this->assertSame(30, $container->getDefinition('apcu.pool')->getArgument(1));
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
        $this->assertSame('6Ridbw4aMn', $cachePool->getArgument(1));
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

        $this->assertSame('PeXBWSl6ca', $cachePool->getArgument(1));
    }

    public function testThrowsExceptionWhenCachePoolTagHasUnknownAttributes()
    {
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

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid "cache.pool" tag for service "app.cache_pool": accepted attributes are');

        $this->cachePoolPass->process($container);
    }

    public function testChainAdapterPool()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.container_class', 'app');
        $container->setParameter('kernel.project_dir', 'foo');

        $container->register('cache.adapter.array', ArrayAdapter::class)
            ->addTag('cache.pool');
        $container->register('cache.adapter.apcu', ApcuAdapter::class)
            ->setArguments([null, 0, null])
            ->addTag('cache.pool');
        $container->register('cache.adapter.chain', ChainAdapter::class);
        $container->setDefinition('cache.chain', new ChildDefinition('cache.adapter.chain'))
            ->addArgument(['cache.adapter.array', 'cache.adapter.apcu'])
            ->addTag('cache.pool');
        $container->setDefinition('cache.app', new ChildDefinition('cache.chain'))
            ->addTag('cache.pool');
        $container->setDefinition('doctrine.result_cache_pool', new ChildDefinition('cache.app'))
            ->addTag('cache.pool');

        $this->cachePoolPass->process($container);

        $appCachePool = $container->getDefinition('cache.app');
        $this->assertInstanceOf(ChildDefinition::class, $appCachePool);
        $this->assertSame('cache.chain', $appCachePool->getParent());

        $chainCachePool = $container->getDefinition('cache.chain');
        $this->assertInstanceOf(ChildDefinition::class, $chainCachePool);
        $this->assertCount(2, $chainCachePool->getArgument(0));
        $this->assertInstanceOf(ChildDefinition::class, $chainCachePool->getArgument(0)[0]);
        $this->assertSame('cache.adapter.array', $chainCachePool->getArgument(0)[0]->getParent());
        $this->assertInstanceOf(ChildDefinition::class, $chainCachePool->getArgument(0)[1]);
        $this->assertSame('cache.adapter.apcu', $chainCachePool->getArgument(0)[1]->getParent());

        $doctrineCachePool = $container->getDefinition('doctrine.result_cache_pool');
        $this->assertInstanceOf(ChildDefinition::class, $doctrineCachePool);
        $this->assertSame('cache.app', $doctrineCachePool->getParent());
    }

    public function testChainChildDefinitionGetsOwnNamespace()
    {
        $container = new ContainerBuilder();
        $container->setParameter('cache.prefix.seed', 'test');

        $container->register('cache.adapter.filesystem', FilesystemAdapter::class)
            ->setAbstract(true)
            ->setArguments([null, 0, null]);
        $container->register('cache.adapter.chain', ChainAdapter::class)
            ->setAbstract(true);

        $container->setDefinition('cache.parent.chain', new ChildDefinition('cache.adapter.chain'))
            ->addArgument(['cache.adapter.filesystem'])
            ->addTag('cache.pool');

        $container->setDefinition('foobar.chained.cache', new ChildDefinition('cache.parent.chain'))
            ->addTag('cache.pool');

        $this->cachePoolPass->process($container);

        $parentChain = $container->getDefinition('cache.parent.chain');
        $childChain = $container->getDefinition('foobar.chained.cache');

        $parentNamespace = $parentChain->getArgument(0)[0]->getArgument(0);
        $childNamespace = $childChain->getArgument(0)[0]->getArgument(0);

        $this->assertNotSame($parentNamespace, $childNamespace);
    }

    public function testChainAdapterNormalizesDefaultLifetimeDurationString()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.container_class', 'app');
        $container->setParameter('kernel.project_dir', 'foo');

        $container->register('cache.adapter.array', ArrayAdapter::class)
            ->addTag('cache.pool');
        $container->register('cache.adapter.apcu', ApcuAdapter::class)
            ->setArguments([null, 0, null])
            ->addTag('cache.pool');
        $container->register('cache.adapter.chain', ChainAdapter::class);
        $container->setDefinition('cache.chain', new ChildDefinition('cache.adapter.chain'))
            ->addArgument(['cache.adapter.array', 'cache.adapter.apcu'])
            ->addTag('cache.pool', ['default_lifetime' => '1 week']);

        $this->cachePoolPass->process($container);

        $chainPool = $container->getDefinition('cache.chain');
        foreach ($chainPool->getArgument(0) as $innerAdapter) {
            $this->assertInstanceOf(Definition::class, $innerAdapter);
            $arguments = $innerAdapter->getArguments();
            $lifetimeArg = $arguments[array_key_last($arguments)];
            $this->assertSame([ParameterNormalizer::class, 'normalizeDuration'], $lifetimeArg->getFactory());
            $this->assertSame(['1 week'], $lifetimeArg->getArguments());
        }
    }

    public function testGlobalClearerAlias()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.container_class', 'app');
        $container->setParameter('kernel.project_dir', 'foo');

        $container->register('cache.default_clearer', Psr6CacheClearer::class);

        $container->setDefinition('cache.system_clearer', new ChildDefinition('cache.default_clearer'));

        $container->setDefinition('cache.foo_bar_clearer', new ChildDefinition('cache.default_clearer'));
        $container->setAlias('cache.global_clearer', 'cache.foo_bar_clearer');

        $container->register('cache.adapter.array', ArrayAdapter::class)
            ->setAbstract(true)
            ->addTag('cache.pool');

        $cachePool = new ChildDefinition('cache.adapter.array');
        $cachePool->addTag('cache.pool', ['clearer' => 'cache.system_clearer']);
        $container->setDefinition('app.cache_pool', $cachePool);

        $this->cachePoolPass->process($container);

        $definition = $container->getDefinition('cache.foo_bar_clearer');

        $this->assertTrue($definition->hasTag('cache.pool.clearer'));
        $this->assertEquals(['app.cache_pool' => new Reference('app.cache_pool', ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE)], $definition->getArgument(0));
    }
}
