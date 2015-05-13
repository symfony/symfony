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

use Symfony\Component\DependencyInjection\Definition;
use Symfony\Bundle\TwigBundle\DependencyInjection\Compiler\TwigLoaderPass;

class TwigLoaderPassTest extends \PHPUnit_Framework_TestCase
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
        $this->builder = $this->getMock(
            'Symfony\Component\DependencyInjection\ContainerBuilder',
            array('hasDefinition', 'findTaggedServiceIds', 'setAlias', 'getDefinition')
        );
        $this->chainLoader = new Definition('loader');
        $this->pass = new TwigLoaderPass();
    }

    public function testMapperPassWithOneTaggedLoaders()
    {
        $serviceIds = array(
            'test_loader_1' => array(
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
            ),
            'test_loader_2' => array(
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
