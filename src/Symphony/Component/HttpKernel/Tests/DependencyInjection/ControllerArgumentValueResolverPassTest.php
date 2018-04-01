<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\HttpKernel\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Definition;
use Symphony\Component\DependencyInjection\Reference;
use Symphony\Component\HttpKernel\Controller\ArgumentResolver;
use Symphony\Component\HttpKernel\DependencyInjection\ControllerArgumentValueResolverPass;

class ControllerArgumentValueResolverPassTest extends TestCase
{
    public function testServicesAreOrderedAccordingToPriority()
    {
        $services = array(
            'n3' => array(array()),
            'n1' => array(array('priority' => 200)),
            'n2' => array(array('priority' => 100)),
        );

        $expected = array(
            new Reference('n1'),
            new Reference('n2'),
            new Reference('n3'),
        );

        $definition = new Definition(ArgumentResolver::class, array(null, array()));
        $container = new ContainerBuilder();
        $container->setDefinition('argument_resolver', $definition);

        foreach ($services as $id => list($tag)) {
            $container->register($id)->addTag('controller.argument_value_resolver', $tag);
        }

        (new ControllerArgumentValueResolverPass())->process($container);
        $this->assertEquals($expected, $definition->getArgument(1)->getValues());
    }

    public function testReturningEmptyArrayWhenNoService()
    {
        $definition = new Definition(ArgumentResolver::class, array(null, array()));
        $container = new ContainerBuilder();
        $container->setDefinition('argument_resolver', $definition);

        (new ControllerArgumentValueResolverPass())->process($container);
        $this->assertEquals(array(), $definition->getArgument(1)->getValues());
    }

    public function testNoArgumentResolver()
    {
        $container = new ContainerBuilder();

        (new ControllerArgumentValueResolverPass())->process($container);

        $this->assertFalse($container->hasDefinition('argument_resolver'));
    }
}
