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

use Prophecy\Argument;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\PropertyInfoPass;
use Symfony\Component\DependencyInjection\Reference;

class PropertyInfoPassTest extends \PHPUnit_Framework_TestCase
{
    public function testServicesAreOrderedAccordingToPriority()
    {
        $services = array(
            'n3' => array('tag' => array()),
            'n1' => array('tag' => array('priority' => 200)),
            'n2' => array('tag' => array('priority' => 100)),
        );

        $expected = array(
            new Reference('n1'),
            new Reference('n2'),
            new Reference('n3'),
        );

        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerBuilder', array('findTaggedServiceIds'));

        $container->expects($this->any())
            ->method('findTaggedServiceIds')
            ->will($this->returnValue($services));

        $propertyInfoPass = new PropertyInfoPass();

        $method = new \ReflectionMethod(
            'Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\PropertyInfoPass',
            'findAndSortTaggedServices'
        );
        $method->setAccessible(true);

        $actual = $method->invoke($propertyInfoPass, 'tag', $container);

        $this->assertEquals($expected, $actual);
    }

    public function testReturningEmptyArrayWhenNoService()
    {
        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerBuilder', array('findTaggedServiceIds'));

        $container->expects($this->any())
            ->method('findTaggedServiceIds')
            ->will($this->returnValue(array()))
        ;

        $propertyInfoPass = new PropertyInfoPass();

        $method = new \ReflectionMethod(
            'Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\PropertyInfoPass',
            'findAndSortTaggedServices'
        );
        $method->setAccessible(true);

        $actual = $method->invoke($propertyInfoPass, 'tag', $container);

        $this->assertEquals(array(), $actual);
    }

    public function testRegisterSerializerExtractor()
    {
        $serializerExtractorDefinitionProphecy = $this->prophesize('Symfony\Component\DependencyInjection\Definition');
        $serializerExtractorDefinitionProphecy->addArgument(Argument::type('Symfony\Component\DependencyInjection\Reference'))->shouldBeCalled();
        $serializerExtractorDefinitionProphecy->addTag('property_info.list_extractor', array('priority' => -999))->shouldBeCalled();

        $propertyInfoDefinitionProphecy = $this->prophesize('Symfony\Component\DependencyInjection\Definition');

        $containerProphecy = $this->prophesize('Symfony\Component\DependencyInjection\ContainerBuilder');
        $containerProphecy->hasDefinition('property_info')->willReturn(true)->shouldBeCalled();
        $containerProphecy->hasDefinition('serializer.mapping.class_metadata_factory')->willReturn(true)->shouldBeCalled();
        $containerProphecy->getDefinition('property_info')->willReturn($propertyInfoDefinitionProphecy->reveal())->shouldBeCalled();
        $containerProphecy->findTaggedServiceIds(Argument::type('string'))->willReturn(array());

        $containerProphecy->register('property_info.serializer_extractor', 'Symfony\Component\PropertyInfo\Extractor\SerializerExtractor')->willReturn($serializerExtractorDefinitionProphecy->reveal());

        $propertyInfoPass = new PropertyInfoPass();
        $propertyInfoPass->process($containerProphecy->reveal());
    }
}
