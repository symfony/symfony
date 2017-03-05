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

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\Form\Extension\DependencyInjection\DependencyInjectionExtension;

class DependencyInjectionExtensionTest extends TestCase
{
    public function testGetTypeExtensions()
    {
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();

        $typeExtension1 = $this->getMockBuilder('Symfony\Component\Form\FormTypeExtensionInterface')->getMock();
        $typeExtension1->expects($this->any())
            ->method('getExtendedType')
            ->willReturn('test');
        $typeExtension2 = $this->getMockBuilder('Symfony\Component\Form\FormTypeExtensionInterface')->getMock();
        $typeExtension2->expects($this->any())
            ->method('getExtendedType')
            ->willReturn('test');
        $typeExtension3 = $this->getMockBuilder('Symfony\Component\Form\FormTypeExtensionInterface')->getMock();
        $typeExtension3->expects($this->any())
            ->method('getExtendedType')
            ->willReturn('other');

        $services = array(
            'extension1' => $typeExtension1 = $this->createFormTypeExtensionMock('test'),
            'extension2' => $typeExtension2 = $this->createFormTypeExtensionMock('test'),
            'extension3' => $typeExtension3 = $this->createFormTypeExtensionMock('other'),
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
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();

        $typeExtension = $this->getMockBuilder('Symfony\Component\Form\FormTypeExtensionInterface')->getMock();
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

    public function testGetTypeGuesser()
    {
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $container
            ->expects($this->once())
            ->method('get')
            ->with('foo')
            ->willReturn($this->getMockBuilder('Symfony\Component\Form\FormTypeGuesserInterface')->getMock());
        $extension = new DependencyInjectionExtension($container, array(), array(), array('foo'));

        $this->assertInstanceOf('Symfony\Component\Form\FormTypeGuesserChain', $extension->getTypeGuesser());
    }

    public function testGetTypeGuesserReturnsNullWhenNoTypeGuessersHaveBeenConfigured()
    {
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $extension = new DependencyInjectionExtension($container, array(), array(), array());

        $this->assertNull($extension->getTypeGuesser());
    }

    private function createFormTypeExtensionMock($extendedType)
    {
        $extension = $this->getMockBuilder('Symfony\Component\Form\FormTypeExtensionInterface')->getMock();
        $extension->expects($this->any())->method('getExtendedType')->willReturn($extendedType);

        return $extension;
    }
}
