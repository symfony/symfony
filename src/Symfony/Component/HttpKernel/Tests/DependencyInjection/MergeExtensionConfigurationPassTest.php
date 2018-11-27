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
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\HttpKernel\DependencyInjection\MergeExtensionConfigurationPass;

class MergeExtensionConfigurationPassTest extends TestCase
{
    public function testAutoloadMainExtension()
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new LoadedExtension());
        $container->registerExtension(new NotLoadedExtension());
        $container->loadFromExtension('loaded', array());

        $configPass = new MergeExtensionConfigurationPass(array('loaded', 'not_loaded'));
        $configPass->process($container);

        $this->assertTrue($container->hasDefinition('loaded.foo'));
        $this->assertTrue($container->hasDefinition('not_loaded.bar'));
    }
}

class LoadedExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $container->register('loaded.foo');
    }
}

class NotLoadedExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $container->register('not_loaded.bar');
    }
}
