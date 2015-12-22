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

use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\PropertyInfoPass;
use Symfony\Component\DependencyInjection\Definition;
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

        $container
            ->expects($this->any())
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

        $container
            ->expects($this->any())
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
        $container = $this->getMock(
            'Symfony\Component\DependencyInjection\ContainerBuilder',
            array('register', 'hasDefinition', 'getDefinition')
        );

        $container
            ->expects($this->exactly(2))
            ->method('hasDefinition')
            ->will($this->returnValue(true))
        ;

        $serializerExtractorDefinition = new Definition('Symfony\Component\PropertyInfo\Extractor\SerializerExtractor');
        $container
            ->expects($this->exactly(1))
            ->method('register')
            ->will($this->returnValue($serializerExtractorDefinition))
        ;

        $propertyInfoDefinition = new Definition(
            'Symfony\Component\PropertyInfo\PropertyInfoExtractor',
            array(null, null, null, null)
        );
        $container
            ->expects($this->exactly(1))
            ->method('getDefinition')
            ->will($this->returnValue($propertyInfoDefinition))
        ;

        $propertyInfoPass = new PropertyInfoPass();
        $propertyInfoPass->process($container);

        $this->assertEquals('serializer.mapping.class_metadata_factory', $serializerExtractorDefinition->getArgument(0)->__toString());
        $this->assertFalse($serializerExtractorDefinition->isPublic());

        $tag = $serializerExtractorDefinition->getTag('property_info.list_extractor');
        $this->assertEquals(array('priority' => -999), $tag[0]);
    }
}
