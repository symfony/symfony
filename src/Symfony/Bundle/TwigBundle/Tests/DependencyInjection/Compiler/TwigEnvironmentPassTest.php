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
use Symfony\Component\DependencyInjection\Definition;

class TwigEnvironmentPassTest extends TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $builder;
    /**
     * @var Definition
     */
    private $definition;
    /**
     * @var TwigEnvironmentPass
     */
    private $pass;

    protected function setUp()
    {
        $this->builder = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')->setMethods(array('hasDefinition', 'findTaggedServiceIds', 'setAlias', 'getDefinition'))->getMock();
        $this->definition = new Definition('twig');
        $this->pass = new TwigEnvironmentPass();
    }

    public function testPassWithTwoExtensionsWithPriority()
    {
        $serviceIds = array(
            'test_extension_1' => array(
                array('priority' => 100),
            ),
            'test_extension_2' => array(
                array('priority' => 200),
            ),
        );

        $this->builder->expects($this->once())
            ->method('hasDefinition')
            ->with('twig')
            ->will($this->returnValue(true));
        $this->builder->expects($this->once())
            ->method('findTaggedServiceIds')
            ->with('twig.extension')
            ->will($this->returnValue($serviceIds));
        $this->builder->expects($this->once())
            ->method('getDefinition')
            ->with('twig')
            ->will($this->returnValue($this->definition));

        $this->pass->process($this->builder);
        $calls = $this->definition->getMethodCalls();
        $this->assertCount(2, $calls);
        $this->assertEquals('addExtension', $calls[0][0]);
        $this->assertEquals('addExtension', $calls[1][0]);
        $this->assertEquals('test_extension_2', (string) $calls[0][1][0]);
        $this->assertEquals('test_extension_1', (string) $calls[1][1][0]);
    }
}
