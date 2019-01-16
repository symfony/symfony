<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Routing\DependencyInjection\RoutingResolverPass;

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
            [['addLoader', [new Reference('loader1')]], ['addLoader', [new Reference('loader2')]]],
            $container->getDefinition('routing.resolver')->getMethodCalls()
        );
    }
}
