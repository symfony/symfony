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
use Symfony\Component\DependencyInjection\Argument\TaggedClassMapArgument;
use Symfony\Component\DependencyInjection\Compiler\ResolveTaggedClassMapArgumentPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Tests\Fixtures\BarTagClass;
use Symfony\Component\DependencyInjection\Tests\Fixtures\BazTagClass;
use Symfony\Component\DependencyInjection\Tests\Fixtures\FooTagClass;
use Symfony\Component\DependencyInjection\Tests\Fixtures\QuxTagClass;

class ResolveTaggedClassMapArgumentPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $container->register(BarTagClass::class, BarTagClass::class)->addResourceTag('my_tag', ['key' => 'bar']);
        $container->register(FooTagClass::class, FooTagClass::class)->addResourceTag('my_tag', ['key' => 'foo']);
        $container->register('service', 'stdClass')->setArguments([new TaggedClassMapArgument('my_tag', 'key')]);

        (new ResolveTaggedClassMapArgumentPass())->process($container);

        $expected = new TaggedClassMapArgument('my_tag', 'key');
        $expected->setValues([
            'bar' => BarTagClass::class,
            'foo' => FooTagClass::class,
        ]);
        $this->assertEquals($expected, $container->getDefinition('service')->getArgument(0));
    }

    public function testProcessWithDefaultIndexAttribute()
    {
        $container = new ContainerBuilder();
        $container->register(BarTagClass::class, BarTagClass::class)->addResourceTag('app.my_tag', ['my_tag' => 'bar']);
        $container->register(FooTagClass::class, FooTagClass::class)->addResourceTag('app.my_tag', ['my_tag' => 'foo']);
        $container->register('service', 'stdClass')->setArguments([new TaggedClassMapArgument('app.my_tag')]);

        (new ResolveTaggedClassMapArgumentPass())->process($container);

        $expected = new TaggedClassMapArgument('app.my_tag');
        $expected->setValues([
            'bar' => BarTagClass::class,
            'foo' => FooTagClass::class,
        ]);
        $this->assertEquals($expected, $container->getDefinition('service')->getArgument(0));
    }

    public function testProcessFallsBackToAsTaggedItemIndex()
    {
        $container = new ContainerBuilder();
        $container->register(BarTagClass::class, BarTagClass::class)->setAutoconfigured(true)->addResourceTag('my_tag');
        $container->register(FooTagClass::class, FooTagClass::class)->setAutoconfigured(true)->addResourceTag('my_tag', ['key' => 'foo']);
        $container->register('service', 'stdClass')->setArguments([new TaggedClassMapArgument('my_tag', 'key')]);

        (new ResolveTaggedClassMapArgumentPass())->process($container);

        $expected = new TaggedClassMapArgument('my_tag', 'key');
        $expected->setValues([
            'bar_tag_class' => BarTagClass::class,
            'foo' => FooTagClass::class,
        ]);
        $this->assertEquals($expected, $container->getDefinition('service')->getArgument(0));
    }

    public function testProcessFallsBackToClassName()
    {
        $container = new ContainerBuilder();
        $container->register(BarTagClass::class, BarTagClass::class)->addResourceTag('my_tag');
        $container->register('service', 'stdClass')->setArguments([new TaggedClassMapArgument('my_tag')]);

        (new ResolveTaggedClassMapArgumentPass())->process($container);

        $expected = new TaggedClassMapArgument('my_tag');
        $expected->setValues([
            BarTagClass::class => BarTagClass::class,
        ]);
        $this->assertEquals($expected, $container->getDefinition('service')->getArgument(0));
    }

    public function testProcessResolvesParameters()
    {
        $container = new ContainerBuilder();
        $container->setParameter('my_key', 'bar');
        $container->register(BarTagClass::class, BarTagClass::class)->addResourceTag('my_tag', ['key' => '%my_key%']);
        $container->register('service', 'stdClass')->setArguments([new TaggedClassMapArgument('my_tag', 'key')]);

        (new ResolveTaggedClassMapArgumentPass())->process($container);

        $expected = new TaggedClassMapArgument('my_tag', 'key');
        $expected->setValues([
            'bar' => BarTagClass::class,
        ]);
        $this->assertEquals($expected, $container->getDefinition('service')->getArgument(0));
    }

    public function testProcessWithExclude()
    {
        $container = new ContainerBuilder();
        $container->register(BarTagClass::class, BarTagClass::class)->addResourceTag('my_tag', ['key' => 'bar']);
        $container->register(FooTagClass::class, FooTagClass::class)->addResourceTag('my_tag', ['key' => 'foo']);
        $container->register('service', 'stdClass')->setArguments([new TaggedClassMapArgument('my_tag', 'key', [FooTagClass::class])]);

        (new ResolveTaggedClassMapArgumentPass())->process($container);

        $expected = new TaggedClassMapArgument('my_tag', 'key', [FooTagClass::class]);
        $expected->setValues([
            'bar' => BarTagClass::class,
        ]);
        $this->assertEquals($expected, $container->getDefinition('service')->getArgument(0));
    }

    public function testProcessSortsByPriority()
    {
        $container = new ContainerBuilder();
        $container->register(BarTagClass::class, BarTagClass::class)->addResourceTag('my_tag', ['key' => 'bar']);
        $container->register(FooTagClass::class, FooTagClass::class)->addResourceTag('my_tag', ['key' => 'foo', 'priority' => 10]);
        $container->register('service', 'stdClass')->setArguments([new TaggedClassMapArgument('my_tag', 'key')]);

        (new ResolveTaggedClassMapArgumentPass())->process($container);

        $this->assertSame([
            'foo' => FooTagClass::class,
            'bar' => BarTagClass::class,
        ], $container->getDefinition('service')->getArgument(0)->getValues());
    }

    public function testProcessHighestPriorityWinsOnDuplicateIndex()
    {
        $container = new ContainerBuilder();
        $container->register(BarTagClass::class, BarTagClass::class)->addResourceTag('my_tag', ['key' => 'same']);
        $container->register(FooTagClass::class, FooTagClass::class)->addResourceTag('my_tag', ['key' => 'same', 'priority' => 10]);
        $container->register('service', 'stdClass')->setArguments([new TaggedClassMapArgument('my_tag', 'key')]);

        (new ResolveTaggedClassMapArgumentPass())->process($container);

        $expected = new TaggedClassMapArgument('my_tag', 'key');
        $expected->setValues([
            'same' => FooTagClass::class,
        ]);
        $this->assertEquals($expected, $container->getDefinition('service')->getArgument(0));
    }

    public function testProcessFallsBackToAsTaggedItemPriority()
    {
        $container = new ContainerBuilder();
        $container->register(BarTagClass::class, BarTagClass::class)->addResourceTag('my_tag', ['key' => 'bar']);
        $container->register(BazTagClass::class, BazTagClass::class)->setAutoconfigured(true)->addResourceTag('my_tag');
        $container->register('service', 'stdClass')->setArguments([new TaggedClassMapArgument('my_tag', 'key')]);

        (new ResolveTaggedClassMapArgumentPass())->process($container);

        $this->assertSame([
            'baz_tag_class' => BazTagClass::class,
            'bar' => BarTagClass::class,
        ], $container->getDefinition('service')->getArgument(0)->getValues());
    }

    public function testProcessWithRepeatedAsTaggedItemAttributes()
    {
        $container = new ContainerBuilder();
        $container->register(BarTagClass::class, BarTagClass::class)->addResourceTag('my_tag', ['key' => 'bar']);
        $container->register(QuxTagClass::class, QuxTagClass::class)->setAutoconfigured(true)->addResourceTag('my_tag');
        $container->register('service', 'stdClass')->setArguments([new TaggedClassMapArgument('my_tag', 'key')]);

        (new ResolveTaggedClassMapArgumentPass())->process($container);

        $this->assertSame([
            'qux_one' => QuxTagClass::class,
            'qux_two' => QuxTagClass::class,
            'bar' => BarTagClass::class,
        ], $container->getDefinition('service')->getArgument(0)->getValues());
    }

    public function testProcessSkipsAbstractResources()
    {
        $container = new ContainerBuilder();
        $container->register(BarTagClass::class, BarTagClass::class)->addResourceTag('my_tag', ['key' => 'bar']);
        $container->register('.abstract.resource', FooTagClass::class)->setAbstract(true)->addResourceTag('my_tag', ['key' => 'foo']);
        $container->register('service', 'stdClass')->setArguments([new TaggedClassMapArgument('my_tag', 'key')]);

        (new ResolveTaggedClassMapArgumentPass())->process($container);

        $this->assertSame([
            'bar' => BarTagClass::class,
        ], $container->getDefinition('service')->getArgument(0)->getValues());
    }

    public function testProcessThrowsOnNonExcludedService()
    {
        $container = new ContainerBuilder();
        $container->register(BarTagClass::class, BarTagClass::class)->addTag('my_tag', ['key' => 'bar']);
        $container->register('service', 'stdClass')->setArguments([new TaggedClassMapArgument('my_tag', 'key')]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The resource "%s" tagged "my_tag" is missing the "container.excluded" tag; did you mean to use "resource_tags" instead of "tags"?', BarTagClass::class));

        (new ResolveTaggedClassMapArgumentPass())->process($container);
    }
}
