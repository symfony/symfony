<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\PropertyInfo\DependencyInjection\PropertyInfoPass;

class PropertyInfoPassTest extends TestCase
{
    /**
     * @dataProvider provideTags
     */
    public function testServicesAreOrderedAccordingToPriority($index, $tag)
    {
        $container = new ContainerBuilder();

        $definition = $container->register('property_info')->setArguments(array(null, null, null, null));
        $container->register('n2')->addTag($tag, array('priority' => 100));
        $container->register('n1')->addTag($tag, array('priority' => 200));
        $container->register('n3')->addTag($tag);

        $propertyInfoPass = new PropertyInfoPass();
        $propertyInfoPass->process($container);

        $expected = new IteratorArgument(array(
            new Reference('n1'),
            new Reference('n2'),
            new Reference('n3'),
        ));
        $this->assertEquals($expected, $definition->getArgument($index));
    }

    public function provideTags()
    {
        return array(
            array(0, 'property_info.list_extractor'),
            array(1, 'property_info.type_extractor'),
            array(2, 'property_info.description_extractor'),
            array(3, 'property_info.access_extractor'),
        );
    }

    public function testReturningEmptyArrayWhenNoService()
    {
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')->setMethods(array('findTaggedServiceIds'))->getMock();

        $container
            ->expects($this->any())
            ->method('findTaggedServiceIds')
            ->will($this->returnValue(array()))
        ;

        $propertyInfoPass = new PropertyInfoPass();

        $method = new \ReflectionMethod(
            'Symfony\Component\PropertyInfo\DependencyInjection\PropertyInfoPass',
            'findAndSortTaggedServices'
        );
        $method->setAccessible(true);

        $actual = $method->invoke($propertyInfoPass, 'tag', $container);

        $this->assertEquals(array(), $actual);
    }
}
