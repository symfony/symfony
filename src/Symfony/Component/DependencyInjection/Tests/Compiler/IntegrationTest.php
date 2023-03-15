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
use Psr\Container\ContainerInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Attribute\CustomAnyAttribute;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Attribute\CustomAutoconfiguration;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Attribute\CustomMethodAttribute;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Attribute\CustomParameterAttribute;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Attribute\CustomPropertyAttribute;
use Symfony\Component\DependencyInjection\Tests\Fixtures\AutoconfiguredInterface2;
use Symfony\Component\DependencyInjection\Tests\Fixtures\AutoconfiguredService1;
use Symfony\Component\DependencyInjection\Tests\Fixtures\AutoconfiguredService2;
use Symfony\Component\DependencyInjection\Tests\Fixtures\BarTagClass;
use Symfony\Component\DependencyInjection\Tests\Fixtures\FooBarTaggedClass;
use Symfony\Component\DependencyInjection\Tests\Fixtures\FooBarTaggedForDefaultPriorityClass;
use Symfony\Component\DependencyInjection\Tests\Fixtures\FooTagClass;
use Symfony\Component\DependencyInjection\Tests\Fixtures\IteratorConsumer;
use Symfony\Component\DependencyInjection\Tests\Fixtures\IteratorConsumerWithDefaultIndexMethod;
use Symfony\Component\DependencyInjection\Tests\Fixtures\IteratorConsumerWithDefaultIndexMethodAndWithDefaultPriorityMethod;
use Symfony\Component\DependencyInjection\Tests\Fixtures\IteratorConsumerWithDefaultPriorityMethod;
use Symfony\Component\DependencyInjection\Tests\Fixtures\LocatorConsumer;
use Symfony\Component\DependencyInjection\Tests\Fixtures\LocatorConsumerConsumer;
use Symfony\Component\DependencyInjection\Tests\Fixtures\LocatorConsumerFactory;
use Symfony\Component\DependencyInjection\Tests\Fixtures\LocatorConsumerWithDefaultIndexMethod;
use Symfony\Component\DependencyInjection\Tests\Fixtures\LocatorConsumerWithDefaultIndexMethodAndWithDefaultPriorityMethod;
use Symfony\Component\DependencyInjection\Tests\Fixtures\LocatorConsumerWithDefaultPriorityMethod;
use Symfony\Component\DependencyInjection\Tests\Fixtures\LocatorConsumerWithoutIndex;
use Symfony\Component\DependencyInjection\Tests\Fixtures\StaticMethodTag;
use Symfony\Component\DependencyInjection\Tests\Fixtures\TaggedConsumerWithExclude;
use Symfony\Component\DependencyInjection\Tests\Fixtures\TaggedService1;
use Symfony\Component\DependencyInjection\Tests\Fixtures\TaggedService2;
use Symfony\Component\DependencyInjection\Tests\Fixtures\TaggedService3;
use Symfony\Component\DependencyInjection\Tests\Fixtures\TaggedService3Configurator;
use Symfony\Component\DependencyInjection\Tests\Fixtures\TaggedService4;
use Symfony\Contracts\Service\ServiceProviderInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

/**
 * This class tests the integration of the different compiler passes.
 */
class IntegrationTest extends TestCase
{
    /**
     * This tests that dependencies are correctly processed.
     *
     * We're checking that:
     *
     *   * A is public, B/C are private
     *   * A -> C
     *   * B -> C
     */
    public function testProcessRemovesAndInlinesRecursively()
    {
        $container = new ContainerBuilder();
        $container->setResourceTracking(false);

        $a = $container
            ->register('a', '\stdClass')
            ->addArgument(new Reference('c'))
            ->setPublic(true)
        ;

        $container
            ->register('b', '\stdClass')
            ->addArgument(new Reference('c'))
        ;

        $c = $container
            ->register('c', '\stdClass')
        ;

        $container->compile();

        $this->assertTrue($container->hasDefinition('a'));
        $arguments = $a->getArguments();
        $this->assertSame($c, $arguments[0]);
        $this->assertFalse($container->hasDefinition('b'));
        $this->assertFalse($container->hasDefinition('c'));
    }

    public function testProcessInlinesReferencesToAliases()
    {
        $container = new ContainerBuilder();
        $container->setResourceTracking(false);

        $a = $container
            ->register('a', '\stdClass')
            ->addArgument(new Reference('b'))
            ->setPublic(true)
        ;

        $container->setAlias('b', new Alias('c', false));

        $c = $container
            ->register('c', '\stdClass')
        ;

        $container->compile();

        $this->assertTrue($container->hasDefinition('a'));
        $arguments = $a->getArguments();
        $this->assertSame($c, $arguments[0]);
        $this->assertFalse($container->hasAlias('b'));
        $this->assertFalse($container->hasDefinition('c'));
    }

    public function testProcessInlinesWhenThereAreMultipleReferencesButFromTheSameDefinition()
    {
        $container = new ContainerBuilder();
        $container->setResourceTracking(false);

        $container
            ->register('a', '\stdClass')
            ->addArgument(new Reference('b'))
            ->addMethodCall('setC', [new Reference('c')])
            ->setPublic(true)
        ;

        $container
            ->register('b', '\stdClass')
            ->addArgument(new Reference('c'))
        ;

        $container
            ->register('c', '\stdClass')
        ;

        $container->compile();

        $this->assertTrue($container->hasDefinition('a'));
        $this->assertFalse($container->hasDefinition('b'));
        $this->assertFalse($container->hasDefinition('c'), 'Service C was not inlined.');
    }

    public function testCanDecorateServiceSubscriberUsingBinding()
    {
        $container = new ContainerBuilder();
        $container->register(ServiceSubscriberStub::class)
            ->addTag('container.service_subscriber')
            ->setPublic(true);

        $container->register(DecoratedServiceSubscriber::class)
            ->setProperty('inner', new Reference(DecoratedServiceSubscriber::class.'.inner'))
            ->setDecoratedService(ServiceSubscriberStub::class);

        $container->compile();

        $this->assertInstanceOf(DecoratedServiceSubscriber::class, $container->get(ServiceSubscriberStub::class));
        $this->assertInstanceOf(ServiceSubscriberStub::class, $container->get(ServiceSubscriberStub::class)->inner);
        $this->assertInstanceOf(ServiceLocator::class, $container->get(ServiceSubscriberStub::class)->inner->container);
    }

    public function testCanDecorateServiceSubscriberReplacingArgument()
    {
        $container = new ContainerBuilder();
        $container->register(ServiceSubscriberStub::class)
            ->setArguments([new Reference(ContainerInterface::class)])
            ->addTag('container.service_subscriber')
            ->setPublic(true);

        $container->register(DecoratedServiceSubscriber::class)
            ->setProperty('inner', new Reference(DecoratedServiceSubscriber::class.'.inner'))
            ->setDecoratedService(ServiceSubscriberStub::class);

        $container->compile();

        $this->assertInstanceOf(DecoratedServiceSubscriber::class, $container->get(ServiceSubscriberStub::class));
        $this->assertInstanceOf(ServiceSubscriberStub::class, $container->get(ServiceSubscriberStub::class)->inner);
        $this->assertInstanceOf(ServiceLocator::class, $container->get(ServiceSubscriberStub::class)->inner->container);
    }

    public function testCanDecorateServiceLocator()
    {
        $container = new ContainerBuilder();

        $container->register('foo', 'stdClass')->setPublic(true);

        $container->register(ServiceLocator::class)
            ->addTag('container.service_locator')
            ->setArguments([[new Reference('foo')]])
        ;

        $container->register(DecoratedServiceLocator::class)
            ->setDecoratedService(ServiceLocator::class)
            ->setPublic(true)
            ->setArguments([new Reference(DecoratedServiceLocator::class.'.inner')])
        ;

        $container->compile();

        $this->assertInstanceOf(DecoratedServiceLocator::class, $container->get(DecoratedServiceLocator::class));
        $this->assertSame($container->get('foo'), $container->get(DecoratedServiceLocator::class)->get('foo'));
    }

    public function testAliasDecoratedService()
    {
        $container = new ContainerBuilder();

        $container->register('service', ServiceLocator::class)
            ->setPublic(true)
            ->setArguments([[]])
        ;
        $container->register('decorator', DecoratedServiceLocator::class)
            ->setDecoratedService('service')
            ->setAutowired(true)
            ->setPublic(true)
        ;
        $container->setAlias(ServiceLocator::class, 'decorator.inner')
            ->setPublic(true)
        ;
        $container->register('user_service', DecoratedServiceLocator::class)
            ->setAutowired(true)
        ;

        $container->compile();

        $this->assertInstanceOf(DecoratedServiceLocator::class, $container->get('service'));
        $this->assertInstanceOf(ServiceLocator::class, $container->get(ServiceLocator::class));
        $this->assertSame($container->get('service'), $container->get('decorator'));
    }

    /**
     * @dataProvider getYamlCompileTests
     */
    public function testYamlContainerCompiles($directory, $actualServiceId, $expectedServiceId, ContainerBuilder $mainContainer = null)
    {
        // allow a container to be passed in, which might have autoconfigure settings
        $container = $mainContainer ?? new ContainerBuilder();
        $container->setResourceTracking(false);
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Fixtures/yaml/integration/'.$directory));
        $loader->load('main.yml');
        $container->compile();
        $actualService = $container->getDefinition($actualServiceId);

        // create a fresh ContainerBuilder, to avoid autoconfigure stuff
        $container = new ContainerBuilder();
        $container->setResourceTracking(false);
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Fixtures/yaml/integration/'.$directory));
        $loader->load('expected.yml');
        $container->compile();
        $expectedService = $container->getDefinition($expectedServiceId);

        // reset changes, we don't care if these differ
        $actualService->setChanges([]);
        $expectedService->setChanges([]);

        $this->assertEquals($expectedService, $actualService);
    }

    public static function getYamlCompileTests()
    {
        $container = new ContainerBuilder();
        $container->registerForAutoconfiguration(IntegrationTestStub::class);
        yield [
            'autoconfigure_child_not_applied',
            'child_service',
            'child_service_expected',
            $container,
        ];

        $container = new ContainerBuilder();
        $container->registerForAutoconfiguration(IntegrationTestStub::class);
        yield [
            'autoconfigure_parent_child',
            'child_service',
            'child_service_expected',
            $container,
        ];

        $container = new ContainerBuilder();
        $container->registerForAutoconfiguration(IntegrationTestStub::class)
            ->addTag('from_autoconfigure');
        yield [
            'autoconfigure_parent_child_tags',
            'child_service',
            'child_service_expected',
            $container,
        ];

        yield [
            'child_parent',
            'child_service',
            'child_service_expected',
        ];

        yield [
            'defaults_child_tags',
            'child_service',
            'child_service_expected',
        ];

        yield [
            'defaults_instanceof_importance',
            'main_service',
            'main_service_expected',
        ];

        yield [
            'defaults_parent_child',
            'child_service',
            'child_service_expected',
        ];

        yield [
            'instanceof_parent_child',
            'child_service',
            'child_service_expected',
        ];

        $container = new ContainerBuilder();
        $container->registerForAutoconfiguration(IntegrationTestStub::class)
            ->addMethodCall('setSunshine', ['supernova']);
        yield [
            'instanceof_and_calls',
            'main_service',
            'main_service_expected',
            $container,
        ];
    }

    public function testTaggedServiceWithIndexAttribute()
    {
        $container = new ContainerBuilder();
        $container->register(BarTagClass::class)
            ->setPublic(true)
            ->addTag('foo_bar', ['foo' => 'bar'])
        ;
        $container->register(FooTagClass::class)
            ->setPublic(true)
            ->addTag('foo_bar')
        ;
        $container->register(FooBarTaggedClass::class)
            ->addArgument(new TaggedIteratorArgument('foo_bar', 'foo'))
            ->setPublic(true)
        ;

        $container->compile();

        $s = $container->get(FooBarTaggedClass::class);

        $param = iterator_to_array($s->getParam()->getIterator());
        $this->assertSame(['bar' => $container->get(BarTagClass::class), 'foo_tag_class' => $container->get(FooTagClass::class)], $param);
    }

    public function testTaggedServiceWithIndexAttributeAndDefaultMethod()
    {
        $container = new ContainerBuilder();
        $container->register(BarTagClass::class)
            ->setPublic(true)
            ->addTag('foo_bar')
        ;
        $container->register(FooTagClass::class)
            ->setPublic(true)
            ->addTag('foo_bar', ['foo' => 'foo'])
        ;
        $container->register(FooBarTaggedClass::class)
            ->addArgument(new TaggedIteratorArgument('foo_bar', 'foo', 'getFooBar'))
            ->setPublic(true)
        ;

        $container->compile();

        $s = $container->get(FooBarTaggedClass::class);

        $param = iterator_to_array($s->getParam()->getIterator());
        $this->assertSame(['bar_tab_class_with_defaultmethod' => $container->get(BarTagClass::class), 'foo' => $container->get(FooTagClass::class)], $param);
    }

    public function testTaggedServiceWithIndexAttributeAndDefaultMethodConfiguredViaAttribute()
    {
        $container = new ContainerBuilder();
        $container->register(BarTagClass::class)
            ->setPublic(true)
            ->addTag('foo_bar', ['foo' => 'bar_tab_class_with_defaultmethod'])
        ;
        $container->register(FooTagClass::class)
            ->setPublic(true)
            ->addTag('foo_bar', ['foo' => 'foo'])
        ;
        $container->register(IteratorConsumer::class)
            ->setAutowired(true)
            ->setPublic(true)
        ;

        $container->compile();

        $s = $container->get(IteratorConsumer::class);

        $param = iterator_to_array($s->getParam()->getIterator());
        $this->assertSame(['bar_tab_class_with_defaultmethod' => $container->get(BarTagClass::class), 'foo' => $container->get(FooTagClass::class)], $param);
    }

    public function testTaggedIteratorWithDefaultIndexMethodConfiguredViaAttribute()
    {
        $container = new ContainerBuilder();
        $container->register(BarTagClass::class)
            ->setPublic(true)
            ->addTag('foo_bar')
        ;
        $container->register(FooTagClass::class)
            ->setPublic(true)
            ->addTag('foo_bar')
        ;
        $container->register(IteratorConsumerWithDefaultIndexMethod::class)
            ->setAutowired(true)
            ->setPublic(true)
        ;

        $container->compile();

        $s = $container->get(IteratorConsumerWithDefaultIndexMethod::class);

        $param = iterator_to_array($s->getParam()->getIterator());
        $this->assertSame(['bar_tag_class' => $container->get(BarTagClass::class), 'foo_tag_class' => $container->get(FooTagClass::class)], $param);
    }

    public function testTaggedIteratorWithDefaultPriorityMethodConfiguredViaAttribute()
    {
        $container = new ContainerBuilder();
        $container->register(BarTagClass::class)
            ->setPublic(true)
            ->addTag('foo_bar')
        ;
        $container->register(FooTagClass::class)
            ->setPublic(true)
            ->addTag('foo_bar')
        ;
        $container->register(IteratorConsumerWithDefaultPriorityMethod::class)
            ->setAutowired(true)
            ->setPublic(true)
        ;

        $container->compile();

        $s = $container->get(IteratorConsumerWithDefaultPriorityMethod::class);

        $param = iterator_to_array($s->getParam()->getIterator());
        $this->assertSame([0 => $container->get(FooTagClass::class), 1 => $container->get(BarTagClass::class)], $param);
    }

    public function testTaggedIteratorWithDefaultIndexMethodAndWithDefaultPriorityMethodConfiguredViaAttribute()
    {
        $container = new ContainerBuilder();
        $container->register(BarTagClass::class)
            ->setPublic(true)
            ->addTag('foo_bar')
        ;
        $container->register(FooTagClass::class)
            ->setPublic(true)
            ->addTag('foo_bar')
        ;
        $container->register(IteratorConsumerWithDefaultIndexMethodAndWithDefaultPriorityMethod::class)
            ->setAutowired(true)
            ->setPublic(true)
        ;

        $container->compile();

        $s = $container->get(IteratorConsumerWithDefaultIndexMethodAndWithDefaultPriorityMethod::class);

        $param = iterator_to_array($s->getParam()->getIterator());
        $this->assertSame(['foo_tag_class' => $container->get(FooTagClass::class), 'bar_tag_class' => $container->get(BarTagClass::class)], $param);
    }

    public function testTaggedLocatorConfiguredViaAttribute()
    {
        $container = new ContainerBuilder();
        $container->register(BarTagClass::class)
            ->setPublic(true)
            ->addTag('foo_bar', ['foo' => 'bar_tab_class_with_defaultmethod'])
        ;
        $container->register(FooTagClass::class)
            ->setPublic(true)
            ->addTag('foo_bar', ['foo' => 'foo'])
        ;
        $container->register(LocatorConsumer::class)
            ->setAutowired(true)
            ->setPublic(true)
        ;

        $container->compile();

        /** @var LocatorConsumer $s */
        $s = $container->get(LocatorConsumer::class);

        $locator = $s->getLocator();
        self::assertSame($container->get(BarTagClass::class), $locator->get('bar_tab_class_with_defaultmethod'));
        self::assertSame($container->get(FooTagClass::class), $locator->get('foo'));
    }

    public function testTaggedLocatorConfiguredViaAttributeWithoutIndex()
    {
        $container = new ContainerBuilder();
        $container->register(BarTagClass::class)
            ->setPublic(true)
            ->addTag('foo_bar')
        ;
        $container->register(FooTagClass::class)
            ->setPublic(true)
            ->addTag('foo_bar')
        ;
        $container->register(LocatorConsumerWithoutIndex::class)
            ->setAutowired(true)
            ->setPublic(true)
        ;

        $container->compile();

        /** @var LocatorConsumerWithoutIndex $s */
        $s = $container->get(LocatorConsumerWithoutIndex::class);

        $locator = $s->getLocator();
        self::assertSame($container->get(BarTagClass::class), $locator->get(BarTagClass::class));
        self::assertSame($container->get(FooTagClass::class), $locator->get(FooTagClass::class));
    }

    public function testTaggedLocatorWithDefaultIndexMethodConfiguredViaAttribute()
    {
        $container = new ContainerBuilder();
        $container->register(BarTagClass::class)
            ->setPublic(true)
            ->addTag('foo_bar')
        ;
        $container->register(FooTagClass::class)
            ->setPublic(true)
            ->addTag('foo_bar')
        ;
        $container->register(LocatorConsumerWithDefaultIndexMethod::class)
            ->setAutowired(true)
            ->setPublic(true)
        ;

        $container->compile();

        /** @var LocatorConsumerWithoutIndex $s */
        $s = $container->get(LocatorConsumerWithDefaultIndexMethod::class);

        $locator = $s->getLocator();
        self::assertSame($container->get(BarTagClass::class), $locator->get('bar_tag_class'));
        self::assertSame($container->get(FooTagClass::class), $locator->get('foo_tag_class'));
    }

    public function testTaggedLocatorWithDefaultPriorityMethodConfiguredViaAttribute()
    {
        $container = new ContainerBuilder();
        $container->register(BarTagClass::class)
            ->setPublic(true)
            ->addTag('foo_bar')
        ;
        $container->register(FooTagClass::class)
            ->setPublic(true)
            ->addTag('foo_bar')
        ;
        $container->register(LocatorConsumerWithDefaultPriorityMethod::class)
            ->setAutowired(true)
            ->setPublic(true)
        ;

        $container->compile();

        /** @var LocatorConsumerWithoutIndex $s */
        $s = $container->get(LocatorConsumerWithDefaultPriorityMethod::class);

        $locator = $s->getLocator();

        // We need to check priority of instances in the factories
        $factories = (new \ReflectionClass($locator))->getProperty('factories');

        self::assertSame([FooTagClass::class, BarTagClass::class], array_keys($factories->getValue($locator)));
    }

    public function testTaggedLocatorWithDefaultIndexMethodAndWithDefaultPriorityMethodConfiguredViaAttribute()
    {
        $container = new ContainerBuilder();
        $container->register(BarTagClass::class)
            ->setPublic(true)
            ->addTag('foo_bar')
        ;
        $container->register(FooTagClass::class)
            ->setPublic(true)
            ->addTag('foo_bar')
        ;
        $container->register(LocatorConsumerWithDefaultIndexMethodAndWithDefaultPriorityMethod::class)
            ->setAutowired(true)
            ->setPublic(true)
        ;

        $container->compile();

        /** @var LocatorConsumerWithoutIndex $s */
        $s = $container->get(LocatorConsumerWithDefaultIndexMethodAndWithDefaultPriorityMethod::class);

        $locator = $s->getLocator();

        // We need to check priority of instances in the factories
        $factories = (new \ReflectionClass($locator))->getProperty('factories');

        self::assertSame(['foo_tag_class', 'bar_tag_class'], array_keys($factories->getValue($locator)));
        self::assertSame($container->get(BarTagClass::class), $locator->get('bar_tag_class'));
        self::assertSame($container->get(FooTagClass::class), $locator->get('foo_tag_class'));
    }

    public function testNestedDefinitionWithAutoconfiguredConstructorArgument()
    {
        $container = new ContainerBuilder();
        $container->register(FooTagClass::class)
            ->setPublic(true)
            ->addTag('foo_bar', ['foo' => 'foo'])
        ;
        $container->register(LocatorConsumerConsumer::class)
            ->setPublic(true)
            ->setArguments([
                (new Definition(LocatorConsumer::class))
                    ->setAutowired(true),
            ])
        ;

        $container->compile();

        /** @var LocatorConsumerConsumer $s */
        $s = $container->get(LocatorConsumerConsumer::class);

        $locator = $s->getLocatorConsumer()->getLocator();
        self::assertSame($container->get(FooTagClass::class), $locator->get('foo'));
    }

    public function testFactoryWithAutoconfiguredArgument()
    {
        $container = new ContainerBuilder();
        $container->register(FooTagClass::class)
            ->setPublic(true)
            ->addTag('foo_bar', ['key' => 'my_service'])
        ;
        $container->register(LocatorConsumerFactory::class);
        $container->register(LocatorConsumer::class)
            ->setPublic(true)
            ->setAutowired(true)
            ->setFactory(new Reference(LocatorConsumerFactory::class))
        ;

        $container->compile();

        /** @var LocatorConsumer $s */
        $s = $container->get(LocatorConsumer::class);

        $locator = $s->getLocator();
        self::assertSame($container->get(FooTagClass::class), $locator->get('my_service'));
    }

    public function testTaggedServiceWithDefaultPriorityMethod()
    {
        $container = new ContainerBuilder();
        $container->register(BarTagClass::class)
            ->setPublic(true)
            ->addTag('foo_bar')
        ;
        $container->register(FooTagClass::class)
            ->setPublic(true)
            ->addTag('foo_bar', ['foo' => 'foo'])
        ;
        $container->register(FooBarTaggedForDefaultPriorityClass::class)
            ->addArgument(new TaggedIteratorArgument('foo_bar', null, null, false, 'getPriority'))
            ->setPublic(true)
        ;

        $container->compile();

        $s = $container->get(FooBarTaggedForDefaultPriorityClass::class);

        $param = iterator_to_array($s->getParam()->getIterator());
        $this->assertSame([$container->get(FooTagClass::class), $container->get(BarTagClass::class)], $param);
    }

    public function testTaggedServiceLocatorWithIndexAttribute()
    {
        $container = new ContainerBuilder();
        $container->register('bar_tag', BarTagClass::class)
            ->setPublic(true)
            ->addTag('foo_bar', ['foo' => 'bar'])
        ;
        $container->register('foo_tag', FooTagClass::class)
            ->setPublic(true)
            ->addTag('foo_bar')
        ;
        $container->register('foo_bar_tagged', FooBarTaggedClass::class)
            ->addArgument(new ServiceLocatorArgument(new TaggedIteratorArgument('foo_bar', 'foo', null, true)))
            ->setPublic(true)
        ;

        $container->compile();

        $s = $container->get('foo_bar_tagged');

        /** @var ServiceLocator $serviceLocator */
        $serviceLocator = $s->getParam();
        $this->assertTrue($s->getParam() instanceof ServiceLocator, sprintf('Wrong instance, should be an instance of ServiceLocator, %s given', get_debug_type($serviceLocator)));

        $same = [
            'bar' => $serviceLocator->get('bar'),
            'foo_tag_class' => $serviceLocator->get('foo_tag_class'),
        ];
        $this->assertSame(['bar' => $container->get('bar_tag'), 'foo_tag_class' => $container->get('foo_tag')], $same);
    }

    public function testTaggedServiceLocatorWithMultipleIndexAttribute()
    {
        $container = new ContainerBuilder();
        $container->register('bar_tag', BarTagClass::class)
            ->setPublic(true)
            ->addTag('foo_bar', ['foo' => 'bar'])
            ->addTag('foo_bar', ['foo' => 'bar_duplicate'])
        ;
        $container->register('foo_tag', FooTagClass::class)
            ->setPublic(true)
            ->addTag('foo_bar')
            ->addTag('foo_bar')
        ;
        $container->register('foo_bar_tagged', FooBarTaggedClass::class)
            ->addArgument(new ServiceLocatorArgument(new TaggedIteratorArgument('foo_bar', 'foo', null, true)))
            ->setPublic(true)
        ;

        $container->compile();

        $s = $container->get('foo_bar_tagged');

        /** @var ServiceLocator $serviceLocator */
        $serviceLocator = $s->getParam();
        $this->assertTrue($s->getParam() instanceof ServiceLocator, sprintf('Wrong instance, should be an instance of ServiceLocator, %s given', get_debug_type($serviceLocator)));

        $same = [
            'bar' => $serviceLocator->get('bar'),
            'bar_duplicate' => $serviceLocator->get('bar_duplicate'),
            'foo_tag_class' => $serviceLocator->get('foo_tag_class'),
        ];
        $this->assertSame(['bar' => $container->get('bar_tag'), 'bar_duplicate' => $container->get('bar_tag'), 'foo_tag_class' => $container->get('foo_tag')], $same);
    }

    public function testTaggedServiceLocatorWithIndexAttributeAndDefaultMethod()
    {
        $container = new ContainerBuilder();
        $container->register('bar_tag', BarTagClass::class)
            ->setPublic(true)
            ->addTag('foo_bar')
        ;
        $container->register('foo_tag', FooTagClass::class)
            ->setPublic(true)
            ->addTag('foo_bar', ['foo' => 'foo'])
        ;
        $container->register('foo_bar_tagged', FooBarTaggedClass::class)
            ->addArgument(new ServiceLocatorArgument(new TaggedIteratorArgument('foo_bar', 'foo', 'getFooBar', true)))
            ->setPublic(true)
        ;

        $container->compile();

        $s = $container->get('foo_bar_tagged');

        /** @var ServiceLocator $serviceLocator */
        $serviceLocator = $s->getParam();
        $this->assertTrue($s->getParam() instanceof ServiceLocator, sprintf('Wrong instance, should be an instance of ServiceLocator, %s given', get_debug_type($serviceLocator)));

        $same = [
            'bar_tab_class_with_defaultmethod' => $serviceLocator->get('bar_tab_class_with_defaultmethod'),
            'foo' => $serviceLocator->get('foo'),
        ];
        $this->assertSame(['bar_tab_class_with_defaultmethod' => $container->get('bar_tag'), 'foo' => $container->get('foo_tag')], $same);
    }

    public function testTaggedServiceLocatorWithFallback()
    {
        $container = new ContainerBuilder();
        $container->register('bar_tag', BarTagClass::class)
            ->setPublic(true)
            ->addTag('foo_bar')
        ;
        $container->register('foo_bar_tagged', FooBarTaggedClass::class)
            ->addArgument(new ServiceLocatorArgument(new TaggedIteratorArgument('foo_bar', null, null, true)))
            ->setPublic(true)
        ;

        $container->compile();

        $s = $container->get('foo_bar_tagged');

        /** @var ServiceLocator $serviceLocator */
        $serviceLocator = $s->getParam();
        $this->assertTrue($s->getParam() instanceof ServiceLocator, sprintf('Wrong instance, should be an instance of ServiceLocator, %s given', get_debug_type($serviceLocator)));

        $expected = [
            'bar_tag' => $container->get('bar_tag'),
        ];
        $this->assertSame($expected, ['bar_tag' => $serviceLocator->get('bar_tag')]);
    }

    public function testTaggedServiceLocatorWithDefaultIndex()
    {
        $container = new ContainerBuilder();
        $container->register('bar_tag', BarTagClass::class)
            ->setPublic(true)
            ->addTag('app.foo_bar', ['foo_bar' => 'baz'])
        ;
        $container->register('foo_bar_tagged', FooBarTaggedClass::class)
            ->addArgument(new ServiceLocatorArgument(new TaggedIteratorArgument('app.foo_bar', null, null, true)))
            ->setPublic(true)
        ;

        $container->compile();

        $s = $container->get('foo_bar_tagged');

        /** @var ServiceLocator $serviceLocator */
        $serviceLocator = $s->getParam();
        $this->assertTrue($s->getParam() instanceof ServiceLocator, sprintf('Wrong instance, should be an instance of ServiceLocator, %s given', get_debug_type($serviceLocator)));

        $expected = [
            'baz' => $container->get('bar_tag'),
        ];
        $this->assertSame($expected, ['baz' => $serviceLocator->get('baz')]);
    }

    public function testTagsViaAttribute()
    {
        $container = new ContainerBuilder();
        $container->registerAttributeForAutoconfiguration(
            CustomAutoconfiguration::class,
            static function (ChildDefinition $definition, CustomAutoconfiguration $attribute, \ReflectionClass $reflector) {
                $definition->addTag('app.custom_tag', get_object_vars($attribute) + ['class' => $reflector->getName()]);
            }
        );

        $container->register('one', TaggedService1::class)
            ->setPublic(true)
            ->setAutoconfigured(true);
        $container->register('two', TaggedService2::class)
            ->addTag('app.custom_tag', ['info' => 'This tag is not autoconfigured'])
            ->setPublic(true)
            ->setAutoconfigured(true);

        $collector = new TagCollector();
        $container->addCompilerPass($collector);

        $container->compile();

        self::assertSame([
            'one' => [
                ['someAttribute' => 'one', 'priority' => 0, 'class' => TaggedService1::class],
                ['someAttribute' => 'two', 'priority' => 0, 'class' => TaggedService1::class],
            ],
            'two' => [
                ['info' => 'This tag is not autoconfigured'],
                ['someAttribute' => 'prio 100', 'priority' => 100, 'class' => TaggedService2::class],
            ],
        ], $collector->collectedTags);
    }

    public function testAttributesAreIgnored()
    {
        $container = new ContainerBuilder();
        $container->registerAttributeForAutoconfiguration(
            CustomAutoconfiguration::class,
            static function (Definition $definition, CustomAutoconfiguration $attribute) {
                $definition->addTag('app.custom_tag', get_object_vars($attribute));
            }
        );

        $container->register('one', TaggedService1::class)
            ->setPublic(true)
            ->addTag('container.ignore_attributes')
            ->setAutoconfigured(true);
        $container->register('two', TaggedService2::class)
            ->setPublic(true)
            ->setAutoconfigured(true);

        $collector = new TagCollector();
        $container->addCompilerPass($collector);

        $container->compile();

        self::assertSame([
            'two' => [
                ['someAttribute' => 'prio 100', 'priority' => 100],
            ],
        ], $collector->collectedTags);
    }

    public function testTagsViaAttributeOnPropertyMethodAndParameter()
    {
        $container = new ContainerBuilder();
        $container->registerAttributeForAutoconfiguration(
            CustomMethodAttribute::class,
            static function (ChildDefinition $definition, CustomMethodAttribute $attribute, \ReflectionMethod $reflector) {
                $tagAttributes = get_object_vars($attribute);
                $tagAttributes['method'] = $reflector->getName();

                $definition->addTag('app.custom_tag', $tagAttributes);
            }
        );
        $container->registerAttributeForAutoconfiguration(
            CustomPropertyAttribute::class,
            static function (ChildDefinition $definition, CustomPropertyAttribute $attribute, \ReflectionProperty $reflector) {
                $tagAttributes = get_object_vars($attribute);
                $tagAttributes['property'] = $reflector->getName();

                $definition->addTag('app.custom_tag', $tagAttributes);
            }
        );
        $container->registerAttributeForAutoconfiguration(
            CustomParameterAttribute::class,
            static function (ChildDefinition $definition, CustomParameterAttribute $attribute, \ReflectionParameter $reflector) {
                $tagAttributes = get_object_vars($attribute);
                $tagAttributes['parameter'] = $reflector->getName();

                $definition->addTag('app.custom_tag', $tagAttributes);
            }
        );
        $container->registerAttributeForAutoconfiguration(
            CustomAnyAttribute::class,
            static function (ChildDefinition $definition, CustomAnyAttribute $attribute, \ReflectionClass|\ReflectionMethod|\ReflectionProperty|\ReflectionParameter $reflector) {
                $tagAttributes = get_object_vars($attribute);
                if ($reflector instanceof \ReflectionClass) {
                    $tagAttributes['class'] = $reflector->getName();
                } elseif ($reflector instanceof \ReflectionMethod) {
                    $tagAttributes['method'] = $reflector->getName();
                } elseif ($reflector instanceof \ReflectionProperty) {
                    $tagAttributes['property'] = $reflector->getName();
                } elseif ($reflector instanceof \ReflectionParameter) {
                    $tagAttributes['parameter'] = $reflector->getName();
                }

                $definition->addTag('app.custom_tag', $tagAttributes);
            }
        );

        $container->register(TaggedService4::class)
            ->setPublic(true)
            ->setAutoconfigured(true);

        $container->register('failing_factory', \stdClass::class);
        $container->register('ccc', TaggedService4::class)
            ->setFactory([new Reference('failing_factory'), 'create'])
            ->setAutoconfigured(true);

        $collector = new TagCollector();
        $container->addCompilerPass($collector);

        $container->compile();

        self::assertSame([
            TaggedService4::class => [
                ['class' => TaggedService4::class],
                ['parameter' => 'param1'],
                ['someAttribute' => 'on param1 in constructor', 'priority' => 0, 'parameter' => 'param1'],
                ['parameter' => 'param2'],
                ['someAttribute' => 'on param2 in constructor', 'priority' => 0, 'parameter' => 'param2'],
                ['method' => 'fooAction'],
                ['someAttribute' => 'on fooAction', 'priority' => 0, 'method' => 'fooAction'],
                ['someAttribute' => 'on param1 in fooAction', 'priority' => 0, 'parameter' => 'param1'],
                ['method' => 'barAction'],
                ['someAttribute' => 'on barAction', 'priority' => 0, 'method' => 'barAction'],
                ['property' => 'name'],
                ['someAttribute' => 'on name', 'priority' => 0, 'property' => 'name'],
            ],
            'ccc' => [
                ['class' => TaggedService4::class],
                ['method' => 'fooAction'],
                ['someAttribute' => 'on fooAction', 'priority' => 0, 'method' => 'fooAction'],
                ['parameter' => 'param1'],
                ['someAttribute' => 'on param1 in fooAction', 'priority' => 0, 'parameter' => 'param1'],
                ['method' => 'barAction'],
                ['someAttribute' => 'on barAction', 'priority' => 0, 'method' => 'barAction'],
                ['property' => 'name'],
                ['someAttribute' => 'on name', 'priority' => 0, 'property' => 'name'],
            ],
        ], $collector->collectedTags);
    }

    public function testAutoconfigureViaAttribute()
    {
        $container = new ContainerBuilder();
        $container->registerAttributeForAutoconfiguration(
            CustomAutoconfiguration::class,
            static function (ChildDefinition $definition) {
                $definition
                    ->addMethodCall('doSomething', [1, 2, 3])
                    ->setBindings(['string $foo' => 'bar'])
                    ->setConfigurator(new Reference('my_configurator'))
                ;
            }
        );

        $container->register('my_configurator', TaggedService3Configurator::class);
        $container->register('three', TaggedService3::class)
            ->setPublic(true)
            ->setAutoconfigured(true);

        $container->compile();

        /** @var TaggedService3 $service */
        $service = $container->get('three');

        self::assertSame('bar', $service->foo);
        self::assertSame(6, $service->sum);
        self::assertTrue($service->hasBeenConfigured);
    }

    public function testAttributeAutoconfigurationOnStaticMethod()
    {
        $container = new ContainerBuilder();
        $container->registerAttributeForAutoconfiguration(
            CustomMethodAttribute::class,
            static function (ChildDefinition $d, CustomMethodAttribute $a, \ReflectionMethod $_r) {
                $d->addTag('custom_tag', ['attribute' => $a->someAttribute]);
            }
        );

        $container->register('service', StaticMethodTag::class)
            ->setPublic(true)
            ->setAutoconfigured(true);

        $container->compile();

        $definition = $container->getDefinition('service');
        self::assertEquals([['attribute' => 'static']], $definition->getTag('custom_tag'));

        $container->get('service');
    }

    public function testTaggedIteratorAndLocatorWithExclude()
    {
        $container = new ContainerBuilder();

        $container->register(AutoconfiguredService1::class)
            ->addTag(AutoconfiguredInterface2::class)
            ->setPublic(true)
        ;
        $container->register(AutoconfiguredService2::class)
            ->addTag(AutoconfiguredInterface2::class)
            ->setPublic(true)
        ;
        $container->register(TaggedConsumerWithExclude::class)
            ->addTag(AutoconfiguredInterface2::class)
            ->setAutoconfigured(true)
            ->setAutowired(true)
            ->setPublic(true)
        ;

        $container->compile();

        $this->assertTrue($container->getDefinition(AutoconfiguredService1::class)->hasTag(AutoconfiguredInterface2::class));
        $this->assertTrue($container->getDefinition(AutoconfiguredService2::class)->hasTag(AutoconfiguredInterface2::class));
        $this->assertTrue($container->getDefinition(TaggedConsumerWithExclude::class)->hasTag(AutoconfiguredInterface2::class));

        $s = $container->get(TaggedConsumerWithExclude::class);

        $items = iterator_to_array($s->items->getIterator());
        $this->assertCount(2, $items);
        $this->assertInstanceOf(AutoconfiguredService1::class, $items[0]);
        $this->assertInstanceOf(AutoconfiguredService2::class, $items[1]);

        $locator = $s->locator;
        $this->assertTrue($locator->has(AutoconfiguredService1::class));
        $this->assertTrue($locator->has(AutoconfiguredService2::class));
        $this->assertFalse($locator->has(TaggedConsumerWithExclude::class));
    }
}

class ServiceSubscriberStub implements ServiceSubscriberInterface
{
    public $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public static function getSubscribedServices(): array
    {
        return [];
    }
}

class DecoratedServiceSubscriber
{
    public $inner;
}

class DecoratedServiceLocator implements ServiceProviderInterface
{
    /**
     * @var ServiceLocator
     */
    private $locator;

    public function __construct(ServiceLocator $locator)
    {
        $this->locator = $locator;
    }

    public function get($id): mixed
    {
        return $this->locator->get($id);
    }

    public function has($id): bool
    {
        return $this->locator->has($id);
    }

    public function getProvidedServices(): array
    {
        return $this->locator->getProvidedServices();
    }
}

class IntegrationTestStub extends IntegrationTestStubParent
{
}

class IntegrationTestStubParent
{
    public function enableSummer($enable)
    {
        // methods used in calls - added here to prevent errors for not existing
    }

    public function setSunshine($type)
    {
    }
}

final class TagCollector implements CompilerPassInterface
{
    public $collectedTags;

    public function process(ContainerBuilder $container): void
    {
        $this->collectedTags = $container->findTaggedServiceIds('app.custom_tag');
    }
}
