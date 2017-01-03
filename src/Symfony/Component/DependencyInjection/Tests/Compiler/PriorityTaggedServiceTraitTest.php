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
            'my_service1' => array('my_custom_tag' => array('priority' => 100)),
            'my_service2' => array('my_custom_tag' => array('priority' => 200)),
            'my_service3' => array('my_custom_tag' => array('priority' => -501)),
            'my_service4' => array('my_custom_tag' => array()),
            'my_service5' => array('my_custom_tag' => array('priority' => -1)),
            'my_service6' => array('my_custom_tag' => array('priority' => -500)),
            'my_service7' => array('my_custom_tag' => array('priority' => -499)),
            'my_service8' => array('my_custom_tag' => array('priority' => 1)),
            'my_service9' => array('my_custom_tag' => array('priority' => -2)),
            'my_service10' => array('my_custom_tag' => array('priority' => -1000)),
            'my_service11' => array('my_custom_tag' => array('priority' => -1001)),
            'my_service12' => array('my_custom_tag' => array('priority' => -1002)),
            'my_service13' => array('my_custom_tag' => array('priority' => -1003)),
            'my_service14' => array('my_custom_tag' => array('priority' => -1000)),
            'my_service15' => array('my_custom_tag' => array('priority' => 1)),
            'my_service16' => array('my_custom_tag' => array('priority' => -1)),
            'my_service17' => array('my_custom_tag' => array('priority' => 200)),
            'my_service18' => array('my_custom_tag' => array('priority' => 100)),
            'my_service19' => array('my_custom_tag' => array()),
        );

        $container = new ContainerBuilder();

        foreach ($services as $id => $tags) {
            $definition = $container->register($id);

            foreach ($tags as $name => $attributes) {
                $definition->addTag($name, $attributes);
            }
        }

        $expected = array(
            new Reference('my_service2'),
            new Reference('my_service17'),
            new Reference('my_service1'),
            new Reference('my_service18'),
            new Reference('my_service8'),
            new Reference('my_service15'),
            new Reference('my_service4'),
            new Reference('my_service19'),
            new Reference('my_service5'),
            new Reference('my_service16'),
            new Reference('my_service9'),
            new Reference('my_service7'),
            new Reference('my_service6'),
            new Reference('my_service3'),
            new Reference('my_service10'),
            new Reference('my_service14'),
            new Reference('my_service11'),
            new Reference('my_service12'),
            new Reference('my_service13'),
        );

        $priorityTaggedServiceTraitImplementation = new PriorityTaggedServiceTraitImplementation();

        $this->assertEquals($expected, $priorityTaggedServiceTraitImplementation->test('my_custom_tag', $container));
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
