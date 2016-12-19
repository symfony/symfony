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
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AddCacheWarmerPass;

class AddCacheWarmerPassTest extends \PHPUnit_Framework_TestCase
{
    public function testThatCacheWarmersAreProcessedInPriorityOrder()
    {
        $services = array(
            'my_cache_warmer_service1' => array(0 => array('priority' => 100)),
            'my_cache_warmer_service2' => array(0 => array('priority' => 200)),
            'my_cache_warmer_service3' => array(),
        );

        $definition = $this->getMockBuilder('Symfony\Component\DependencyInjection\Definition')->getMock();
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')->setMethods(array('findTaggedServiceIds', 'getDefinition', 'hasDefinition'))->getMock();

        $container->expects($this->atLeastOnce())
            ->method('findTaggedServiceIds')
            ->will($this->returnValue($services));
        $container->expects($this->atLeastOnce())
            ->method('getDefinition')
            ->with('cache_warmer')
            ->will($this->returnValue($definition));
        $container->expects($this->atLeastOnce())
            ->method('hasDefinition')
            ->with('cache_warmer')
            ->will($this->returnValue(true));

        $definition->expects($this->once())
            ->method('replaceArgument')
            ->with(0, array(
                new Reference('my_cache_warmer_service2'),
                new Reference('my_cache_warmer_service1'),
                new Reference('my_cache_warmer_service3'),
            ));

        $addCacheWarmerPass = new AddCacheWarmerPass();
        $addCacheWarmerPass->process($container);
    }

    public function testThatCompilerPassIsIgnoredIfThereIsNoCacheWarmerDefinition()
    {
        $definition = $this->getMockBuilder('Symfony\Component\DependencyInjection\Definition')->getMock();
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')->setMethods(array('hasDefinition', 'findTaggedServiceIds', 'getDefinition'))->getMock();

        $container->expects($this->never())->method('findTaggedServiceIds');
        $container->expects($this->never())->method('getDefinition');
        $container->expects($this->atLeastOnce())
            ->method('hasDefinition')
            ->with('cache_warmer')
            ->will($this->returnValue(false));
        $definition->expects($this->never())->method('replaceArgument');

        $addCacheWarmerPass = new AddCacheWarmerPass();
        $addCacheWarmerPass->process($container);
    }

    public function testThatCacheWarmersMightBeNotDefined()
    {
        $definition = $this->getMockBuilder('Symfony\Component\DependencyInjection\Definition')->getMock();
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')->setMethods(array('hasDefinition', 'findTaggedServiceIds', 'getDefinition'))->getMock();

        $container->expects($this->atLeastOnce())
            ->method('findTaggedServiceIds')
            ->will($this->returnValue(array()));
        $container->expects($this->never())->method('getDefinition');
        $container->expects($this->atLeastOnce())
            ->method('hasDefinition')
            ->with('cache_warmer')
            ->will($this->returnValue(true));

        $definition->expects($this->never())->method('replaceArgument');

        $addCacheWarmerPass = new AddCacheWarmerPass();
        $addCacheWarmerPass->process($container);
    }
}
