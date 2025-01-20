<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpKernel\DependencyInjection\MergeExtensionConfigurationPass;
use Symfony\Component\HttpKernel\Tests\Fixtures\AcmeFooBundle\AcmeFooBundle;

class MergeExtensionConfigurationPassTest extends TestCase
{
    public function testAutoloadMainExtension()
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new LoadedExtension());
        $container->registerExtension(new NotLoadedExtension());
        $container->loadFromExtension('loaded', []);

        $configPass = new MergeExtensionConfigurationPass(['loaded', 'not_loaded']);
        $configPass->process($container);

        $this->assertTrue($container->hasDefinition('loaded.foo'));
        $this->assertTrue($container->hasDefinition('not_loaded.bar'));
    }

    public function testFooBundle()
    {
        $bundle = new AcmeFooBundle();

        $container = new ContainerBuilder(new ParameterBag([
            'kernel.environment' => 'test',
            'kernel.build_dir' => sys_get_temp_dir(),
        ]));
        $container->registerExtension(new LoadedExtension());
        $container->registerExtension($bundle->getContainerExtension());

        $configPass = new MergeExtensionConfigurationPass(['loaded', 'acme_foo']);
        $configPass->process($container);

        $this->assertSame([['bar' => 'baz'], []], $container->getExtensionConfig('loaded'), '->prependExtension() prepends an extension config');
        $this->assertTrue($container->hasDefinition('acme_foo.foo'), '->loadExtension() registers a service');
        $this->assertTrue($container->hasDefinition('acme_foo.bar'), '->loadExtension() imports a service');
        $this->assertTrue($container->hasParameter('acme_foo.config'), '->loadExtension() sets a parameter');
        $this->assertSame(['foo' => 'bar', 'ping' => 'pong'], $container->getParameter('acme_foo.config'), '->loadConfiguration() defines and loads configurations');
    }
}

class LoadedExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $container->register('loaded.foo');
    }
}

class NotLoadedExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $container->register('not_loaded.bar');
    }
}
