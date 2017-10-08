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
use Symfony\Component\Form\Extension\DependencyInjection\DependencyInjectionExtension;
use Symfony\Component\Form\FormTypeGuesserChain;
use Symfony\Component\Form\FormTypeGuesserInterface;

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

    public function testGetTypeGuesser()
    {
        $container = $this->createContainerMock();
        $extension = new DependencyInjectionExtension($container, array(), array($this->getMockBuilder(FormTypeGuesserInterface::class)->getMock()));

        $this->assertInstanceOf(FormTypeGuesserChain::class, $extension->getTypeGuesser());
    }

    public function testGetTypeGuesserReturnsNullWhenNoTypeGuessersHaveBeenConfigured()
    {
        $container = $this->createContainerMock();
        $extension = new DependencyInjectionExtension($container, array(), array());

        $this->assertNull($extension->getTypeGuesser());
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
