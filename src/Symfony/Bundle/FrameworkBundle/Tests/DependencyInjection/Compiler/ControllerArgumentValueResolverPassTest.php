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

use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\ControllerArgumentValueResolverPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;

class ControllerArgumentValueResolverPassTest extends \PHPUnit_Framework_TestCase
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
        $this->assertEquals($expected, $definition->getArgument(1));
    }

    public function testReturningEmptyArrayWhenNoService()
    {
        $definition = new Definition(ArgumentResolver::class, array(null, array()));
        $container = new ContainerBuilder();
        $container->setDefinition('argument_resolver', $definition);

        (new ControllerArgumentValueResolverPass())->process($container);
        $this->assertEquals(array(), $definition->getArgument(1));
    }

    public function testNoArgumentResolver()
    {
        $container = new ContainerBuilder();

        (new ControllerArgumentValueResolverPass())->process($container);

        $this->assertFalse($container->hasDefinition('argument_resolver'));
    }
}
