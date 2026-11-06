<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AttributeRouteControllerLoaderPass;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Routing\Loader\AttributeDirectoryLoader;
use Symfony\Component\Routing\Loader\AttributeRouteControllerLoader as BaseAttributeRouteControllerLoader;

class AttributeRouteControllerLoaderPassTest extends TestCase
{
    public function testLoaderClassIsSwitchedToTheDeprecatedFrameworkBundleClass()
    {
        $container = new ContainerBuilder();
        $container->register('routing.loader.attribute', BaseAttributeRouteControllerLoader::class);

        (new AttributeRouteControllerLoaderPass())->process($container);

        $this->assertSame(AttributeRouteControllerLoader::class, $container->getDefinition('routing.loader.attribute')->getClass());
    }

    public function testPassDoesNothingWithoutTheLoaderDefinition()
    {
        $container = new ContainerBuilder();

        (new AttributeRouteControllerLoaderPass())->process($container);

        $this->assertFalse($container->hasDefinition('routing.loader.attribute'));
    }

    public function testPassDoesNotOverrideACustomLoaderClass()
    {
        $container = new ContainerBuilder();
        $container->register('routing.loader.attribute', AttributeDirectoryLoader::class);

        (new AttributeRouteControllerLoaderPass())->process($container);

        $this->assertSame(AttributeDirectoryLoader::class, $container->getDefinition('routing.loader.attribute')->getClass());
    }
}
