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
        $typeExtension1 = new DummyExtension('test');
        $typeExtension2 = new DummyExtension('test');
        $typeExtension3 = new DummyExtension('other');

        $extensions = [
            'test' => new \ArrayIterator([$typeExtension1, $typeExtension2]),
            'other' => new \ArrayIterator([$typeExtension3]),
        ];

        $extension = new DependencyInjectionExtension(new ContainerBuilder(), $extensions, []);

        $this->assertTrue($extension->hasTypeExtensions('test'));
        $this->assertTrue($extension->hasTypeExtensions('other'));
        $this->assertFalse($extension->hasTypeExtensions('unknown'));
        $this->assertSame([$typeExtension1, $typeExtension2], $extension->getTypeExtensions('test'));
        $this->assertSame([$typeExtension3], $extension->getTypeExtensions('other'));
    }

    public function testThrowExceptionForInvalidExtendedType()
    {
        $this->expectException('Symfony\Component\Form\Exception\InvalidArgumentException');
        $extensions = [
            'test' => new \ArrayIterator([new DummyExtension('unmatched')]),
        ];

        $extension = new DependencyInjectionExtension(new ContainerBuilder(), $extensions, []);

        $extension->getTypeExtensions('test');
    }

    /**
     * @group legacy
     * @expectedDeprecation Passing four arguments to the Symfony\Component\Form\Extension\DependencyInjection\DependencyInjectionExtension::__construct() method is deprecated since Symfony 3.3 and will be disallowed in Symfony 4.0. The new constructor only accepts three arguments.
     */
    public function testLegacyGetTypeExtensions()
    {
        $container = new ContainerBuilder();

        $typeExtension1 = new DummyExtension('test');
        $typeExtension2 = new DummyExtension('test');
        $typeExtension3 = new DummyExtension('other');

        $container->set('extension1', $typeExtension1);
        $container->set('extension2', $typeExtension2);
        $container->set('extension3', $typeExtension3);

        $extension = new DependencyInjectionExtension($container, [], ['test' => ['extension1', 'extension2'], 'other' => ['extension3']], []);

        $this->assertTrue($extension->hasTypeExtensions('test'));
        $this->assertFalse($extension->hasTypeExtensions('unknown'));
        $this->assertSame([$typeExtension1, $typeExtension2], $extension->getTypeExtensions('test'));
    }

    /**
     * @group legacy
     * @expectedDeprecation Passing four arguments to the Symfony\Component\Form\Extension\DependencyInjection\DependencyInjectionExtension::__construct() method is deprecated since Symfony 3.3 and will be disallowed in Symfony 4.0. The new constructor only accepts three arguments.
     */
    public function testLegacyThrowExceptionForInvalidExtendedType()
    {
        $this->expectException('Symfony\Component\Form\Exception\InvalidArgumentException');
        $formTypeExtension = new DummyExtension('unmatched');

        $container = new ContainerBuilder();
        $container->set('extension', $formTypeExtension);

        $extension = new DependencyInjectionExtension($container, [], ['test' => ['extension']], []);

        $extensions = $extension->getTypeExtensions('test');

        $this->assertCount(1, $extensions);
        $this->assertSame($formTypeExtension, $extensions[0]);
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

    /**
     * @group legacy
     */
    public function testLegacyGetTypeGuesser()
    {
        $container = new ContainerBuilder();
        $container->set('foo', new DummyTypeGuesser());

        $extension = new DependencyInjectionExtension($container, [], [], ['foo']);

        $this->assertInstanceOf(FormTypeGuesserChain::class, $extension->getTypeGuesser());
    }

    /**
     * @group legacy
     */
    public function testLegacyGetTypeGuesserReturnsNullWhenNoTypeGuessersHaveBeenConfigured()
    {
        $extension = new DependencyInjectionExtension(new ContainerBuilder(), [], [], []);

        $this->assertNull($extension->getTypeGuesser());
    }
}

class DummyExtension extends AbstractTypeExtension
{
    private $extendedType;

    public function __construct($extendedType)
    {
        $this->extendedType = $extendedType;
    }

    public function getExtendedType()
    {
        return $this->extendedType;
    }
}

class DummyTypeGuesser implements FormTypeGuesserInterface
{
    public function guessType($class, $property)
    {
    }

    public function guessRequired($class, $property)
    {
    }

    public function guessMaxLength($class, $property)
    {
    }

    public function guessPattern($class, $property)
    {
    }
}
