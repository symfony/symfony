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
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\DependencyInjection\DependencyInjectionExtension;
use Symfony\Component\Form\FormTypeGuesserChain;
use Symfony\Component\Form\FormTypeGuesserInterface;

class DependencyInjectionExtensionTest extends TestCase
{
    public function testGetTypeExtensions()
    {
        $container = $this->createContainerMock();
        $container->expects($this->never())->method('get');

        $typeExtension1 = new TestTypeExtension();
        $typeExtension2 = new TestTypeExtension();
        $typeExtension3 = new OtherTypeExtension();
        $typeExtension4 = new MultipleTypesTypeExtension();

        $extensions = array(
            'test' => new \ArrayIterator(array($typeExtension1, $typeExtension2, $typeExtension4)),
            'other' => new \ArrayIterator(array($typeExtension3, $typeExtension4)),
        );

        $extension = new DependencyInjectionExtension($container, $extensions, array());

        $this->assertTrue($extension->hasTypeExtensions('test'));
        $this->assertTrue($extension->hasTypeExtensions('other'));
        $this->assertFalse($extension->hasTypeExtensions('unknown'));
        $this->assertSame(array($typeExtension1, $typeExtension2, $typeExtension4), $extension->getTypeExtensions('test'));
        $this->assertSame(array($typeExtension3, $typeExtension4), $extension->getTypeExtensions('other'));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\InvalidArgumentException
     */
    public function testThrowExceptionForInvalidExtendedType()
    {
        $container = $this->getMockBuilder('Psr\Container\ContainerInterface')->getMock();
        $container->expects($this->never())->method('get');

        $extensions = array(
            'unmatched' => new \ArrayIterator(array(new TestTypeExtension())),
        );

        $extension = new DependencyInjectionExtension($container, $extensions, array());

        $extension->getTypeExtensions('unmatched');
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
}

class TestTypeExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return array('test');
    }
}

class OtherTypeExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return array('other');
    }
}

class MultipleTypesTypeExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        yield 'test';
        yield 'other';
    }
}
