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
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AddCacheWarmerPass;

/**
 * @group legacy
 */
class AddCacheWarmerPassTest extends TestCase
{
    public function testThatCacheWarmersAreProcessedInPriorityOrder()
    {
        $container = new ContainerBuilder();

        $definition = $container->register('cache_warmer')->addArgument(null);
        $container->register('my_cache_warmer_service1')->addTag('kernel.cache_warmer', array('priority' => 100));
        $container->register('my_cache_warmer_service2')->addTag('kernel.cache_warmer', array('priority' => 200));
        $container->register('my_cache_warmer_service3')->addTag('kernel.cache_warmer');

        $addCacheWarmerPass = new AddCacheWarmerPass();
        $addCacheWarmerPass->process($container);

        $expected = array(
            new Reference('my_cache_warmer_service2'),
            new Reference('my_cache_warmer_service1'),
            new Reference('my_cache_warmer_service3'),
        );
        $this->assertEquals($expected, $definition->getArgument(0));
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
