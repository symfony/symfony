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
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\ConfigCachePass;

class ConfigCachePassTest extends TestCase
{
    public function testThatCheckersAreProcessedInPriorityOrder()
    {
        $services = array(
            'checker_2' => array(0 => array('priority' => 100)),
            'checker_1' => array(0 => array('priority' => 200)),
            'checker_3' => array(),
        );

        $definition = $this->getMockBuilder('Symfony\Component\DependencyInjection\Definition')->getMock();
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')->setMethods(array('findTaggedServiceIds', 'getDefinition', 'hasDefinition'))->getMock();

        $container->expects($this->atLeastOnce())
            ->method('findTaggedServiceIds')
            ->will($this->returnValue($services));
        $container->expects($this->atLeastOnce())
            ->method('getDefinition')
            ->with('config_cache_factory')
            ->will($this->returnValue($definition));

        $definition->expects($this->once())
            ->method('replaceArgument')
            ->with(0, array(
                    new Reference('checker_1'),
                    new Reference('checker_2'),
                    new Reference('checker_3'),
                ));

        $pass = new ConfigCachePass();
        $pass->process($container);
    }

    public function testThatCheckersCanBeMissing()
    {
        $definition = $this->getMockBuilder('Symfony\Component\DependencyInjection\Definition')->getMock();
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')->setMethods(array('findTaggedServiceIds'))->getMock();

        $container->expects($this->atLeastOnce())
            ->method('findTaggedServiceIds')
            ->will($this->returnValue(array()));

        $pass = new ConfigCachePass();
        $pass->process($container);
    }
}
