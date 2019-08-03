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
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\DependencyInjection\DependencyInjectionExtension;
use Symfony\Component\Form\FormTypeGuesserChain;
use Symfony\Component\Form\FormTypeGuesserInterface;

class DependencyInjectionExtensionTest extends TestCase
{
    public function testGetTypeExtensions()
    {
        $typeExtension1 = new TestTypeExtension();
        $typeExtension2 = new TestTypeExtension();
        $typeExtension3 = new OtherTypeExtension();
        $typeExtension4 = new MultipleTypesTypeExtension();

        $extensions = [
            'test' => new \ArrayIterator([$typeExtension1, $typeExtension2, $typeExtension4]),
            'other' => new \ArrayIterator([$typeExtension3, $typeExtension4]),
        ];

        $extension = new DependencyInjectionExtension(new ContainerBuilder(), $extensions, []);

        $this->assertTrue($extension->hasTypeExtensions('test'));
        $this->assertTrue($extension->hasTypeExtensions('other'));
        $this->assertFalse($extension->hasTypeExtensions('unknown'));
        $this->assertSame([$typeExtension1, $typeExtension2, $typeExtension4], $extension->getTypeExtensions('test'));
        $this->assertSame([$typeExtension3, $typeExtension4], $extension->getTypeExtensions('other'));
    }

    public function testThrowExceptionForInvalidExtendedType()
    {
        $this->expectException('Symfony\Component\Form\Exception\InvalidArgumentException');
        $extensions = [
            'unmatched' => new \ArrayIterator([new TestTypeExtension()]),
        ];

        $extension = new DependencyInjectionExtension(new ContainerBuilder(), $extensions, []);

        $extension->getTypeExtensions('unmatched');
    }

    public function testGetTypeGuesser()
    {
        $extension = new DependencyInjectionExtension(new ContainerBuilder(), [], [$this->getMockBuilder(FormTypeGuesserInterface::class)->getMock()]);

        $this->assertInstanceOf(FormTypeGuesserChain::class, $extension->getTypeGuesser());
    }

    public function testGetTypeGuesserReturnsNullWhenNoTypeGuessersHaveBeenConfigured()
    {
        $extension = new DependencyInjectionExtension(new ContainerBuilder(), [], []);

        $this->assertNull($extension->getTypeGuesser());
    }
}

class TestTypeExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return ['test'];
    }
}

class OtherTypeExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return ['other'];
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
