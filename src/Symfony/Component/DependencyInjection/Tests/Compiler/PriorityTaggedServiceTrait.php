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
            'my_service1' => array(0 => array('priority' => 100)),
            'my_service2' => array(0 => array('priority' => 200)),
            'my_service3' => array(0 => array('priority' => -500)),
            'my_service4' => array(0 => array()),
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
                new Reference('my_service4'),
                new Reference('my_service3'),
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
