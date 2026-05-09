<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Kernel\BundleInterface;
use Symfony\Component\DependencyInjection\Kernel\KernelTrait;
use Symfony\Component\DependencyInjection\Kernel\RequiredBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;

class AbstractKernelTest extends TestCase
{
    private string $varDir;

    protected function setUp(): void
    {
        $this->varDir = sys_get_temp_dir().'/sf_abstract_kernel_test';
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->varDir);
    }

    public function testBootBuildsAndCachesContainer()
    {
        $kernel = $this->createKernel();
        $kernel->boot();

        $this->assertInstanceOf(ContainerInterface::class, $kernel->getContainer());
        $this->assertSame('test', $kernel->getContainer()->getParameter('kernel.environment'));
        $this->assertTrue($kernel->getContainer()->getParameter('kernel.debug'));
    }

    public function testBootIsIdempotent()
    {
        $kernel = $this->createKernel();
        $kernel->boot();
        $container1 = $kernel->getContainer();

        $kernel->boot();
        $this->assertSame($container1, $kernel->getContainer());
    }

    public function testShutdownClearsContainer()
    {
        $kernel = $this->createKernel();
        $kernel->boot();
        $kernel->shutdown();

        $this->expectException(\LogicException::class);
        $kernel->getContainer();
    }

    public function testRebootAfterShutdown()
    {
        $kernel = $this->createKernel();
        $kernel->boot();
        $class1 = $kernel->getContainer()::class;

        $kernel->shutdown();
        $kernel->boot();

        $this->assertSame($class1, $kernel->getContainer()::class);
    }

    public function testContainerIsCachedOnDisk()
    {
        $kernel = $this->createKernel();
        $kernel->boot();
        $class = $kernel->getContainer()::class;
        $kernel->shutdown();

        $kernel2 = $this->createKernel();
        $kernel2->boot();
        $this->assertSame($class, $kernel2->getContainer()::class);
    }

    public function testGetContainerThrowsBeforeBoot()
    {
        $kernel = $this->createKernel();

        $this->expectException(\LogicException::class);
        $kernel->getContainer();
    }

    public function testGetEnvironmentAndDebug()
    {
        $kernel = $this->createKernel('prod', false);
        $this->assertSame('prod', $kernel->getEnvironment());
        $this->assertFalse($kernel->isDebug());
    }

    public function testEmptyEnvironmentThrows()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->createKernel('');
    }

    public function testBuildHookIsCalled()
    {
        $kernel = new BuildHookKernel('test', true);
        $kernel->boot();
        $this->assertTrue($kernel->getContainer()->getParameter('build_hook_called'));
    }

    public function testKernelParametersAreSet()
    {
        $kernel = $this->createKernel();
        $kernel->boot();
        $container = $kernel->getContainer();

        $this->assertTrue($container->hasParameter('kernel.project_dir'));
        $this->assertSame('test', $container->getParameter('kernel.environment'));
        $this->assertTrue($container->getParameter('kernel.debug'));
        $this->assertTrue($container->hasParameter('kernel.build_dir'));
        $this->assertTrue($container->hasParameter('kernel.cache_dir'));
        $this->assertTrue($container->hasParameter('kernel.logs_dir'));
        $this->assertTrue($container->hasParameter('kernel.container_class'));
        $this->assertSame([], $container->getParameter('kernel.bundles'));
        $this->assertSame([], $container->getParameter('kernel.bundles_metadata'));
    }

    public function testKernelIsSyntheticService()
    {
        $kernel = $this->createKernel();
        $kernel->boot();

        $this->assertSame($kernel, $kernel->getContainer()->get('kernel'));
    }

    public function testClone()
    {
        $kernel = $this->createKernel();
        $kernel->boot();

        $clone = clone $kernel;

        $this->assertFalse($clone->isBooted());
        $this->expectException(\LogicException::class);
        $clone->getContainer();
    }

    public function testSerialize()
    {
        $kernel = new TestKernel('test', true);

        $unserialized = unserialize(serialize($kernel));

        $this->assertSame('test', $unserialized->getEnvironment());
        $this->assertTrue($unserialized->isDebug());
    }

    public function testBootSetsContainerOnBundles()
    {
        $bundle = $this->createMock(BundleInterface::class);
        $bundle->method('getName')->willReturn('TestBundle');
        $bundle->expects($this->once())->method('boot');
        $bundle->expects($this->atLeastOnce())
            ->method('setContainer')
            ->with($this->isInstanceOf(ContainerInterface::class));

        $kernel = $this->createKernelWithBundles([$bundle]);
        $kernel->boot();
    }

    public function testShutdownCallsShutdownOnBundles()
    {
        $bundle = $this->createMock(BundleInterface::class);
        $bundle->method('getName')->willReturn('TestBundle');
        $bundle->expects($this->once())->method('shutdown');
        $bundle->expects($this->atLeast(2))
            ->method('setContainer')
            ->willReturnCallback(function ($container) {
                if (null !== $container) {
                    $this->assertInstanceOf(ContainerInterface::class, $container);
                }
            });

        $kernel = $this->createKernelWithBundles([$bundle]);
        $kernel->boot();
        $kernel->shutdown();
    }

    public function testGetBundlesAndGetBundle()
    {
        $bundle = $this->createStub(BundleInterface::class);
        $bundle->method('getName')->willReturn('FooBundle');

        $kernel = $this->createKernelWithBundles([$bundle]);
        $kernel->boot();

        $this->assertCount(1, $kernel->getBundles());
        $this->assertSame($bundle, $kernel->getBundle('FooBundle'));
    }

    public function testGetBundleThrowsOnUnknownBundle()
    {
        $kernel = $this->createKernel();
        $kernel->boot();

        $this->expectException(\InvalidArgumentException::class);
        $kernel->getBundle('NonExistentBundle');
    }

    public function testDuplicateBundleNameThrows()
    {
        $bundle1 = $this->createStub(BundleInterface::class);
        $bundle1->method('getName')->willReturn('DuplicateName');
        $bundle2 = $this->createStub(BundleInterface::class);
        $bundle2->method('getName')->willReturn('DuplicateName');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Trying to register two bundles with the same name "DuplicateName"');

        $kernel = $this->createKernelWithBundles([$bundle1, $bundle2]);
        $kernel->boot();
    }

    public function testBundleKernelParameters()
    {
        $bundle = new TestAbstractBundle();

        $kernel = $this->createKernelWithBundles([$bundle]);
        $kernel->boot();

        $container = $kernel->getContainer();
        $bundles = $container->getParameter('kernel.bundles');
        $metadata = $container->getParameter('kernel.bundles_metadata');

        $this->assertArrayHasKey('TestAbstractBundle', $bundles);
        $this->assertSame(TestAbstractBundle::class, $bundles['TestAbstractBundle']);
        $this->assertArrayHasKey('TestAbstractBundle', $metadata);
        $this->assertArrayHasKey('path', $metadata['TestAbstractBundle']);
    }

    public function testGetStartTime()
    {
        $kernel = $this->createKernel('test', false);
        $this->assertSame(-\INF, $kernel->getStartTime());
    }

    public function testContainerClassValidity()
    {
        $this->expectException(\InvalidArgumentException::class);
        $kernel = $this->createKernel('test.env');
        $kernel->boot();
    }

    public function testRegisterBundlesFromFile()
    {
        $dir = $this->varDir;
        @mkdir($dir.'/config', 0o777, true);
        file_put_contents($dir.'/config/bundles.php', '<?php return ['.TestAbstractBundle::class.'::class => [\'all\' => true]];');

        $kernel = $this->createKernel();
        $kernel->boot();

        $this->assertCount(1, $kernel->getBundles());
        $this->assertInstanceOf(TestAbstractBundle::class, $kernel->getBundle('TestAbstractBundle'));
    }

    public function testRegisterBundlesRespectsEnvironment()
    {
        $dir = $this->varDir;
        @mkdir($dir.'/config', 0o777, true);
        file_put_contents($dir.'/config/bundles.php', '<?php return ['.TestAbstractBundle::class.'::class => [\'dev\' => true]];');

        $kernel = $this->createKernel('prod');
        $kernel->boot();

        $this->assertCount(0, $kernel->getBundles());
    }

    public function testBundleCacheIsNotUsedInDebugWhenNotFresh()
    {
        $dir = $this->varDir;
        @mkdir($dir.'/config', 0o777, true);
        file_put_contents($dir.'/config/bundles.php', '<?php return ['.TestAbstractBundle::class.'::class => [\'all\' => true]];');

        $kernel = $this->createKernel();
        $kernel->boot();

        $this->assertCount(1, $kernel->getBundles());
        $kernel->shutdown();

        file_put_contents($dir.'/config/bundles.php', '<?php return ['.TestAbstractBundle::class.'::class => [\'all\' => true], '.ChildBundle::class.'::class => [\'all\' => true]];');

        $kernel = $this->createKernel();
        $kernel->boot();

        $this->assertCount(2, $kernel->getBundles());
    }

    public function testBundleCacheIsUsedInDebugWhenFresh()
    {
        $dir = $this->varDir;
        @mkdir($dir.'/config', 0o777, true);
        file_put_contents($dir.'/config/bundles.php', '<?php return ['.TestAbstractBundle::class.'::class => [\'all\' => true]];');

        $kernel = $this->createKernel();
        $kernel->boot();

        $this->assertCount(1, $kernel->getBundles());
        $kernel->shutdown();

        // Modify bundles.php but backdate it so the cache file remains newer
        file_put_contents($dir.'/config/bundles.php', '<?php return ['.TestAbstractBundle::class.'::class => [\'all\' => true], '.ChildBundle::class.'::class => [\'all\' => true]];');
        touch($dir.'/config/bundles.php', time() - 10);

        $kernel = $this->createKernel();
        $kernel->boot();

        $this->assertCount(1, $kernel->getBundles(), 'Bundle cache should be used in debug mode when cache is newer than bundles.php');
    }

    public function testBundleCacheIsUsedInNoDebug()
    {
        $dir = $this->varDir;
        @mkdir($dir.'/config', 0o777, true);
        file_put_contents($dir.'/config/bundles.php', '<?php return ['.TestAbstractBundle::class.'::class => [\'all\' => true]];');

        $kernel = $this->createKernel('test', false);
        $kernel->boot();

        $this->assertCount(1, $kernel->getBundles());
        $kernel->shutdown();

        file_put_contents($dir.'/config/bundles.php', '<?php return ['.TestAbstractBundle::class.'::class => [\'all\' => true], '.ChildBundle::class.'::class => [\'all\' => true]];');

        $kernel = $this->createKernel('test', false);
        $kernel->boot();

        $this->assertCount(1, $kernel->getBundles(), 'Bundle cache should be used in non-debug mode even when bundles.php changes');
    }

    public function testBundleCacheStaysPopulatedWhenContainerIsRebuilt()
    {
        $dir = $this->varDir;
        @mkdir($dir.'/config', 0o777, true);
        file_put_contents($dir.'/config/bundles.php', '<?php return ['.TestAbstractBundle::class.'::class => [\'all\' => true]];');
        touch($dir.'/config/bundles.php', time() - 10);

        $kernel = $this->createKernel();
        @mkdir($kernel->getBuildDir(), 0o777, true);
        file_put_contents($kernel->getBuildDir().'/'.$kernel->getTestContainerClass().'.bundles.php', '<?php return [\'TestAbstractBundle\' => new \Symfony\Component\DependencyInjection\Tests\TestAbstractBundle()];');

        $kernel->boot();

        $this->assertCount(1, $kernel->getBundles());
        $kernel->shutdown();

        $kernel = $this->createKernel();
        $kernel->boot();

        $this->assertCount(1, $kernel->getBundles());
    }

    public function testConfigureContainerHook()
    {
        $kernel = new ConfigureContainerKernel('test', true);
        $kernel->boot();
        $this->assertTrue($kernel->getContainer()->getParameter('configured'));
    }

    public function testDefaultContainerConfigurationLoadsServicesYaml()
    {
        $dir = $this->varDir;
        @mkdir($dir.'/config', 0o777, true);
        file_put_contents($dir.'/config/services.yaml', "parameters:\n    from_yaml: true\n");

        $kernel = $this->createKernel();
        $kernel->boot();

        $this->assertTrue($kernel->getContainer()->getParameter('from_yaml'));
    }

    public function testBundleExtensionIsRegistered()
    {
        $kernel = $this->createKernelWithBundles([new ExtensionBundle()]);
        $kernel->boot();

        $this->assertTrue($kernel->getContainer()->hasParameter('extension_bundle.loaded'));
    }

    public function testBundleExtensionIsLoadedWithoutExplicitConfig()
    {
        $kernel = $this->createKernelWithBundles([new ImplicitExtensionBundle()]);
        $kernel->boot();

        $this->assertTrue($kernel->getContainer()->hasParameter('implicit_extension.loaded'));
    }

    public function testBundleAsCompilerPass()
    {
        $kernel = $this->createKernelWithBundles([new CompilerPassBundle()]);
        $kernel->boot();

        $this->assertTrue($kernel->getContainer()->hasParameter('compiler_pass.processed'));
    }

    public function testRequiredBundleChildrenAreRegisteredBeforeParent()
    {
        $kernel = new RequiredBundleKernel('test', true, $this->varDir, [MetaParentBundle::class => ['all' => true]]);
        $kernel->boot();

        $this->assertSame(['ChildBundle', 'MetaParentBundle'], array_keys($kernel->getBundles()));
    }

    public function testRequiredBundleDeduplicatesByClass()
    {
        // ChildBundle is listed explicitly AND required by MetaParentBundle
        $kernel = new RequiredBundleKernel('test', true, $this->varDir, [
            ChildBundle::class => ['all' => true],
            MetaParentBundle::class => ['all' => true],
        ]);
        $kernel->boot();

        $this->assertSame(['ChildBundle', 'MetaParentBundle'], array_keys($kernel->getBundles()));
    }

    public function testNestedRequiredBundles()
    {
        $kernel = new RequiredBundleKernel('test', true, $this->varDir, [TopMetaBundle::class => ['all' => true]]);
        $kernel->boot();

        $this->assertSame(['LeafBundle', 'MiddleMetaBundle', 'TopMetaBundle'], array_keys($kernel->getBundles()));
    }

    public function testTwoRequiredBundlesWithSameChild()
    {
        $kernel = new RequiredBundleKernel('test', true, $this->varDir, [
            MetaParentBundle::class => ['all' => true],
            SecondMetaParentBundle::class => ['all' => true],
        ]);
        $kernel->boot();

        $this->assertSame(['ChildBundle', 'MetaParentBundle', 'SecondMetaParentBundle'], array_keys($kernel->getBundles()));
    }

    public function testRequiredBundleSelfReferenceIsDeduped()
    {
        $kernel = new RequiredBundleKernel('test', true, $this->varDir, [SelfReferencingMetaBundle::class => ['all' => true]]);
        $kernel->boot();

        $this->assertSame(['SelfReferencingMetaBundle'], array_keys($kernel->getBundles()));
    }

    public function testRequiredBundleCircularReferenceIsDeduped()
    {
        $kernel = new RequiredBundleKernel('test', true, $this->varDir, [CircularMetaBundleA::class => ['all' => true]]);
        $kernel->boot();

        // Both bundles are registered (circular reference doesn't cause infinite loop)
        $this->assertSame(['CircularMetaBundleB', 'CircularMetaBundleA'], array_keys($kernel->getBundles()));
    }

    public function testRequiredBundleIgnoreOnInvalid()
    {
        $kernel = new RequiredBundleKernel('test', true, $this->varDir, [IgnoreOnInvalidBundle::class => ['all' => true]]);
        $kernel->boot();

        $this->assertSame(['IgnoreOnInvalidBundle'], array_keys($kernel->getBundles()));
    }

    private function createKernel(string $env = 'test', bool $debug = true): TestKernel
    {
        return new TestKernel($env, $debug, $this->varDir);
    }

    private function createKernelWithBundles(array $bundles): BundleTestKernel
    {
        return new BundleTestKernel($bundles, $this->varDir);
    }
}

class TestKernel extends AbstractKernel
{
    use KernelTrait {
        registerBundles as public;
    }

    public function __construct(string $env, bool $debug, private string $dir = '')
    {
        parent::__construct($env, $debug);
    }

    public function getProjectDir(): string
    {
        return $this->dir ?: sys_get_temp_dir().'/sf_abstract_kernel_test';
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }

    public function getTestContainerClass(): string
    {
        return $this->getContainerClass();
    }
}

class BuildHookKernel extends TestKernel
{
    protected function build(ContainerBuilder $container): void
    {
        $container->setParameter('build_hook_called', true);
    }
}

class ConfigureContainerKernel extends TestKernel
{
    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->parameters()->set('configured', true);
    }
}

class BundleTestKernel extends AbstractKernel
{
    use KernelTrait;

    public function __construct(private array $testBundles, private string $dir)
    {
        parent::__construct('test', true);
    }

    public function getProjectDir(): string
    {
        return $this->dir;
    }

    protected function initializeBundles(): void
    {
        $this->bundles = [];
        foreach ($this->testBundles as $bundle) {
            $name = $bundle->getName();
            if (isset($this->bundles[$name])) {
                throw new \LogicException(\sprintf('Trying to register two bundles with the same name "%s".', $name));
            }
            $this->bundles[$name] = $bundle;
        }
    }
}

class TestAbstractBundle extends AbstractBundle
{
}

class ExtensionBundle extends AbstractBundle
{
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->setParameter('extension_bundle.loaded', true);
    }
}

class CompilerPassBundle extends AbstractBundle implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $container->setParameter('compiler_pass.processed', true);
    }
}

class ImplicitExtensionBundle extends AbstractBundle
{
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->setParameter('implicit_extension.loaded', true);
    }
}

class ChildBundle extends AbstractBundle
{
}

#[RequiredBundle(ChildBundle::class)]
class MetaParentBundle extends AbstractBundle
{
}

class LeafBundle extends AbstractBundle
{
}

#[RequiredBundle(LeafBundle::class)]
class MiddleMetaBundle extends AbstractBundle
{
}

#[RequiredBundle(MiddleMetaBundle::class)]
class TopMetaBundle extends AbstractBundle
{
}

#[RequiredBundle(ChildBundle::class)]
class SecondMetaParentBundle extends AbstractBundle
{
}

#[RequiredBundle('NonExistent\\Bundle\\ThatDoesNotExist', ignoreOnInvalid: true)]
class IgnoreOnInvalidBundle extends AbstractBundle
{
}

#[RequiredBundle(self::class)]
class SelfReferencingMetaBundle extends AbstractBundle
{
}

#[RequiredBundle(CircularMetaBundleB::class)]
class CircularMetaBundleA extends AbstractBundle
{
}

#[RequiredBundle(CircularMetaBundleA::class)]
class CircularMetaBundleB extends AbstractBundle
{
}

class RequiredBundleKernel extends TestKernel
{
    use KernelTrait {
        registerBundles as public;
    }

    public function __construct(string $env, bool $debug, private string $testDir, private array $bundlesDefinition)
    {
        parent::__construct($env, $debug, $testDir);
    }

    public function getProjectDir(): string
    {
        return $this->testDir;
    }

    private function getBundlesDefinition(): array
    {
        return $this->bundlesDefinition;
    }
}
