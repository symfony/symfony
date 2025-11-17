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

use PHPUnit\Framework\Attributes\DataProvider;
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

        $container->register('service4', HelloInterface::class)->addTag('my_custom_tag');

        $definition = $container->register('debug.service5', \stdClass::class)->addTag('my_custom_tag');
        $definition->addTag('container.decorator', ['id' => 'service5']);

        $priorityTaggedServiceTraitImplementation = new PriorityTaggedServiceTraitImplementation();

        $tag = new TaggedIteratorArgument('my_custom_tag', 'foo', 'getFooBar');
        $expected = [
            'bar_tab_class_with_defaultmethod' => new TypedReference('service2', BarTagClass::class),
            'service1' => new TypedReference('service1', FooTagClass::class),
            '10' => new TypedReference('service3', IntTagClass::class),
            'service4' => new TypedReference('service4', HelloInterface::class),
            'service5' => new TypedReference('debug.service5', \stdClass::class),
        ];
        $services = $priorityTaggedServiceTraitImplementation->test($tag, $container);
        $this->assertSame(array_keys($expected), array_keys($services));
        $this->assertEquals($expected, $priorityTaggedServiceTraitImplementation->test($tag, $container));
    }

    #[DataProvider('provideInvalidDefaultMethods')]
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
        yield ['getMethodShouldBeStatic', null, \sprintf('Method "%s::getMethodShouldBeStatic()" should be static.', FooTaggedForInvalidDefaultMethodClass::class)];
        yield ['getMethodShouldBeStatic', 'foo', \sprintf('Either method "%s::getMethodShouldBeStatic()" should be static or tag "my_custom_tag" on service "service1" is missing attribute "foo".', FooTaggedForInvalidDefaultMethodClass::class)];
        yield ['getMethodShouldBePublicInsteadProtected', null, \sprintf('Method "%s::getMethodShouldBePublicInsteadProtected()" should be public.', FooTaggedForInvalidDefaultMethodClass::class)];
        yield ['getMethodShouldBePublicInsteadProtected', 'foo', \sprintf('Either method "%s::getMethodShouldBePublicInsteadProtected()" should be public or tag "my_custom_tag" on service "service1" is missing attribute "foo".', FooTaggedForInvalidDefaultMethodClass::class)];
        yield ['getMethodShouldBePublicInsteadPrivate', null, \sprintf('Method "%s::getMethodShouldBePublicInsteadPrivate()" should be public.', FooTaggedForInvalidDefaultMethodClass::class)];
        yield ['getMethodShouldBePublicInsteadPrivate', 'foo', \sprintf('Either method "%s::getMethodShouldBePublicInsteadPrivate()" should be public or tag "my_custom_tag" on service "service1" is missing attribute "foo".', FooTaggedForInvalidDefaultMethodClass::class)];
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
        $container->register('service6', MultiTagHelloNamedService::class)
            ->setAutoconfigured(true)
            ->addTag('my_custom_tag');

        (new ResolveInstanceofConditionalsPass())->process($container);

        $priorityTaggedServiceTraitImplementation = new PriorityTaggedServiceTraitImplementation();

        $tag = new TaggedIteratorArgument('my_custom_tag', 'foo', 'getFooBar', exclude: ['service4', 'service5']);
        $expected = [
            'service3' => new TypedReference('service3', HelloNamedService2::class),
            'multi_hello_2' => new TypedReference('service6', MultiTagHelloNamedService::class),
            'hello' => new TypedReference('service2', HelloNamedService::class),
            'multi_hello_1' => new TypedReference('service6', MultiTagHelloNamedService::class),
            'service1' => new TypedReference('service1', FooTagClass::class),
            'multi_hello_0' => new TypedReference('service6', MultiTagHelloNamedService::class),
        ];

        $services = $priorityTaggedServiceTraitImplementation->test($tag, $container);
        $this->assertSame(array_keys($expected), array_keys($services));
        $this->assertEquals($expected, $priorityTaggedServiceTraitImplementation->test($tag, $container));
    }

    public function testResolveIndexedTags()
    {
        $container = new ContainerBuilder();
        $container->setParameter('custom_param_service1', 'bar');
        $container->setParameter('custom_param_service2', 'baz');
        $container->setParameter('custom_param_service2_empty', '');
        $container->setParameter('custom_param_service2_null', null);
        $container->register('service1')->addTag('my_custom_tag', ['foo' => '%custom_param_service1%']);

        $definition = $container->register('service2', BarTagClass::class);
        $definition->addTag('my_custom_tag', ['foo' => '%custom_param_service2%', 'priority' => 100]);
        $definition->addTag('my_custom_tag', ['foo' => '%custom_param_service2_empty%']);
        $definition->addTag('my_custom_tag', ['foo' => '%custom_param_service2_null%']);

        $priorityTaggedServiceTraitImplementation = new PriorityTaggedServiceTraitImplementation();

        $tag = new TaggedIteratorArgument('my_custom_tag', 'foo', 'getFooBar');
        $expected = [
            'baz' => new TypedReference('service2', BarTagClass::class),
            'bar' => new Reference('service1'),
            '' => new TypedReference('service2', BarTagClass::class),
            'bar_tab_class_with_defaultmethod' => new TypedReference('service2', BarTagClass::class),
        ];
        $services = $priorityTaggedServiceTraitImplementation->test($tag, $container);
        $this->assertSame(array_keys($expected), array_keys($services));
        $this->assertEquals($expected, $priorityTaggedServiceTraitImplementation->test($tag, $container));
    }

    public function testAttributesAreMergedWithTags()
    {
        $container = new ContainerBuilder();
        $definition = $container->register('service_attr_first', MultiTagHelloNamedService::class);
        $definition->setAutoconfigured(true);
        $definition->addTag('my_custom_tag', ['foo' => 'z']);
        $definition->addTag('my_custom_tag', []);

        (new ResolveInstanceofConditionalsPass())->process($container);

        $priorityTaggedServiceTraitImplementation = new PriorityTaggedServiceTraitImplementation();

        $tag = new TaggedIteratorArgument('my_custom_tag', 'foo', 'getFooBar');
        $services = $priorityTaggedServiceTraitImplementation->test($tag, $container);

        $expected = [
            'multi_hello_2' => new TypedReference('service_attr_first', MultiTagHelloNamedService::class),
            'multi_hello_1' => new TypedReference('service_attr_first', MultiTagHelloNamedService::class),
            'z' => new TypedReference('service_attr_first', MultiTagHelloNamedService::class),
            'multi_hello_0' => new TypedReference('service_attr_first', MultiTagHelloNamedService::class),
        ];
        $this->assertSame(array_keys($expected), array_keys($services));
        $this->assertEquals($expected, $services);
    }

    public function testAttributesAreFallbacks()
    {
        $container = new ContainerBuilder();
        $definition = $container->register('service_attr_first', MultiTagHelloNamedService::class);
        $definition->setAutoconfigured(true);
        $definition->addTag('my_custom_tag', ['foo' => 'z']);

        (new ResolveInstanceofConditionalsPass())->process($container);

        $priorityTaggedServiceTraitImplementation = new PriorityTaggedServiceTraitImplementation();

        $tag = new TaggedIteratorArgument('my_custom_tag', 'foo', 'getFooBar');
        $services = $priorityTaggedServiceTraitImplementation->test($tag, $container);

        $this->assertEquals(['z' => new TypedReference('service_attr_first', MultiTagHelloNamedService::class)], $services);
    }

    public function testTaggedIteratorWithDefaultNameMethod()
    {
        $container = new ContainerBuilder();
        $container->register('service', ClassWithDefaultNameMethod::class)->addTag('my_custom_tag');

        $priorityTaggedServiceTraitImplementation = new PriorityTaggedServiceTraitImplementation();

        $tag = new TaggedIteratorArgument('my_custom_tag');
        $services = $priorityTaggedServiceTraitImplementation->test($tag, $container);
        $this->assertEquals([new Reference('service')], $services);
    }

    public function testIndexedIteratorUsesTagAttributeOverDefaultMethod()
    {
        $container = new ContainerBuilder();
        $container->register('service.a', ServiceWithStaticGetType::class)
            ->addTag('my_tag', ['type' => 'from_tag']);

        $priorityTaggedServiceTraitImplementation = new PriorityTaggedServiceTraitImplementation();

        $tag = new TaggedIteratorArgument('my_tag', 'type', 'getType');
        $services = $priorityTaggedServiceTraitImplementation->test($tag, $container);

        $this->assertArrayHasKey('from_tag', $services);
        $this->assertArrayNotHasKey('from_static_method', $services);
        $this->assertInstanceOf(TypedReference::class, $services['from_tag']);
        $this->assertSame('service.a', (string) $services['from_tag']);
    }

    public function testIndexedIteratorUsesDefaultMethodAsFallback()
    {
        $container = new ContainerBuilder();
        $container->register('service.a', ServiceWithStaticGetType::class)
            ->addTag('my_tag');

        $priorityTaggedServiceTraitImplementation = new PriorityTaggedServiceTraitImplementation();

        $tag = new TaggedIteratorArgument('my_tag', 'type', 'getType');
        $services = $priorityTaggedServiceTraitImplementation->test($tag, $container);

        $this->assertArrayHasKey('from_static_method', $services);
        $this->assertArrayNotHasKey('from_tag', $services);
        $this->assertInstanceOf(TypedReference::class, $services['from_static_method']);
    }

    public function testIndexedIteratorUsesTagIndexAndDefaultPriorityMethod()
    {
        $container = new ContainerBuilder();

        $container->register('service.a', ServiceWithStaticPriority::class)
            ->addTag('my_tag', ['type' => 'tag_index']);

        $container->register('service.b', \stdClass::class)
            ->addTag('my_tag', ['type' => 'another_index']);

        $priorityTaggedServiceTraitImplementation = new PriorityTaggedServiceTraitImplementation();

        $tag = new TaggedIteratorArgument('my_tag', 'type', null, 'getPriority');
        $services = $priorityTaggedServiceTraitImplementation->test($tag, $container);

        $this->assertArrayHasKey('tag_index', $services);
        $this->assertSame('service.a', (string) $services['tag_index']);

        $this->assertSame(['tag_index', 'another_index'], array_keys($services));
    }

    public function testTaggedLocatorWithProvidedIndexAttributeAndNonStaticDefaultIndexMethod()
    {
        $container = new ContainerBuilder();
        $container->register('service', NonStaticDefaultIndexClass::class)
            ->addTag('my_custom_tag', ['type' => 'foo']);

        $priorityTaggedServiceTraitImplementation = new PriorityTaggedServiceTraitImplementation();
        $tag = new TaggedIteratorArgument('my_custom_tag', 'type', 'getType');

        $services = $priorityTaggedServiceTraitImplementation->test($tag, $container);
        $this->assertEquals(['foo' => new TypedReference('service', NonStaticDefaultIndexClass::class)], $services);
    }

    public function testTaggedLocatorWithoutIndexAttributeAndNonStaticDefaultIndexMethod()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Either method "%s::getType()" should be static or tag "my_custom_tag" on service "service" is missing attribute "type".', NonStaticDefaultIndexClass::class));

        $container = new ContainerBuilder();
        $container->register('service', NonStaticDefaultIndexClass::class)
            ->addTag('my_custom_tag');

        $priorityTaggedServiceTraitImplementation = new PriorityTaggedServiceTraitImplementation();
        $tag = new TaggedIteratorArgument('my_custom_tag', 'type', 'getType');

        $priorityTaggedServiceTraitImplementation->test($tag, $container);
    }

    public function testMergingAsTaggedItemWithEmptyTagAndNonStaticBusinessMethod()
    {
        $container = new ContainerBuilder();
        $container->register('service', AsTaggedItemClassWithBusinessMethod::class)
            ->setAutoconfigured(true)
            ->addTag('my_custom_tag');

        (new ResolveInstanceofConditionalsPass())->process($container);

        $priorityTaggedServiceTraitImplementation = new PriorityTaggedServiceTraitImplementation();
        $tag = new TaggedIteratorArgument('my_custom_tag', 'index');

        $services = $priorityTaggedServiceTraitImplementation->test($tag, $container);
        $this->assertEquals(['bar' => new TypedReference('service', AsTaggedItemClassWithBusinessMethod::class)], $services);
    }

    public function testPriorityFallbackWithoutIndexAndStaticPriorityMethod()
    {
        $container = new ContainerBuilder();
        $container->register('service', StaticPriorityClass::class)
            ->addTag('my_custom_tag');

        $priorityTaggedServiceTraitImplementation = new PriorityTaggedServiceTraitImplementation();
        $tag = new TaggedIteratorArgument('my_custom_tag', null, null, false, 'getDefaultPriority');

        $services = $priorityTaggedServiceTraitImplementation->test($tag, $container);
        $this->assertEquals([new Reference('service')], $services);
    }

    public function testMultiTagsWithMixedAttributesAndNonStaticDefault()
    {
        $container = new ContainerBuilder();
        $container->register('service', MultiTagNonStaticClass::class)
            ->addTag('my_custom_tag', ['type' => 'foo'])
            ->addTag('my_custom_tag');

        $priorityTaggedServiceTraitImplementation = new PriorityTaggedServiceTraitImplementation();
        $tag = new TaggedIteratorArgument('my_custom_tag', 'type', 'getType');

        $services = $priorityTaggedServiceTraitImplementation->test($tag, $container);
        $this->assertCount(2, $services);
        $this->assertArrayHasKey('foo', $services);
        $this->assertArrayHasKey('default', $services);
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

#[AsTaggedItem(index: 'multi_hello_0', priority: 0)]
#[AsTaggedItem(index: 'multi_hello_1', priority: 1)]
#[AsTaggedItem(index: 'multi_hello_2', priority: 2)]
class MultiTagHelloNamedService
{
}

interface HelloInterface
{
    public static function getFooBar(): string;
}

class ClassWithDefaultNameMethod
{
    public function getDefaultName(): string
    {
        return 'foo';
    }
}

class ServiceWithStaticGetType
{
    public static function getType(): string
    {
        return 'from_static_method';
    }
}

class ServiceWithStaticPriority
{
    public static function getPriority(): int
    {
        return 10;
    }
}

class NonStaticDefaultIndexClass
{
    public function getType(): string
    {
        return 'foo';
    }
}

#[AsTaggedItem(index: 'bar')]
class AsTaggedItemClassWithBusinessMethod
{
    public function getDefaultName(): string
    {
        return 'ignored';
    }
}

class StaticPriorityClass
{
    public static function getDefaultPriority(): int
    {
        return 10;
    }
}

class MultiTagNonStaticClass
{
    public static function getType(): string
    {
        return 'default';
    }
}
