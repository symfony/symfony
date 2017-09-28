<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Config\DependencyInjection\ConfigCachePass;

/**
 * @group legacy
 */
class ConfigCachePassTest extends TestCase
{
    public function testThatCheckersAreProcessedInPriorityOrder()
    {
        $container = new ContainerBuilder();

        $definition = $container->register('config_cache_factory')->addArgument(null);
        $container->register('checker_2')->addTag('config_cache.resource_checker', array('priority' => 100));
        $container->register('checker_1')->addTag('config_cache.resource_checker', array('priority' => 200));
        $container->register('checker_3')->addTag('config_cache.resource_checker');

        $pass = new ConfigCachePass();
        $pass->process($container);

        $expected = new IteratorArgument(array(
            new Reference('checker_1'),
            new Reference('checker_2'),
            new Reference('checker_3'),
        ));
        $this->assertEquals($expected, $definition->getArgument(0));
    }

    public function testThatCheckersCanBeMissing()
    {
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')->setMethods(array('findTaggedServiceIds'))->getMock();

        $container->expects($this->atLeastOnce())
            ->method('findTaggedServiceIds')
            ->will($this->returnValue(array()));

        $pass = new ConfigCachePass();
        $pass->process($container);
    }
}
