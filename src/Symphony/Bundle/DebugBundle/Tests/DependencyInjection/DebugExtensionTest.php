<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\DebugBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symphony\Bundle\DebugBundle\DependencyInjection\DebugExtension;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\ParameterBag\ParameterBag;

class DebugExtensionTest extends TestCase
{
    public function testLoadWithoutConfiguration()
    {
        $container = $this->createContainer();
        $container->registerExtension(new DebugExtension());
        $container->loadFromExtension('debug', array());
        $this->compileContainer($container);

        $expectedTags = array(
            array(
                'id' => 'dump',
                'template' => '@Debug/Profiler/dump.html.twig',
                'priority' => 240,
            ),
        );

        $this->assertSame($expectedTags, $container->getDefinition('data_collector.dump')->getTag('data_collector'));
    }

    private function createContainer()
    {
        $container = new ContainerBuilder(new ParameterBag(array(
            'kernel.cache_dir' => __DIR__,
            'kernel.charset' => 'UTF-8',
            'kernel.debug' => true,
            'kernel.bundles' => array('DebugBundle' => 'Symphony\\Bundle\\DebugBundle\\DebugBundle'),
        )));

        return $container;
    }

    private function compileContainer(ContainerBuilder $container)
    {
        $container->getCompilerPassConfig()->setOptimizationPasses(array());
        $container->getCompilerPassConfig()->setRemovingPasses(array());
        $container->compile();
    }
}
