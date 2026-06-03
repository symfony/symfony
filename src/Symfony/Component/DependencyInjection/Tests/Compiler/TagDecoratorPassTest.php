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
use Symfony\Component\DependencyInjection\Compiler\DecoratorServicePass;
use Symfony\Component\DependencyInjection\Compiler\TagDecoratorPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

class TagDecoratorPassTest extends TestCase
{
    public function testDecorateByTag()
    {
        $container = new ContainerBuilder();

        $container->register('foo', \stdClass::class)->addTag('tag');
        $container->register('bar', \stdClass::class)->addTag('tag');
        $container->register('decorator', \stdClass::class)->addResourceTag('container.tag_decorator', ['decorates_tag' => 'tag']);

        $this->process($container);

        $this->assertFalse($container->has('decorator'));

        $this->assertTrue($container->has('.decorator.foo.decorator'));
        $this->assertTrue($container->has('.decorator.bar.decorator'));

        $this->assertSame('.decorator.foo.decorator', (string) $container->getAlias('foo'));
        $this->assertSame('.decorator.bar.decorator', (string) $container->getAlias('bar'));

        $this->assertTrue($container->has('.decorator.foo.decorator.inner'));
        $this->assertTrue($container->has('.decorator.bar.decorator.inner'));
    }

    public function testDecorateByTagPriority()
    {
        $container = new ContainerBuilder();

        $container->register('foo', \stdClass::class)->addTag('tag');
        $container->register('bar', \stdClass::class)->addTag('tag');
        $container->register('decorator1', \stdClass::class)->addResourceTag('container.tag_decorator', ['decorates_tag' => 'tag', 'priority' => 2]);
        $container->register('decorator2', \stdClass::class)->addResourceTag('container.tag_decorator', ['decorates_tag' => 'tag', 'priority' => 1]);

        $this->process($container);

        $this->assertFalse($container->has('decorator1'));
        $this->assertFalse($container->has('decorator2'));

        $this->assertTrue($container->has('.decorator.foo.decorator1'));
        $this->assertTrue($container->has('.decorator.foo.decorator2'));
        $this->assertTrue($container->has('.decorator.bar.decorator1'));
        $this->assertTrue($container->has('.decorator.bar.decorator2'));

        $this->assertSame('.decorator.foo.decorator2', (string) $container->getAlias('foo'));
        $this->assertSame('.decorator.bar.decorator2', (string) $container->getAlias('bar'));

        $this->assertSame('.decorator.foo.decorator1', (string) $container->getAlias('.decorator.foo.decorator2.inner'));
        $this->assertSame('.decorator.bar.decorator1', (string) $container->getAlias('.decorator.bar.decorator2.inner'));
    }

    public function testDecorateByTagIgnoreOnInvalid()
    {
        $container = new ContainerBuilder();
        $container->register('decorator', \stdClass::class)->addResourceTag('container.tag_decorator', [
            'decorates_tag' => 'non_existent_tag',
            'on_invalid' => ContainerInterface::IGNORE_ON_INVALID_REFERENCE,
        ]);

        $this->process($container);
        $this->assertFalse($container->has('decorator'));
    }

    public function testDecorateByTagNullOnInvalid()
    {
        $container = new ContainerBuilder();
        $container->register('decorator', \stdClass::class)->addResourceTag('container.tag_decorator', [
            'decorates_tag' => 'non_existent_tag',
            'on_invalid' => ContainerInterface::NULL_ON_INVALID_REFERENCE,
        ]);

        $this->process($container);
        $this->assertFalse($container->has('decorator'));
    }

    public function testDecorateByTagExceptionOnInvalid()
    {
        $container = new ContainerBuilder();
        $container->register('decorator', \stdClass::class)->addResourceTag('container.tag_decorator', ['decorates_tag' => 'non_existent_tag']);

        $this->expectException(ServiceNotFoundException::class);
        $this->process($container);
    }

    protected function process(ContainerBuilder $container): void
    {
        (new TagDecoratorPass())->process($container);
        (new DecoratorServicePass())->process($container);
    }
}
