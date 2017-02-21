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
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\SerializerPass;

/**
 * Tests for the SerializerPass class.
 *
 * @author Javier Lopez <f12loalf@gmail.com>
 */
class SerializerPassTest extends TestCase
{
    public function testThrowExceptionWhenNoNormalizers()
    {
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')->setMethods(array('hasDefinition', 'findTaggedServiceIds'))->getMock();

        $container->expects($this->once())
            ->method('hasDefinition')
            ->with('serializer')
            ->will($this->returnValue(true));

        $container->expects($this->once())
            ->method('findTaggedServiceIds')
            ->with('serializer.normalizer')
            ->will($this->returnValue(array()));

        $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}('RuntimeException');

        $serializerPass = new SerializerPass();
        $serializerPass->process($container);
    }

    public function testThrowExceptionWhenNoEncoders()
    {
        $definition = $this->getMockBuilder('Symfony\Component\DependencyInjection\Definition')->getMock();
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')->setMethods(array('hasDefinition', 'findTaggedServiceIds', 'getDefinition'))->getMock();

        $container->expects($this->once())
            ->method('hasDefinition')
            ->with('serializer')
            ->will($this->returnValue(true));

        $container->expects($this->any())
            ->method('findTaggedServiceIds')
            ->will($this->onConsecutiveCalls(
                    array('n' => array('serializer.normalizer')),
                    array()
              ));

        $container->expects($this->once())
            ->method('getDefinition')
            ->will($this->returnValue($definition));

        $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}('RuntimeException');

        $serializerPass = new SerializerPass();
        $serializerPass->process($container);
    }

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

        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')->setMethods(array('findTaggedServiceIds'))->getMock();

        $container->expects($this->any())
            ->method('findTaggedServiceIds')
            ->will($this->returnValue($services));

        $serializerPass = new SerializerPass();

        $method = new \ReflectionMethod(
          'Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\SerializerPass',
          'findAndSortTaggedServices'
        );
        $method->setAccessible(true);

        $actual = $method->invoke($serializerPass, 'tag', $container);

        $this->assertEquals($expected, $actual);
    }
}
