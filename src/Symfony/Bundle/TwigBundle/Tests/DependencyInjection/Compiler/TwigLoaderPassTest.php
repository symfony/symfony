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
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Bundle\TwigBundle\DependencyInjection\Compiler\TwigLoaderPass;

class TwigLoaderPassTest extends TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $builder;
    /**
     * @var Definition
     */
    private $chainLoader;
    /**
     * @var TwigLoaderPass
     */
    private $pass;

    protected function setUp()
    {
        $this->builder = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')->setMethods(array('hasDefinition', 'findTaggedServiceIds', 'setAlias', 'getDefinition'))->getMock();
        $this->chainLoader = new Definition('loader');
        $this->pass = new TwigLoaderPass();
    }

    public function testMapperPassWithOneTaggedLoaders()
    {
        $serviceIds = array(
            'test_loader_1' => array(
                array(),
            ),
        );

        $this->builder->expects($this->once())
            ->method('hasDefinition')
            ->with('twig')
            ->will($this->returnValue(true));
        $this->builder->expects($this->once())
            ->method('findTaggedServiceIds')
            ->with('twig.loader')
            ->will($this->returnValue($serviceIds));
        $this->builder->expects($this->once())
            ->method('setAlias')
            ->with('twig.loader', 'test_loader_1');

        $this->pass->process($this->builder);
    }

    public function testMapperPassWithTwoTaggedLoaders()
    {
        $serviceIds = array(
            'test_loader_1' => array(
                array(),
            ),
            'test_loader_2' => array(
                array(),
            ),
        );

        $this->builder->expects($this->once())
            ->method('hasDefinition')
            ->with('twig')
            ->will($this->returnValue(true));
        $this->builder->expects($this->once())
            ->method('findTaggedServiceIds')
            ->with('twig.loader')
            ->will($this->returnValue($serviceIds));
        $this->builder->expects($this->once())
            ->method('getDefinition')
            ->with('twig.loader.chain')
            ->will($this->returnValue($this->chainLoader));
        $this->builder->expects($this->once())
            ->method('setAlias')
            ->with('twig.loader', 'twig.loader.chain');

        $this->pass->process($this->builder);
        $calls = $this->chainLoader->getMethodCalls();
        $this->assertCount(2, $calls);
        $this->assertEquals('addLoader', $calls[0][0]);
        $this->assertEquals('addLoader', $calls[1][0]);
        $this->assertEquals('test_loader_1', (string) $calls[0][1][0]);
        $this->assertEquals('test_loader_2', (string) $calls[1][1][0]);
    }

    public function testMapperPassWithTwoTaggedLoadersWithPriority()
    {
        $serviceIds = array(
            'test_loader_1' => array(
                array('priority' => 100),
            ),
            'test_loader_2' => array(
                array('priority' => 200),
            ),
        );

        $this->builder->expects($this->once())
            ->method('hasDefinition')
            ->with('twig')
            ->will($this->returnValue(true));
        $this->builder->expects($this->once())
            ->method('findTaggedServiceIds')
            ->with('twig.loader')
            ->will($this->returnValue($serviceIds));
        $this->builder->expects($this->once())
            ->method('getDefinition')
            ->with('twig.loader.chain')
            ->will($this->returnValue($this->chainLoader));
        $this->builder->expects($this->once())
            ->method('setAlias')
            ->with('twig.loader', 'twig.loader.chain');

        $this->pass->process($this->builder);
        $calls = $this->chainLoader->getMethodCalls();
        $this->assertCount(2, $calls);
        $this->assertEquals('addLoader', $calls[0][0]);
        $this->assertEquals('addLoader', $calls[1][0]);
        $this->assertEquals('test_loader_2', (string) $calls[0][1][0]);
        $this->assertEquals('test_loader_1', (string) $calls[1][1][0]);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\LogicException
     */
    public function testMapperPassWithZeroTaggedLoaders()
    {
        $this->builder->expects($this->once())
            ->method('hasDefinition')
            ->with('twig')
            ->will($this->returnValue(true));
        $this->builder->expects($this->once())
            ->method('findTaggedServiceIds')
            ->with('twig.loader')
            ->will($this->returnValue(array()));

        $this->pass->process($this->builder);
    }
}
