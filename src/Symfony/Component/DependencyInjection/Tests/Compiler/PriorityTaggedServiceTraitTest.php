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
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\Compiler\ResolveInstanceofConditionalsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Tests\Fixtures\BarTagClass;
use Symfony\Component\DependencyInjection\Tests\Fixtures\FooTagClass;
use Symfony\Component\DependencyInjection\Tests\Fixtures\FooTaggedForInvalidDefaultMethodClass;
use Symfony\Component\DependencyInjection\Tests\Fixtures\IntTagClass;
use Symfony\Component\DependencyInjection\TypedReference;

class PriorityTaggedServiceTraitTest extends TestCase
{
    public function testThatCacheWarmersAreProcessedInPriorityOrder()
    {
        $services = [
            'my_service1' => ['my_custom_tag' => ['priority' => 100]],
            'my_service2' => ['my_custom_tag' => ['priority' => 200]],
            'my_service3' => ['my_custom_tag' => ['priority' => -501]],
            'my_service4' => ['my_custom_tag' => []],
            'my_service5' => ['my_custom_tag' => ['priority' => -1]],
            'my_service6' => ['my_custom_tag' => ['priority' => -500]],
            'my_service7' => ['my_custom_tag' => ['priority' => -499]],
            'my_service8' => ['my_custom_tag' => ['priority' => 1]],
            'my_service9' => ['my_custom_tag' => ['priority' => -2]],
            'my_service10' => ['my_custom_tag' => ['priority' => -1000]],
            'my_service11' => ['my_custom_tag' => ['priority' => -1001]],
            'my_service12' => ['my_custom_tag' => ['priority' => -1002]],
            'my_service13' => ['my_custom_tag' => ['priority' => -1003]],
            'my_service14' => ['my_custom_tag' => ['priority' => -1000]],
            'my_service15' => ['my_custom_tag' => ['priority' => 1]],
            'my_service16' => ['my_custom_tag' => ['priority' => -1]],
            'my_service17' => ['my_custom_tag' => ['priority' => 200]],
            'my_service18' => ['my_custom_tag' => ['priority' => 100]],
            'my_service19' => ['my_custom_tag' => []],
        ];

        $container = new ContainerBuilder();

        foreach ($services as $id => $tags) {
            $definition = $container->register($id);

            foreach ($tags as $name => $attributes) {
                $definition->addTag($name, $attributes);
            }
        }

        $expected = [
            new Reference('my_service2'),
            new Reference('my_service17'),
            new Reference('my_service1'),
            new Reference('my_service18'),
            new Reference('my_service8'),
            new Reference('my_service15'),
            new Reference('my_service4'),
            new Reference('my_service19'),
            new Reference('my_service5'),
            new Reference('my_service16'),
            new Reference('my_service9'),
            new Reference('my_service7'),
            new Reference('my_service6'),
            new Reference('my_service3'),
            new Reference('my_service10'),
            new Reference('my_service14'),
            new Reference('my_service11'),
            new Reference('my_service12'),
            new Reference('my_service13'),
        ];

        $priorityTaggedServiceTraitImplementation = new PriorityTaggedServiceTraitImplementation();

        $this->assertEquals($expected, $priorityTaggedServiceTraitImplementation->test('my_custom_tag', $container));
    }

    public function testWithEmptyArray()
    {
        $container = new ContainerBuilder();
        $priorityTaggedServiceTraitImplementation = new PriorityTaggedServiceTraitImplementation();
        $this->assertEquals([], $priorityTaggedServiceTraitImplementation->test('my_custom_tag', $container));
    }

    public function testOnlyTheFirstNonIndexedTagIsListed()
    {
        $container = new ContainerBuilder();
        $container->register('service1')->addTag('my_custom_tag');

        $definition = $container->register('service2', BarTagClass::class);
        $definition->addTag('my_custom_tag', ['priority' => 100]);
        $definition->addTag('my_custom_tag', []);

        $priorityTaggedServiceTraitImplementation = new PriorityTaggedServiceTraitImplementation();

        $expected = [
            new Reference('service2'),
            new Reference('service1'),
        ];
        $this->assertEquals($expected, $priorityTaggedServiceTraitImplementation->test('my_custom_tag', $container));
    }

    public function testOnlyTheIndexedTagsAreListed()
    {
        $container = new ContainerBuilder();
        $container->register('service1')->addTag('my_custom_tag', ['foo' => 'bar']);

        $definition = $container->register('service2', BarTagClass::class);
        $definition->addTag('my_custom_tag', ['priority' => 100]);
        $definition->addTag('my_custom_tag', ['foo' => 'a']);
        $definition->addTag('my_custom_tag', ['foo' => 'b', 'priority' => 100]);
        $definition->addTag('my_custom_tag', ['foo' => 'b']);
        $definition->addTag('my_custom_tag', []);

        $priorityTaggedServiceTraitImplementation = new PriorityTaggedServiceTraitImplementation();

        $tag = new TaggedIteratorArgument('my_custom_tag', 'foo');
        $expected = [
            'bar_tag_class' => new TypedReference('service2', BarTagClass::class),
            'b' => new TypedReference('service2', BarTagClass::class),
            'bar' => new Reference('service1'),
            'a' => new TypedReference('service2', BarTagClass::class),
        ];
        $services = $priorityTaggedServiceTraitImplementation->test($tag, $container);
        $this->assertSame(array_keys($expected), array_keys($services));
        $this->assertEquals($expected, $priorityTaggedServiceTraitImplementation->test($tag, $container));
    }

    public function testTheIndexedTagsByDefaultIndexMethod()
    {
        $container = new ContainerBuilder();
        $container->register('service1', FooTagClass::class)->addTag('my_custom_tag');

        $definition = $container->register('service2', BarTagClass::class);
        $definition->addTag('my_custom_tag', ['priority' => 100]);
        $definition->addTag('my_custom_tag', []);

        $container->register('service3', IntTagClass::class)->addTag('my_custom_tag');

        $priorityTaggedServiceTraitImplementation = new PriorityTaggedServiceTraitImplementation();

        $tag = new TaggedIteratorArgument('my_custom_tag', 'foo', 'getFooBar');
        $expected = [
            'bar_tab_class_with_defaultmethod' => new TypedReference('service2', BarTagClass::class),
            'service1' => new TypedReference('service1', FooTagClass::class),
            '10' => new TypedReference('service3', IntTagClass::class),
        ];
        $services = $priorityTaggedServiceTraitImplementation->test($tag, $container);
        $this->assertSame(array_keys($expected), array_keys($services));
        $this->assertEquals($expected, $priorityTaggedServiceTraitImplementation->test($tag, $container));
    }

    /**
     * @dataProvider provideInvalidDefaultMethods
     */
    public function testTheIndexedTagsByDefaultIndexMethodFailure(string $defaultIndexMethod, ?string $indexAttribute, string $expectedExceptionMessage)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        $container = new ContainerBuilder();

        $container->register('service1', FooTaggedForInvalidDefaultMethodClass::class)->addTag('my_custom_tag');

        $priorityTaggedServiceTraitImplementation = new PriorityTaggedServiceTraitImplementation();

        $tag = new TaggedIteratorArgument('my_custom_tag', $indexAttribute, $defaultIndexMethod);
        $priorityTaggedServiceTraitImplementation->test($tag, $container);
    }

    public static function provideInvalidDefaultMethods(): iterable
    {
        yield ['getMethodShouldBeStatic', null, sprintf('Method "%s::getMethodShouldBeStatic()" should be static.', FooTaggedForInvalidDefaultMethodClass::class)];
        yield ['getMethodShouldBeStatic', 'foo', sprintf('Either method "%s::getMethodShouldBeStatic()" should be static or tag "my_custom_tag" on service "service1" is missing attribute "foo".', FooTaggedForInvalidDefaultMethodClass::class)];
        yield ['getMethodShouldBePublicInsteadProtected', null, sprintf('Method "%s::getMethodShouldBePublicInsteadProtected()" should be public.', FooTaggedForInvalidDefaultMethodClass::class)];
        yield ['getMethodShouldBePublicInsteadProtected', 'foo', sprintf('Either method "%s::getMethodShouldBePublicInsteadProtected()" should be public or tag "my_custom_tag" on service "service1" is missing attribute "foo".', FooTaggedForInvalidDefaultMethodClass::class)];
        yield ['getMethodShouldBePublicInsteadPrivate', null, sprintf('Method "%s::getMethodShouldBePublicInsteadPrivate()" should be public.', FooTaggedForInvalidDefaultMethodClass::class)];
        yield ['getMethodShouldBePublicInsteadPrivate', 'foo', sprintf('Either method "%s::getMethodShouldBePublicInsteadPrivate()" should be public or tag "my_custom_tag" on service "service1" is missing attribute "foo".', FooTaggedForInvalidDefaultMethodClass::class)];
    }

    public function testTaggedItemAttributes()
    {
        $container = new ContainerBuilder();
        $container->register('service1', FooTagClass::class)->addTag('my_custom_tag');
        $container->register('service2', HelloNamedService::class)
            ->setAutoconfigured(true)
            ->setInstanceofConditionals([
                HelloNamedService::class => (new ChildDefinition(''))->addTag('my_custom_tag'),
                \stdClass::class => (new ChildDefinition(''))->addTag('my_custom_tag2'),
            ]);
        $container->register('service3', HelloNamedService2::class)
            ->setAutoconfigured(true)
            ->addTag('my_custom_tag');
        $container->register('service4', HelloNamedService2::class)
            ->setAutoconfigured(true)
            ->addTag('my_custom_tag');
        $container->register('service5', HelloNamedService2::class)
            ->setAutoconfigured(true)
            ->addTag('my_custom_tag');

        (new ResolveInstanceofConditionalsPass())->process($container);

        $priorityTaggedServiceTraitImplementation = new PriorityTaggedServiceTraitImplementation();

        $tag = new TaggedIteratorArgument('my_custom_tag', 'foo', 'getFooBar', exclude: ['service4', 'service5']);
        $expected = [
            'service3' => new TypedReference('service3', HelloNamedService2::class),
            'hello' => new TypedReference('service2', HelloNamedService::class),
            'service1' => new TypedReference('service1', FooTagClass::class),
        ];
        $services = $priorityTaggedServiceTraitImplementation->test($tag, $container);
        $this->assertSame(array_keys($expected), array_keys($services));
        $this->assertEquals($expected, $priorityTaggedServiceTraitImplementation->test($tag, $container));
    }
}

class PriorityTaggedServiceTraitImplementation
{
    use PriorityTaggedServiceTrait;

    public function test($tagName, ContainerBuilder $container)
    {
        return $this->findAndSortTaggedServices($tagName, $container);
    }
}

#[AsTaggedItem(index: 'hello', priority: 1)]
class HelloNamedService extends \stdClass
{
}

#[AsTaggedItem(priority: 2)]
class HelloNamedService2
{
}
