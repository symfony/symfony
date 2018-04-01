<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Routing\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Config\Loader\LoaderResolver;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Reference;
use Symphony\Component\Routing\DependencyInjection\RoutingResolverPass;

class RoutingResolverPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $container->register('routing.resolver', LoaderResolver::class);
        $container->register('loader1')->addTag('routing.loader');
        $container->register('loader2')->addTag('routing.loader');

        (new RoutingResolverPass())->process($container);

        $this->assertEquals(
            array(array('addLoader', array(new Reference('loader1'))), array('addLoader', array(new Reference('loader2')))),
            $container->getDefinition('routing.resolver')->getMethodCalls()
        );
    }
}
