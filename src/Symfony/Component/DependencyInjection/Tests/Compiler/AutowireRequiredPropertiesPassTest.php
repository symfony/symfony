<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\DependencyInjection\Compiler\AutowireRequiredPropertiesPass;
use Symfony\Component\DependencyInjection\Compiler\ResolveClassPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\TypedReference;

require_once __DIR__.'/../Fixtures/includes/autowiring_classes.php';

class AutowireRequiredPropertiesPassTest extends TestCase
{
    public function testAttribute()
    {
        $container = new ContainerBuilder();
        $container->register(Foo::class);

        $container->register('property_injection', AutowireProperty::class)
            ->setAutowired(true);

        (new ResolveClassPass())->process($container);
        (new AutowireRequiredPropertiesPass())->process($container);

        $properties = $container->getDefinition('property_injection')->getProperties();

        $this->assertArrayHasKey('foo', $properties);
        $this->assertEquals(Foo::class, (string) $properties['foo']);
    }

    public function testTargetAttributeIsPropagatedOnRequiredProperty()
    {
        $container = new ContainerBuilder();
        $container->register(Foo::class);

        $container->register('property_injection_with_target', AutowirePropertyWithTarget::class)
            ->setAutowired(true);

        (new ResolveClassPass())->process($container);
        (new AutowireRequiredPropertiesPass())->process($container);

        $properties = $container->getDefinition('property_injection_with_target')->getProperties();

        $this->assertArrayHasKey('foo', $properties);
        $this->assertInstanceOf(TypedReference::class, $properties['foo']);
        $attributes = array_values(array_filter($properties['foo']->getAttributes(), static fn ($attribute) => $attribute instanceof Target));
        $this->assertCount(1, $attributes);
        $this->assertSame('foo.target', $attributes[0]->name);
    }

    public function testAttributeWithReadonlyProperty()
    {
        $container = new ContainerBuilder();
        $container->register(Foo::class);
        $container->register('property_injection', AutowireReadonlyProperty::class)
            ->setAutowired(true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot autowire non-public(set) property "Symfony\Component\DependencyInjection\Tests\Compiler\AutowireReadonlyProperty::$foo" with #[Symfony\Contracts\Service\Attribute\Required].');

        (new AutowireRequiredPropertiesPass())->process($container);
    }

    public function testAttributeWithPrivateProperty()
    {
        $container = new ContainerBuilder();
        $container->register(Foo::class);
        $container->register('property_injection', AutowirePrivateProperty::class)
            ->setAutowired(true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot autowire non-public(set) property "Symfony\Component\DependencyInjection\Tests\Compiler\AutowirePrivateProperty::$foo" with #[Symfony\Contracts\Service\Attribute\Required].');

        (new AutowireRequiredPropertiesPass())->process($container);
    }
}
