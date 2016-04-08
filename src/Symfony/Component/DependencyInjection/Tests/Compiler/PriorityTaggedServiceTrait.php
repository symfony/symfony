<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class PriorityTaggedServiceTraitTest extends \PHPUnit_Framework_TestCase
{
    public function testThatCacheWarmersAreProcessedInPriorityOrder()
    {
        $services = array(
            'my_service1' => array(array('priority' => 100)),
            'my_service2' => array(array('priority' => 200)),
            'my_service3' => array(array('priority' => -500)),
            'my_service4' => array(array()),
            'my_service5' => array(array()),
            'my_service6' => array(array('priority' => -500)),
            'my_service7' => array(array('priority' => -499)),
            'my_service8' => array(array('priority' => 1)),
            'my_service9' => array(array()),
            'my_service10' => array(array('priority' => -1000)),
            'my_service11' => array(array('priority' => -1000)),
            'my_service12' => array(array('priority' => -1000)),
            'my_service13' => array(array('priority' => -1000)),
        );

        $definition = $this->getMock('Symfony\Component\DependencyInjection\Definition');
        $container = $this->getMock(
            'Symfony\Component\DependencyInjection\ContainerBuilder',
            array('findTaggedServiceIds', 'getDefinition', 'hasDefinition')
        );

        $container
            ->expects($this->atLeastOnce())
            ->method('findTaggedServiceIds')
            ->will($this->returnValue($services));
        $container
            ->expects($this->atLeastOnce())
            ->method('getDefinition')
            ->with('my_custom_tag')
            ->will($this->returnValue($definition));

        $definition
            ->expects($this->once())
            ->method('replaceArgument')
            ->with(0, array(
                new Reference('my_service2'),
                new Reference('my_service1'),
                new Reference('my_service8'),
                new Reference('my_service4'),
                new Reference('my_service5'),
                new Reference('my_service9'),
                new Reference('my_service7'),
                new Reference('my_service3'),
                new Reference('my_service6'),
                new Reference('my_service10'),
                new Reference('my_service11'),
                new Reference('my_service12'),
                new Reference('my_service13'),
            ));

        (new PriorityTaggedServiceTraitImplementation())->test('my_custom_tag', $container);
    }
}

class PriorityTaggedServiceTraitImplementation
{
    use PriorityTaggedServiceTrait;

    public function test($tagName, ContainerBuilder $container)
    {
        return $this->findAndSortTaggedServices($tagName, $container);
    }
}
