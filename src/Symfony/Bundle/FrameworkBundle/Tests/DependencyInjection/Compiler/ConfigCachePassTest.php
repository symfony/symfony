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

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\ConfigCachePass;

class ConfigCachePassTest extends \PHPUnit_Framework_TestCase
{
    public function testThatCheckersAreProcessedInPriorityOrder()
    {
        $services = array(
            'checker_2' => array(0 => array('priority' => 100)),
            'checker_1' => array(0 => array('priority' => 200)),
            'checker_3' => array(0 => array()),
        );

        $definition = $this->getMock('Symfony\Component\DependencyInjection\Definition');
        $container = $this->getMock(
            'Symfony\Component\DependencyInjection\ContainerBuilder',
            array('findTaggedServiceIds', 'getDefinition', 'hasDefinition')
        );

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
        $container = $this->getMock(
            'Symfony\Component\DependencyInjection\ContainerBuilder',
            array('findTaggedServiceIds')
        );

        $container->expects($this->atLeastOnce())
            ->method('findTaggedServiceIds')
            ->will($this->returnValue(array()));

        $pass = new ConfigCachePass();
        $pass->process($container);
    }
}
