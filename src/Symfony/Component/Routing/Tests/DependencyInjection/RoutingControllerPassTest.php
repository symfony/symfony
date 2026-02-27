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
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Routing\DependencyInjection\RoutingControllerPass;

class RoutingControllerPassTest extends TestCase
{
    public function testProcessInjectsTaggedControllerClassesOrderedAndUnique()
    {
        $container = new ContainerBuilder();
        $container->setParameter('ctrl_a.class', CtrlA::class);

        $container->register('routing.loader.attribute.services', \stdClass::class)
            ->setArguments([null]);

        $container->register('ctrl_a', '%ctrl_a.class%')->addTag('routing.controller', ['priority' => 10]);
        $container->register('ctrl_b', CtrlB::class)->addTag('routing.controller', ['priority' => 20]);
        $container->register('ctrl_c', CtrlC::class)->addTag('routing.controller', ['priority' => -5]);

        (new RoutingControllerPass())->process($container);

        $this->assertSame([
            CtrlB::class,
            CtrlA::class,
            CtrlC::class,
        ], $container->getDefinition('routing.loader.attribute.services')->getArgument(0));
    }

    public function testProcessWithNoTaggedControllersSetsEmptyList()
    {
        $container = new ContainerBuilder();

        $loaderDef = new Definition(\stdClass::class);
        $loaderDef->setArguments([['preexisting']]);
        $container->setDefinition('routing.loader.attribute.services', $loaderDef);

        (new RoutingControllerPass())->process($container);

        $this->assertSame([], $container->getDefinition('routing.loader.attribute.services')->getArgument(0));
    }
}
