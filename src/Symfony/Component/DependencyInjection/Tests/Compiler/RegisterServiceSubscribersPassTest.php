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
use Psr\Container\ContainerInterface as PsrContainerInterface;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\DependencyInjection\Attribute\TaggedLocator;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\DependencyInjection\Compiler\AutowirePass;
use Symfony\Component\DependencyInjection\Compiler\RegisterServiceSubscribersPass;
use Symfony\Component\DependencyInjection\Compiler\ResolveBindingsPass;
use Symfony\Component\DependencyInjection\Compiler\ResolveServiceSubscribersPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition;
use Symfony\Component\DependencyInjection\Tests\Fixtures\TestDefinition1;
use Symfony\Component\DependencyInjection\Tests\Fixtures\TestDefinition2;
use Symfony\Component\DependencyInjection\Tests\Fixtures\TestDefinition3;
use Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber;
use Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriberChild;
use Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriberIntersection;
use Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriberIntersectionWithTrait;
use Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriberParent;
use Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriberUnion;
use Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriberUnionWithTrait;
use Symfony\Component\DependencyInjection\TypedReference;
use Symfony\Contracts\Service\Attribute\SubscribedService;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

require_once __DIR__.'/../Fixtures/includes/classes.php';

class RegisterServiceSubscribersPassTest extends TestCase
{
    public function testInvalidClass()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Service "foo" must implement interface "Symfony\Contracts\Service\ServiceSubscriberInterface".');
        $container = new ContainerBuilder();

        $container->register('foo', CustomDefinition::class)
            ->addTag('container.service_subscriber')
        ;

        (new RegisterServiceSubscribersPass())->process($container);
        (new ResolveServiceSubscribersPass())->process($container);
    }

    public function testInvalidAttributes()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "container.service_subscriber" tag accepts only the "key" and "id" attributes, "bar" given for service "foo".');
        $container = new ContainerBuilder();

        $container->register('foo', TestServiceSubscriber::class)
            ->addTag('container.service_subscriber', ['bar' => '123'])
        ;

        (new RegisterServiceSubscribersPass())->process($container);
        (new ResolveServiceSubscribersPass())->process($container);
    }

    public function testNoAttributes()
    {
        $container = new ContainerBuilder();

        $container->register('foo', TestServiceSubscriber::class)
            ->addArgument(new Reference(PsrContainerInterface::class))
            ->addTag('container.service_subscriber')
        ;

        (new RegisterServiceSubscribersPass())->process($container);
        (new ResolveServiceSubscribersPass())->process($container);

        $foo = $container->getDefinition('foo');
        $locator = $container->getDefinition((string) $foo->getArgument(0));

        $this->assertFalse($locator->isPublic());
        $this->assertSame(ServiceLocator::class, $locator->getClass());

        $expected = [
            TestServiceSubscriber::class => new ServiceClosureArgument(new TypedReference(TestServiceSubscriber::class, TestServiceSubscriber::class)),
            CustomDefinition::class => new ServiceClosureArgument(new TypedReference(CustomDefinition::class, CustomDefinition::class, ContainerInterface::IGNORE_ON_INVALID_REFERENCE)),
            'bar' => new ServiceClosureArgument(new TypedReference(CustomDefinition::class, CustomDefinition::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, 'bar')),
            'baz' => new ServiceClosureArgument(new TypedReference(CustomDefinition::class, CustomDefinition::class, ContainerInterface::IGNORE_ON_INVALID_REFERENCE, 'baz')),
            'late_alias' => new ServiceClosureArgument(new TypedReference(TestDefinition1::class, TestDefinition1::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, 'late_alias')),
        ];

        $this->assertEquals($expected, $container->getDefinition((string) $locator->getFactory()[0])->getArgument(0));
    }

    public function testWithAttributes()
    {
        $container = new ContainerBuilder();

        $container->register('foo', TestServiceSubscriber::class)
            ->setAutowired(true)
            ->addArgument(new Reference(PsrContainerInterface::class))
            ->addTag('container.service_subscriber', ['key' => 'bar', 'id' => 'bar'])
            ->addTag('container.service_subscriber', ['key' => 'bar', 'id' => 'baz']) // should be ignored: the first wins
        ;

        (new RegisterServiceSubscribersPass())->process($container);
        (new ResolveServiceSubscribersPass())->process($container);

        $foo = $container->getDefinition('foo');
        $locator = $container->getDefinition((string) $foo->getArgument(0));

        $this->assertFalse($locator->isPublic());
        $this->assertSame(ServiceLocator::class, $locator->getClass());

        $expected = [
            TestServiceSubscriber::class => new ServiceClosureArgument(new TypedReference(TestServiceSubscriber::class, TestServiceSubscriber::class)),
            CustomDefinition::class => new ServiceClosureArgument(new TypedReference(CustomDefinition::class, CustomDefinition::class, ContainerInterface::IGNORE_ON_INVALID_REFERENCE)),
            'bar' => new ServiceClosureArgument(new TypedReference('bar', CustomDefinition::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, 'bar')),
            'baz' => new ServiceClosureArgument(new TypedReference(CustomDefinition::class, CustomDefinition::class, ContainerInterface::IGNORE_ON_INVALID_REFERENCE, 'baz')),
            'late_alias' => new ServiceClosureArgument(new TypedReference(TestDefinition1::class, TestDefinition1::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, 'late_alias')),
        ];

        $this->assertEquals($expected, $container->getDefinition((string) $locator->getFactory()[0])->getArgument(0));
    }

    public function testUnionServices()
    {
        $container = new ContainerBuilder();

        $container->register('bar', \stdClass::class);
        $container->setAlias(TestDefinition1::class, 'bar');
        $container->setAlias(TestDefinition2::class, 'bar');
        $container->register('foo', TestServiceSubscriberUnion::class)
            ->addArgument(new Reference(PsrContainerInterface::class))
            ->addTag('container.service_subscriber')
        ;

        (new RegisterServiceSubscribersPass())->process($container);
        (new ResolveServiceSubscribersPass())->process($container);

        $foo = $container->getDefinition('foo');
        $locator = $container->getDefinition((string) $foo->getArgument(0));

        $this->assertFalse($locator->isPublic());
        $this->assertSame(ServiceLocator::class, $locator->getClass());

        $expected = [
            'string|'.TestDefinition2::class.'|'.TestDefinition1::class => new ServiceClosureArgument(new TypedReference('string|'.TestDefinition2::class.'|'.TestDefinition1::class, 'string|'.TestDefinition2::class.'|'.TestDefinition1::class)),
            'bar' => new ServiceClosureArgument(new TypedReference(TestDefinition1::class.'|'.TestDefinition2::class, TestDefinition1::class.'|'.TestDefinition2::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, 'bar')),
            'baz' => new ServiceClosureArgument(new TypedReference(TestDefinition1::class.'|'.TestDefinition2::class, TestDefinition1::class.'|'.TestDefinition2::class, ContainerInterface::IGNORE_ON_INVALID_REFERENCE, 'baz')),
        ];

        $this->assertEquals($expected, $container->getDefinition((string) $locator->getFactory()[0])->getArgument(0));

        (new AutowirePass())->process($container);

        $expected = [
            'string|'.TestDefinition2::class.'|'.TestDefinition1::class => new ServiceClosureArgument(new TypedReference('bar', TestDefinition1::class.'|'.TestDefinition2::class)),
            'bar' => new ServiceClosureArgument(new TypedReference('bar', TestDefinition1::class.'|'.TestDefinition2::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, 'bar')),
            'baz' => new ServiceClosureArgument(new TypedReference('bar', TestDefinition1::class.'|'.TestDefinition2::class, ContainerInterface::IGNORE_ON_INVALID_REFERENCE, 'baz')),
        ];
        $this->assertEquals($expected, $container->getDefinition((string) $locator->getFactory()[0])->getArgument(0));
    }

    public function testIntersectionServices()
    {
        $container = new ContainerBuilder();

        $container->register('foo', TestServiceSubscriberIntersection::class)
            ->addArgument(new Reference(PsrContainerInterface::class))
            ->addTag('container.service_subscriber')
        ;

        (new RegisterServiceSubscribersPass())->process($container);
        (new ResolveServiceSubscribersPass())->process($container);

        $foo = $container->getDefinition('foo');
        $locator = $container->getDefinition((string) $foo->getArgument(0));

        $this->assertFalse($locator->isPublic());
        $this->assertSame(ServiceLocator::class, $locator->getClass());

        $expected = [
            TestDefinition1::class.'&'.TestDefinition2::class => new ServiceClosureArgument(new TypedReference(TestDefinition1::class.'&'.TestDefinition2::class, TestDefinition1::class.'&'.TestDefinition2::class)),
            'bar' => new ServiceClosureArgument(new TypedReference(TestDefinition1::class.'&'.TestDefinition2::class, TestDefinition1::class.'&'.TestDefinition2::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, 'bar')),
            'baz' => new ServiceClosureArgument(new TypedReference(TestDefinition1::class.'&'.TestDefinition2::class, TestDefinition1::class.'&'.TestDefinition2::class, ContainerInterface::IGNORE_ON_INVALID_REFERENCE, 'baz')),
        ];

        $this->assertEquals($expected, $container->getDefinition((string) $locator->getFactory()[0])->getArgument(0));
    }

    public function testExtraServiceSubscriber()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Service key "test" does not exist in the map returned by "Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber::getSubscribedServices()" for service "foo_service".');
        $container = new ContainerBuilder();
        $container->register('foo_service', TestServiceSubscriber::class)
            ->setAutowired(true)
            ->addArgument(new Reference(PsrContainerInterface::class))
            ->addTag('container.service_subscriber', [
                'key' => 'test',
                'id' => TestServiceSubscriber::class,
            ])
        ;
        $container->register(TestServiceSubscriber::class, TestServiceSubscriber::class);
        $container->compile();
    }

    public function testServiceSubscriberTraitWithSubscribedServiceAttribute()
    {
        if (!class_exists(SubscribedService::class)) {
            $this->markTestSkipped('SubscribedService attribute not available.');
        }

        $container = new ContainerBuilder();

        $container->register('foo', TestServiceSubscriberChild::class)
            ->addMethodCall('setContainer', [new Reference(PsrContainerInterface::class)])
            ->addTag('container.service_subscriber')
        ;

        (new RegisterServiceSubscribersPass())->process($container);
        (new ResolveServiceSubscribersPass())->process($container);

        $foo = $container->getDefinition('foo');
        $locator = $container->getDefinition((string) $foo->getMethodCalls()[0][1][0]);

        $expected = [
            TestServiceSubscriberChild::class.'::invalidDefinition' => new ServiceClosureArgument(new TypedReference('Symfony\Component\DependencyInjection\Tests\Fixtures\InvalidDefinition', 'Symfony\Component\DependencyInjection\Tests\Fixtures\InvalidDefinition')),
            TestServiceSubscriberChild::class.'::testDefinition2' => new ServiceClosureArgument(new TypedReference(TestDefinition2::class, TestDefinition2::class, ContainerInterface::IGNORE_ON_INVALID_REFERENCE)),
            TestServiceSubscriberChild::class.'::testDefinition4' => new ServiceClosureArgument(new TypedReference(TestDefinition3::class, TestDefinition3::class)),
            TestServiceSubscriberParent::class.'::testDefinition1' => new ServiceClosureArgument(new TypedReference(TestDefinition1::class, TestDefinition1::class)),
            'custom_name' => new ServiceClosureArgument(new TypedReference(TestDefinition3::class, TestDefinition3::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, 'custom_name')),
        ];

        $this->assertEquals($expected, $container->getDefinition((string) $locator->getFactory()[0])->getArgument(0));
    }

    public function testServiceSubscriberTraitWithSubscribedServiceAttributeOnStaticMethod()
    {
        if (!class_exists(SubscribedService::class)) {
            $this->markTestSkipped('SubscribedService attribute not available.');
        }

        $subscriber = new class() implements ServiceSubscriberInterface {
            use ServiceSubscriberTrait;

            #[SubscribedService]
            public static function method(): TestDefinition1
            {
            }
        };

        $this->expectException(\LogicException::class);

        $subscriber::getSubscribedServices();
    }

    public function testServiceSubscriberTraitWithSubscribedServiceAttributeOnMethodWithRequiredParameters()
    {
        if (!class_exists(SubscribedService::class)) {
            $this->markTestSkipped('SubscribedService attribute not available.');
        }

        $subscriber = new class() implements ServiceSubscriberInterface {
            use ServiceSubscriberTrait;

            #[SubscribedService]
            public function method($param1, $param2 = null): TestDefinition1
            {
            }
        };

        $this->expectException(\LogicException::class);

        $subscriber::getSubscribedServices();
    }

    public function testServiceSubscriberTraitWithSubscribedServiceAttributeOnMethodMissingReturnType()
    {
        if (!class_exists(SubscribedService::class)) {
            $this->markTestSkipped('SubscribedService attribute not available.');
        }

        $subscriber = new class() implements ServiceSubscriberInterface {
            use ServiceSubscriberTrait;

            #[SubscribedService]
            public function method()
            {
            }
        };

        $this->expectException(\LogicException::class);

        $subscriber::getSubscribedServices();
    }

    public function testServiceSubscriberTraitWithUnionReturnType()
    {
        if (!class_exists(SubscribedService::class)) {
            $this->markTestSkipped('SubscribedService attribute not available.');
        }

        $container = new ContainerBuilder();

        $container->register('foo', TestServiceSubscriberUnionWithTrait::class)
            ->addMethodCall('setContainer', [new Reference(PsrContainerInterface::class)])
            ->addTag('container.service_subscriber')
        ;

        (new RegisterServiceSubscribersPass())->process($container);
        (new ResolveServiceSubscribersPass())->process($container);

        $foo = $container->getDefinition('foo');
        $locator = $container->getDefinition((string) $foo->getMethodCalls()[0][1][0]);

        $expected = [
            TestServiceSubscriberUnionWithTrait::class.'::method1' => new ServiceClosureArgument(new TypedReference(TestDefinition1::class.'|'.TestDefinition2::class.'|null', TestDefinition1::class.'|'.TestDefinition2::class.'|null', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)),
            TestServiceSubscriberUnionWithTrait::class.'::method2' => new ServiceClosureArgument(new TypedReference(TestDefinition1::class.'|'.TestDefinition2::class, TestDefinition1::class.'|'.TestDefinition2::class)),
        ];

        $this->assertEquals($expected, $container->getDefinition((string) $locator->getFactory()[0])->getArgument(0));
    }

    public function testServiceSubscriberTraitWithIntersectionReturnType()
    {
        if (!class_exists(SubscribedService::class)) {
            $this->markTestSkipped('SubscribedService attribute not available.');
        }

        $container = new ContainerBuilder();

        $container->register('foo', TestServiceSubscriberIntersectionWithTrait::class)
            ->addMethodCall('setContainer', [new Reference(PsrContainerInterface::class)])
            ->addTag('container.service_subscriber')
        ;

        (new RegisterServiceSubscribersPass())->process($container);
        (new ResolveServiceSubscribersPass())->process($container);

        $foo = $container->getDefinition('foo');
        $locator = $container->getDefinition((string) $foo->getMethodCalls()[0][1][0]);

        $expected = [
            TestServiceSubscriberIntersectionWithTrait::class.'::method1' => new ServiceClosureArgument(new TypedReference(TestDefinition1::class.'&'.TestDefinition2::class, TestDefinition1::class.'&'.TestDefinition2::class)),
        ];

        $this->assertEquals($expected, $container->getDefinition((string) $locator->getFactory()[0])->getArgument(0));
    }

    public function testServiceSubscriberWithSemanticId()
    {
        $container = new ContainerBuilder();

        $subscriber = new class() implements ServiceSubscriberInterface {
            public static function getSubscribedServices(): array
            {
                return [
                    'some.service' => 'stdClass',
                    'some_service' => 'stdClass',
                    'another_service' => 'stdClass',
                ];
            }
        };
        $container->register('some.service', 'stdClass');
        $container->setAlias('stdClass $someService', 'some.service');
        $container->setAlias('stdClass $some_service', 'some.service');
        $container->setAlias('stdClass $anotherService', 'some.service');
        $container->register('foo', $subscriber::class)
            ->addMethodCall('setContainer', [new Reference(PsrContainerInterface::class)])
            ->addTag('container.service_subscriber');

        (new RegisterServiceSubscribersPass())->process($container);
        (new ResolveServiceSubscribersPass())->process($container);

        $foo = $container->getDefinition('foo');
        $locator = $container->getDefinition((string) $foo->getMethodCalls()[0][1][0]);

        $expected = [
            'some.service' => new ServiceClosureArgument(new TypedReference('stdClass', 'stdClass', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, 'some.service')),
            'some_service' => new ServiceClosureArgument(new TypedReference('stdClass', 'stdClass', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, 'some_service')),
            'another_service' => new ServiceClosureArgument(new TypedReference('stdClass', 'stdClass', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, 'anotherService')),
        ];
        $this->assertEquals($expected, $container->getDefinition((string) $locator->getFactory()[0])->getArgument(0));

        (new AutowirePass())->process($container);

        $expected = [
            'some.service' => new ServiceClosureArgument(new TypedReference('some.service', 'stdClass')),
            'some_service' => new ServiceClosureArgument(new TypedReference('stdClass $some_service', 'stdClass')),
            'another_service' => new ServiceClosureArgument(new TypedReference('stdClass $anotherService', 'stdClass')),
        ];
        $this->assertEquals($expected, $container->getDefinition((string) $locator->getFactory()[0])->getArgument(0));
    }

    public function testSubscribedServiceWithAttributes()
    {
        if (!property_exists(SubscribedService::class, 'attributes')) {
            $this->markTestSkipped('Requires symfony/service-contracts 3.2+');
        }

        $container = new ContainerBuilder();

        $subscriber = new class() implements ServiceSubscriberInterface {
            public static function getSubscribedServices(): array
            {
                return [
                    new SubscribedService('tagged.iterator', 'iterable', attributes: new TaggedIterator('tag')),
                    new SubscribedService('tagged.locator', PsrContainerInterface::class, attributes: new TaggedLocator('tag')),
                    new SubscribedService('autowired', 'stdClass', attributes: new Autowire(service: 'service.id')),
                    new SubscribedService('autowired.nullable', 'stdClass', nullable: true, attributes: new Autowire(service: 'service.id')),
                    new SubscribedService('autowired.parameter', 'string', attributes: new Autowire('%parameter.1%')),
                    new SubscribedService('autowire.decorated', \stdClass::class, attributes: new AutowireDecorated()),
                    new SubscribedService('target', \stdClass::class, attributes: new Target('someTarget')),
                ];
            }
        };

        $container->setParameter('parameter.1', 'foobar');
        $container->register('foo', $subscriber::class)
            ->addMethodCall('setContainer', [new Reference(PsrContainerInterface::class)])
            ->addTag('container.service_subscriber');

        (new RegisterServiceSubscribersPass())->process($container);
        (new ResolveServiceSubscribersPass())->process($container);

        $foo = $container->getDefinition('foo');
        $locator = $container->getDefinition((string) $foo->getMethodCalls()[0][1][0]);

        $expected = [
            'tagged.iterator' => new ServiceClosureArgument(new TypedReference('iterable', 'iterable', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, 'tagged.iterator', [new TaggedIterator('tag')])),
            'tagged.locator' => new ServiceClosureArgument(new TypedReference(PsrContainerInterface::class, PsrContainerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, 'tagged.locator', [new TaggedLocator('tag')])),
            'autowired' => new ServiceClosureArgument(new TypedReference('stdClass', 'stdClass', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, 'autowired', [new Autowire(service: 'service.id')])),
            'autowired.nullable' => new ServiceClosureArgument(new TypedReference('stdClass', 'stdClass', ContainerInterface::IGNORE_ON_INVALID_REFERENCE, 'autowired.nullable', [new Autowire(service: 'service.id')])),
            'autowired.parameter' => new ServiceClosureArgument(new TypedReference('string', 'string', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, 'autowired.parameter', [new Autowire(service: '%parameter.1%')])),
            'autowire.decorated' => new ServiceClosureArgument(new TypedReference('stdClass', 'stdClass', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, 'autowire.decorated', [new AutowireDecorated()])),
            'target' => new ServiceClosureArgument(new TypedReference('stdClass', 'stdClass', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, 'target', [new Target('someTarget')])),
        ];
        $this->assertEquals($expected, $container->getDefinition((string) $locator->getFactory()[0])->getArgument(0));

        (new AutowirePass())->process($container);

        $expected = [
            'tagged.iterator' => new ServiceClosureArgument(new TaggedIteratorArgument('tag')),
            'tagged.locator' => new ServiceClosureArgument(new ServiceLocatorArgument(new TaggedIteratorArgument('tag', 'tag', needsIndexes: true))),
            'autowired' => new ServiceClosureArgument(new TypedReference('service.id', 'stdClass', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, 'autowired', [new Autowire(service: 'service.id')])),
            'autowired.nullable' => new ServiceClosureArgument(new TypedReference('service.id', 'stdClass', ContainerInterface::IGNORE_ON_INVALID_REFERENCE, 'autowired.nullable', [new Autowire(service: 'service.id')])),
            'autowired.parameter' => new ServiceClosureArgument('foobar'),
            'autowire.decorated' => new ServiceClosureArgument(new Reference('.service_locator.0tSxobl.inner', ContainerInterface::NULL_ON_INVALID_REFERENCE)),
            'target' => new ServiceClosureArgument(new TypedReference('stdClass', 'stdClass', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, 'target', [new Target('someTarget')])),
        ];
        $this->assertEquals($expected, $container->getDefinition((string) $locator->getFactory()[0])->getArgument(0));
    }

    public function testBinding()
    {
        $container = new ContainerBuilder();

        $container->register('foo', TestServiceSubscriber::class)
            ->addMethodCall('setServiceProvider')
            ->addTag('container.service_subscriber')
        ;

        (new RegisterServiceSubscribersPass())->process($container);
        (new ResolveBindingsPass())->process($container);

        $foo = $container->getDefinition('foo');
        $locator = $container->getDefinition((string) $foo->getMethodCalls()[0][1][0]);

        $this->assertFalse($locator->isPublic());
        $this->assertSame(ServiceLocator::class, $locator->getClass());

        $expected = [
            TestServiceSubscriber::class => new ServiceClosureArgument(new TypedReference(TestServiceSubscriber::class, TestServiceSubscriber::class)),
            CustomDefinition::class => new ServiceClosureArgument(new TypedReference(CustomDefinition::class, CustomDefinition::class, ContainerInterface::IGNORE_ON_INVALID_REFERENCE)),
            'bar' => new ServiceClosureArgument(new TypedReference(CustomDefinition::class, CustomDefinition::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, 'bar')),
            'baz' => new ServiceClosureArgument(new TypedReference(CustomDefinition::class, CustomDefinition::class, ContainerInterface::IGNORE_ON_INVALID_REFERENCE, 'baz')),
            'late_alias' => new ServiceClosureArgument(new TypedReference(TestDefinition1::class, TestDefinition1::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, 'late_alias')),
        ];

        $this->assertEquals($expected, $container->getDefinition((string) $locator->getFactory()[0])->getArgument(0));
    }
}
