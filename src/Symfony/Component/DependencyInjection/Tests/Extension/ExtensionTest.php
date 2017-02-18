<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Extension;

use PHPUnit\Framework\TestCase;

class ExtensionTest extends TestCase
{
    /**
     * @dataProvider getResolvedEnabledFixtures
     */
    public function testIsConfigEnabledReturnsTheResolvedValue($enabled)
    {
        $pb = $this->getMockBuilder('Symfony\Component\DependencyInjection\ParameterBag\ParameterBag')
            ->setMethods(array('resolveValue'))
            ->getMock()
        ;

        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')
            ->setMethods(array('getParameterBag'))
            ->getMock()
        ;

        $pb->expects($this->once())
            ->method('resolveValue')
            ->with($this->equalTo($enabled))
            ->will($this->returnValue($enabled))
        ;

        $container->expects($this->once())
            ->method('getParameterBag')
            ->will($this->returnValue($pb))
        ;

        $extension = $this->getMockBuilder('Symfony\Component\DependencyInjection\Extension\Extension')
            ->setMethods(array())
            ->getMockForAbstractClass()
        ;

        $r = new \ReflectionMethod('Symfony\Component\DependencyInjection\Extension\Extension', 'isConfigEnabled');
        $r->setAccessible(true);

        $r->invoke($extension, $container, array('enabled' => $enabled));
    }

    public function getResolvedEnabledFixtures()
    {
        return array(
            array(true),
            array(false),
        );
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessage The config array has no 'enabled' key.
     */
    public function testIsConfigEnabledOnNonEnableableConfig()
    {
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')
            ->getMock()
        ;

        $extension = $this->getMockBuilder('Symfony\Component\DependencyInjection\Extension\Extension')
            ->setMethods(array())
            ->getMockForAbstractClass()
        ;

        $r = new \ReflectionMethod('Symfony\Component\DependencyInjection\Extension\Extension', 'isConfigEnabled');
        $r->setAccessible(true);

        $r->invoke($extension, $container, array());
    }
}
