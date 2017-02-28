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
        $container = $this->createContainerMock();
        $container->expects($this->never())->method('get');

        $typeExtension1 = $this->createFormTypeExtensionMock('test');
        $typeExtension2 = $this->createFormTypeExtensionMock('test');
        $typeExtension3 = $this->createFormTypeExtensionMock('other');

        $extensions = array(
            'test' => new \ArrayIterator(array($typeExtension1, $typeExtension2)),
            'other' => new \ArrayIterator(array($typeExtension3)),
        );

        $extension = new DependencyInjectionExtension($container, $extensions, array());

        $this->assertTrue($extension->hasTypeExtensions('test'));
        $this->assertTrue($extension->hasTypeExtensions('other'));
        $this->assertFalse($extension->hasTypeExtensions('unknown'));
        $this->assertSame(array($typeExtension1, $typeExtension2), $extension->getTypeExtensions('test'));
        $this->assertSame(array($typeExtension3), $extension->getTypeExtensions('other'));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\InvalidArgumentException
     */
    public function testThrowExceptionForInvalidExtendedType()
    {
        $container = $this->getMockBuilder('Psr\Container\ContainerInterface')->getMock();
        $container->expects($this->never())->method('get');

        $extensions = array(
            'test' => new \ArrayIterator(array($this->createFormTypeExtensionMock('unmatched'))),
        );

        $extension = new DependencyInjectionExtension($container, $extensions, array());

        $extension->getTypeExtensions('test');
    }

    /**
     * @group legacy
     * @expectedDeprecation Passing four arguments to the Symfony\Component\Form\Extension\DependencyInjection\DependencyInjectionExtension::__construct() method is deprecated since Symfony 3.3 and will be disallowed in Symfony 4.0. The new constructor only accepts three arguments.
     */
    public function testLegacyGetTypeExtensions()
    {
        $container = $this->createContainerMock();

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

        $extension = new DependencyInjectionExtension($container, array('test' => array('extension1', 'extension2'), 'other' => array('extension3')), array(), array());

        $this->assertTrue($extension->hasTypeExtensions('test'));
        $this->assertFalse($extension->hasTypeExtensions('unknown'));
        $this->assertSame(array($typeExtension1, $typeExtension2), $extension->getTypeExtensions('test'));
    }

    /**
     * @group legacy
     * @expectedException \Symfony\Component\Form\Exception\InvalidArgumentException
     * @expectedDeprecation Passing four arguments to the Symfony\Component\Form\Extension\DependencyInjection\DependencyInjectionExtension::__construct() method is deprecated since Symfony 3.3 and will be disallowed in Symfony 4.0. The new constructor only accepts three arguments.
     */
    public function testLegacyThrowExceptionForInvalidExtendedType()
    {
        $container = $this->createContainerMock();

        $container->expects($this->any())
            ->method('get')
            ->with('extension')
            ->willReturn($this->createFormTypeExtensionMock('unmatched'));

        $extension = new DependencyInjectionExtension($container, array('test' => array('extension')), array(), array());

        $extension->getTypeExtensions('test');
    }

    private function createContainerMock()
    {
        return $this->getMockBuilder('Psr\Container\ContainerInterface')
            ->setMethods(array('get', 'has'))
            ->getMock();
    }

    private function createFormTypeExtensionMock($extendedType)
    {
        $extension = $this->getMockBuilder('Symfony\Component\Form\FormTypeExtensionInterface')->getMock();
        $extension->expects($this->any())->method('getExtendedType')->willReturn($extendedType);

        return $extension;
    }
}
