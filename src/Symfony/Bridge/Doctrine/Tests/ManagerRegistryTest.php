<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests;

use PHPUnit\Framework\TestCase;
use ProxyManager\Proxy\LazyLoadingInterface;
use ProxyManager\Proxy\ValueHolderInterface;
use Symfony\Bridge\Doctrine\ManagerRegistry;
use Symfony\Bridge\Doctrine\Tests\Fixtures\DummyManager;
use Symfony\Bridge\ProxyManager\LazyProxy\PhpDumper\ProxyDumper;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\Filesystem\Filesystem;

class ManagerRegistryTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo', DummyManager::class)->setPublic(true);
        $container->getDefinition('foo')->setLazy(true);
        $container->compile();

        $dumper = new PhpDumper($container);
        $dumper->setProxyDumper(new ProxyDumper());
        eval('?>'.$dumper->dump(['class' => 'LazyServiceDoctrineBridgeContainer']));
    }

    public function testResetService()
    {
        $container = new \LazyServiceDoctrineBridgeContainer();

        $registry = new TestManagerRegistry('name', [], ['defaultManager' => 'foo'], 'defaultConnection', 'defaultManager', 'proxyInterfaceName');
        $registry->setTestContainer($container);

        $foo = $container->get('foo');
        $foo->bar = 123;
        $this->assertTrue(isset($foo->bar));

        $registry->resetManager();

        $this->assertSame($foo, $container->get('foo'));
        $this->assertInstanceOf(DummyManager::class, $foo);
        $this->assertFalse(isset($foo->bar));
    }

    /**
     * When performing an entity manager lazy service reset, the reset operations may re-use the container
     * to create a "fresh" service: when doing so, it can happen that the "fresh" service is itself a proxy.
     *
     * Because of that, the proxy will be populated with a wrapped value that is itself a proxy: repeating
     * the reset operation keeps increasing this nesting until the application eventually runs into stack
     * overflow or memory overflow operations, which can happen for long-running processes that rely on
     * services that are reset very often.
     */
    public function testResetServiceWillNotNestFurtherLazyServicesWithinEachOther()
    {
        // This test scenario only applies to containers composed as a set of generated sources
        $this->dumpLazyServiceProjectAsFilesServiceContainer();

        /** @var ContainerInterface $container */
        $container = new \LazyServiceProjectAsFilesServiceContainer();

        $registry = new TestManagerRegistry(
            'irrelevant',
            [],
            ['defaultManager' => 'foo'],
            'irrelevant',
            'defaultManager',
            'irrelevant'
        );
        $registry->setTestContainer($container);

        $service = $container->get('foo');

        self::assertInstanceOf(DummyManager::class, $service);
        self::assertInstanceOf(LazyLoadingInterface::class, $service);
        self::assertInstanceOf(ValueHolderInterface::class, $service);
        self::assertFalse($service->isProxyInitialized());

        $service->initializeProxy();

        self::assertTrue($container->initialized('foo'));
        self::assertTrue($service->isProxyInitialized());

        $registry->resetManager();
        $service->initializeProxy();

        $wrappedValue = $service->getWrappedValueHolderValue();
        self::assertInstanceOf(DummyManager::class, $wrappedValue);
        self::assertNotInstanceOf(LazyLoadingInterface::class, $wrappedValue);
        self::assertNotInstanceOf(ValueHolderInterface::class, $wrappedValue);
    }

    private function dumpLazyServiceProjectAsFilesServiceContainer()
    {
        if (class_exists(\LazyServiceProjectAsFilesServiceContainer::class, false)) {
            return;
        }

        $container = new ContainerBuilder();

        $container->register('foo', DummyManager::class)
            ->setPublic(true)
            ->setLazy(true);
        $container->compile();

        $fileSystem = new Filesystem();

        $temporaryPath = $fileSystem->tempnam(sys_get_temp_dir(), 'symfonyManagerRegistryTest');
        $fileSystem->remove($temporaryPath);
        $fileSystem->mkdir($temporaryPath);

        $dumper = new PhpDumper($container);

        $dumper->setProxyDumper(new ProxyDumper());
        $containerFiles = $dumper->dump([
            'class' => 'LazyServiceProjectAsFilesServiceContainer',
            'as_files' => true,
        ]);

        array_walk(
            $containerFiles,
            static function (string $containerSources, string $fileName) use ($temporaryPath): void {
                (new Filesystem())->dumpFile($temporaryPath.'/'.$fileName, $containerSources);
            }
        );

        require $temporaryPath.'/LazyServiceProjectAsFilesServiceContainer.php';
    }
}

class TestManagerRegistry extends ManagerRegistry
{
    public function setTestContainer($container)
    {
        $this->container = $container;
    }

    public function getAliasNamespace($alias): string
    {
        return 'Foo';
    }
}
