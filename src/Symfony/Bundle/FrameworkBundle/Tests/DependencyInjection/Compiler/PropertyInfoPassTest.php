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
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\PropertyInfoPass;
use Symfony\Component\DependencyInjection\Reference;

class PropertyInfoPassTest extends TestCase
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

        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')->setMethods(array('findTaggedServiceIds'))->getMock();

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
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')->setMethods(array('findTaggedServiceIds'))->getMock();

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
}
