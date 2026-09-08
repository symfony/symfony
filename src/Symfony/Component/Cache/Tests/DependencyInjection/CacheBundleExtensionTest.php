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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\CacheBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\ResolveChildDefinitionsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;

class CacheBundleExtensionTest extends TestCase
{
    public function testTheDefaultPoolsAreRegistered()
    {
        $container = $this->createContainerFromFile('cache');

        $this->assertSame(FilesystemAdapter::class, $container->getDefinition('cache.app')->getClass());
        $this->assertTrue($container->getDefinition('cache.app')->isPublic());
        $this->assertTrue($container->getDefinition('cache.system')->isPublic());
        $this->assertSame(TagAwareAdapter::class, $container->getDefinition('cache.app.taggable')->getClass());

        $this->assertTrue($container->hasDefinition('cache.default_marshaller'));
        $this->assertTrue($container->hasDefinition('cache.default_clearer'));
        $this->assertTrue($container->hasDefinition('cache.system_clearer'));
        $this->assertTrue($container->hasDefinition('cache.global_clearer'));
    }

    /**
     * The pools of the sections that use them are declared by the bundles owning those sections.
     */
    #[DataProvider('provideSectionPools')]
    public function testTheSectionPoolsAreNotRegistered(string $id)
    {
        $this->assertFalse($this->createContainerFromFile('cache')->has($id));
    }

    public static function provideSectionPools(): iterable
    {
        yield ['cache.validator'];
        yield ['cache.serializer'];
        yield ['cache.property_info'];
        yield ['cache.property_access'];
        yield ['cache.asset_mapper'];
        yield ['cache.messenger.restart_workers_signal'];
        yield ['cache.scheduler'];
    }

    public function testTheConfiguredPoolsAreRegistered()
    {
        $container = $this->createContainerFromFile('cache');

        $foo = $container->getDefinition('cache.foo');
        $this->assertSame(30, $foo->getTag('cache.pool')[0]['default_lifetime']);

        $bar = $container->getDefinition('cache.bar');
        $this->assertSame(TagAwareAdapter::class, $bar->getClass());
        $this->assertSame('.cache.bar.inner', (string) $bar->getArgument(0));
    }

    public function testTheDefaultProviderDeducesTheAdapterFromTheDsn()
    {
        $container = $this->createContainerFromFile('cache_default_provider');

        $app = $container->getDefinition('cache.app');
        $this->assertSame([AbstractAdapter::class, 'createAdapter'], $app->getFactory());

        $connection = $container->getDefinition((string) $app->getArgument(0));
        $this->assertSame([AbstractAdapter::class, 'createConnection'], $connection->getFactory());
        $this->assertStringContainsString('APP_CACHE_DSN', $connection->getArgument(0));

        $this->assertSame('my-app', $container->getParameter('cache.prefix.seed'));
    }

    public function testAPoolCanBeAChildOfAPoolDeclaredByAnotherExtension()
    {
        $container = $this->createContainerFromFile('cache', static function (ContainerBuilder $container) {
            $container->setDefinition('cache.acme', new ChildDefinition('cache.system'))->addTag('cache.pool');
        });

        $this->assertSame($container->getDefinition('cache.system')->getClass(), $container->getDefinition('cache.acme')->getClass());
    }

    private function createContainerFromFile(string $file, ?\Closure $configure = null): ContainerBuilder
    {
        $container = new ContainerBuilder(new EnvPlaceholderParameterBag([
            'kernel.debug' => false,
            'kernel.cache_dir' => __DIR__,
            'kernel.share_dir' => __DIR__,
            'kernel.project_dir' => __DIR__,
            'kernel.container_class' => 'testContainer',
            'container.build_id' => 'abc123',
        ]));
        $container->setResourceTracking(false);
        $container->registerExtension(new CacheBundle()->getContainerExtension());
        new PhpFileLoader($container, new FileLocator(__DIR__.'/Fixtures'))->load($file.'.php');
        $configure?->__invoke($container);
        $container->getCompilerPassConfig()->setBeforeOptimizationPasses([]);
        $container->getCompilerPassConfig()->setOptimizationPasses([new ResolveChildDefinitionsPass()]);
        $container->getCompilerPassConfig()->setBeforeRemovingPasses([]);
        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->getCompilerPassConfig()->setAfterRemovingPasses([]);
        $container->compile();

        return $container;
    }
}
