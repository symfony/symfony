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

        $container->expects($this->any())
            ->method('getDefinition')
            ->will($this->returnValue($definition));

        $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}('RuntimeException');

        $serializerPass = new SerializerPass();
        $serializerPass->process($container);
    }

    public function testServicesAreOrderedAccordingToPriority()
    {
        $container = new ContainerBuilder();

        $definition = $container->register('serializer')->setArguments(array(null, null));
        $container->register('n2')->addTag('serializer.normalizer', array('priority' => 100))->addTag('serializer.encoder', array('priority' => 100));
        $container->register('n1')->addTag('serializer.normalizer', array('priority' => 200))->addTag('serializer.encoder', array('priority' => 200));
        $container->register('n3')->addTag('serializer.normalizer')->addTag('serializer.encoder');

        $serializerPass = new SerializerPass();
        $serializerPass->process($container);

        $expected = array(
            new Reference('n1'),
            new Reference('n2'),
            new Reference('n3'),
        );
        $this->assertEquals($expected, $definition->getArgument(0));
        $this->assertEquals($expected, $definition->getArgument(1));
    }
}
