<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\TwigBundle\DependencyInjection\Compiler\TwigEnvironmentPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class TwigEnvironmentPassTest extends TestCase
{
    public function testTwigBridgeExtensionsAreRegisteredFirst()
    {
        $twigDefinition = new Definition('twig');

        $containerBuilderMock = $this->getMockBuilder(ContainerBuilder::class)
            ->setMethods(array('hasDefinition', 'get', 'findTaggedServiceIds', 'getDefinition'))
            ->getMock();
        $containerBuilderMock
            ->expects($this->once())
            ->method('hasDefinition')
            ->with('twig')
            ->will($this->returnValue(true));
        $containerBuilderMock
            ->expects($this->once())
            ->method('findTaggedServiceIds')
            ->with('twig.extension')
            ->will($this->returnValue(array(
                'other_extension' => array(
                    array()
                ),
                'twig_bridge_extension' => array(
                    array()
                )
            )));

        $otherExtensionDefinitionMock = $this->getMockBuilder(Definition::class)
            ->setMethods(array('getClass'))
            ->getMock();
        $otherExtensionDefinitionMock
            ->expects($this->once())
            ->method('getClass')
            ->will($this->returnValue('Foo\\Bar'));

        $twigExtensionDefinitionMock = $this->getMockBuilder(Definition::class)
            ->setMethods(array('getClass'))
            ->getMock();
        $twigExtensionDefinitionMock
            ->expects($this->once())
            ->method('getClass')
            ->will($this->returnValue('Symfony\\Bridge\\Twig\\Extension\\Foo'));

        $containerBuilderMock
            ->expects($this->exactly(3))
            ->method('getDefinition')
            ->withConsecutive(array('twig'), array('other_extension'), array('twig_bridge_extension'))
            ->willReturnOnConsecutiveCalls(
                $this->returnValue($twigDefinition),
                $this->returnValue($otherExtensionDefinitionMock),
                $this->returnValue($twigExtensionDefinitionMock)
            );

        $twigEnvironmentPass = new TwigEnvironmentPass();
        $twigEnvironmentPass->process($containerBuilderMock);

        $methodCalls = $twigDefinition->getMethodCalls();
        $this->assertCount(2, $methodCalls);

        $twigBridgeExtensionReference = $methodCalls[0][1][0];
        $this->assertInstanceOf(Reference::class, $twigBridgeExtensionReference);
        /* @var Reference $twigBridgeExtensionReference */
        $this->assertEquals('twig_bridge_extension', $twigBridgeExtensionReference->__toString());

        $otherExtensionReference = $methodCalls[1][1][0];
        $this->assertInstanceOf(Reference::class, $otherExtensionReference);
        /* @var Reference $otherExtensionReference */
        $this->assertEquals('other_extension', $otherExtensionReference->__toString());
    }
}
