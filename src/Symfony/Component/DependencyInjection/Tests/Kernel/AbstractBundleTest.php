<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Kernel;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Kernel\BundleExtension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

class AbstractBundleTest extends TestCase
{
    public function testGetNameIsGuessedFromClass()
    {
        $bundle = new FooBarBundle();

        $this->assertSame('FooBarBundle', $bundle->getName());
    }

    public function testGetNameExplicit()
    {
        $bundle = new ExplicitlyNamedBundle();

        $this->assertSame('CustomName', $bundle->getName());
    }

    public function testGetPath()
    {
        $bundle = new FooBarBundle();

        // AbstractBundle assumes modern directory structure: dirname($file, 2)
        $this->assertSame(\dirname(__DIR__), $bundle->getPath());
    }

    public function testGetContainerExtension()
    {
        $bundle = new FooBarBundle();
        $extension = $bundle->getContainerExtension();

        $this->assertInstanceOf(ExtensionInterface::class, $extension);
        $this->assertInstanceOf(BundleExtension::class, $extension);
        $this->assertSame('foo_bar', $extension->getAlias());
    }

    public function testGetContainerExtensionIsCached()
    {
        $bundle = new FooBarBundle();

        $this->assertSame($bundle->getContainerExtension(), $bundle->getContainerExtension());
    }

    public function testSetContainer()
    {
        $bundle = new FooBarBundle();
        $container = $this->createStub(\Symfony\Component\DependencyInjection\ContainerInterface::class);

        $bundle->setContainer($container);
        $bundle->setContainer(null);

        // No assertion needed; verifies no error is thrown
        $this->assertTrue(true);
    }

    public function testLoadExtensionHookIsCalled()
    {
        $bundle = new LoadExtensionBundle();
        $extension = $bundle->getContainerExtension();

        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');
        $extension->load([], $container);

        $this->assertTrue($container->hasParameter('load_extension.called'));
    }

    public function testBuildHook()
    {
        $bundle = new BuildBundle();
        $container = new ContainerBuilder();
        $bundle->build($container);

        $this->assertTrue($container->hasParameter('build.called'));
    }
}

class FooBarBundle extends AbstractBundle
{
}

class ExplicitlyNamedBundle extends AbstractBundle
{
    public function __construct()
    {
        $this->name = 'CustomName';
    }
}

class LoadExtensionBundle extends AbstractBundle
{
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->setParameter('load_extension.called', true);
    }
}

class BuildBundle extends AbstractBundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->setParameter('build.called', true);
    }
}
