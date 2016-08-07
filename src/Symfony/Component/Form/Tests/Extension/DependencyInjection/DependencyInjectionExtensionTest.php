<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\DependencyInjection;

use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\Form\Extension\DependencyInjection\DependencyInjectionExtension;

class DependencyInjectionExtensionTest extends \PHPUnit_Framework_TestCase
{
    public function testGetTypeExtensions()
    {
        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');

        $typeExtension1 = $this->getMock('Symfony\Component\Form\FormTypeExtensionInterface');
        $typeExtension1->expects($this->any())
            ->method('getExtendedType')
            ->willReturn('test');
        $typeExtension2 = $this->getMock('Symfony\Component\Form\FormTypeExtensionInterface');
        $typeExtension2->expects($this->any())
            ->method('getExtendedType')
            ->willReturn('test');
        $typeExtension3 = $this->getMock('Symfony\Component\Form\FormTypeExtensionInterface');
        $typeExtension3->expects($this->any())
            ->method('getExtendedType')
            ->willReturn('other');

        $services = array(
            'extension1' => $typeExtension1,
            'extension2' => $typeExtension2,
            'extension3' => $typeExtension3,
        );

        $container->expects($this->any())
            ->method('get')
            ->willReturnCallback(function ($id) use ($services) {
                if (isset($services[$id])) {
                    return $services[$id];
                }

                throw new ServiceNotFoundException($id);
            });

        $extension = new DependencyInjectionExtension($container, array(), array('test' => array('extension1', 'extension2'), 'other' => array('extension3')), array());

        $this->assertTrue($extension->hasTypeExtensions('test'));
        $this->assertFalse($extension->hasTypeExtensions('unknown'));
        $this->assertSame(array($typeExtension1, $typeExtension2), $extension->getTypeExtensions('test'));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\InvalidArgumentException
     */
    public function testThrowExceptionForInvalidExtendedType()
    {
        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');

        $typeExtension = $this->getMock('Symfony\Component\Form\FormTypeExtensionInterface');
        $typeExtension->expects($this->any())
            ->method('getExtendedType')
            ->willReturn('unmatched');

        $container->expects($this->any())
            ->method('get')
            ->with('extension')
            ->willReturn($typeExtension);

        $extension = new DependencyInjectionExtension($container, array(), array('test' => array('extension')), array());

        $extension->getTypeExtensions('test');
    }
}
