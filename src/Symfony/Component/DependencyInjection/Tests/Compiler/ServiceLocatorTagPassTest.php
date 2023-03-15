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
use Symfony\Component\DependencyInjection\Argument\BoundArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition;
use Symfony\Component\DependencyInjection\Tests\Fixtures\TestDefinition1;
use Symfony\Component\DependencyInjection\Tests\Fixtures\TestDefinition2;

require_once __DIR__.'/../Fixtures/includes/classes.php';

class ServiceLocatorTagPassTest extends TestCase
{
    public function testNoServices()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid definition for service "foo": an array of references is expected as first argument when the "container.service_locator" tag is set.');
        $container = new ContainerBuilder();

        $container->register('foo', ServiceLocator::class)
            ->addTag('container.service_locator')
        ;

        (new ServiceLocatorTagPass())->process($container);
    }

    public function testScalarServices()
    {
        $container = new ContainerBuilder();

        $container->register('foo', ServiceLocator::class)
            ->setArguments([[
                'dummy',
            ]])
            ->addTag('container.service_locator')
        ;

        (new ServiceLocatorTagPass())->process($container);

        $this->assertSame('dummy', $container->get('foo')->get(0));
    }

    public function testProcessValue()
    {
        $container = new ContainerBuilder();

        $container->register('bar', CustomDefinition::class);
        $container->register('baz', CustomDefinition::class);

        $container->register('foo', ServiceLocator::class)
            ->setArguments([[
                new Reference('bar'),
                new Reference('baz'),
                'some.service' => new Reference('bar'),
            ]])
            ->addTag('container.service_locator')
        ;

        (new ServiceLocatorTagPass())->process($container);

        /** @var ServiceLocator $locator */
        $locator = $container->get('foo');

        $this->assertSame(CustomDefinition::class, $locator('bar')::class);
        $this->assertSame(CustomDefinition::class, $locator('baz')::class);
        $this->assertSame(CustomDefinition::class, $locator('some.service')::class);
    }

    public function testServiceWithKeyOverwritesPreviousInheritedKey()
    {
        $container = new ContainerBuilder();

        $container->register('bar', TestDefinition1::class);
        $container->register('baz', TestDefinition2::class);

        $container->register('foo', ServiceLocator::class)
            ->setArguments([[
                new Reference('bar'),
                'bar' => new Reference('baz'),
            ]])
            ->addTag('container.service_locator')
        ;

        (new ServiceLocatorTagPass())->process($container);

        /** @var ServiceLocator $locator */
        $locator = $container->get('foo');

        $this->assertSame(TestDefinition2::class, $locator('bar')::class);
    }

    public function testInheritedKeyOverwritesPreviousServiceWithKey()
    {
        $container = new ContainerBuilder();

        $container->register('bar', TestDefinition1::class);
        $container->register('baz', TestDefinition2::class);

        $container->register('foo', ServiceLocator::class)
            ->setArguments([[
                'bar' => new Reference('baz'),
                new Reference('bar'),
                16 => new Reference('baz'),
            ]])
            ->addTag('container.service_locator')
        ;

        (new ServiceLocatorTagPass())->process($container);

        /** @var ServiceLocator $locator */
        $locator = $container->get('foo');

        $this->assertSame(TestDefinition1::class, $locator('bar')::class);
        $this->assertSame(TestDefinition2::class, $locator(16)::class);
    }

    public function testBindingsAreCopied()
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->setBindings(['foo' => 'foo']);

        $locator = ServiceLocatorTagPass::register($container, ['foo' => new Reference('foo')], 'foo');
        $locator = $container->getDefinition($locator);
        $locator = $container->getDefinition($locator->getFactory()[0]);

        $this->assertSame(['foo'], array_keys($locator->getBindings()));
        $this->assertInstanceOf(BoundArgument::class, $locator->getBindings()['foo']);
    }

    public function testTaggedServices()
    {
        $container = new ContainerBuilder();

        $container->register('bar', TestDefinition1::class)->addTag('test_tag');
        $container->register('baz', TestDefinition2::class)->addTag('test_tag');

        $container->register('foo', ServiceLocator::class)
            ->setArguments([new TaggedIteratorArgument('test_tag', null, null, true)])
            ->addTag('container.service_locator')
        ;

        (new ServiceLocatorTagPass())->process($container);

        /** @var ServiceLocator $locator */
        $locator = $container->get('foo');

        $this->assertSame(TestDefinition1::class, $locator('bar')::class);
        $this->assertSame(TestDefinition2::class, $locator('baz')::class);
    }

    public function testIndexedByServiceIdWithDecoration()
    {
        $container = new ContainerBuilder();

        $locator = new Definition(Locator::class);
        $locator->setPublic(true);
        $locator->addArgument(new ServiceLocatorArgument(new TaggedIteratorArgument('test_tag', null, null, true)));

        $container->setDefinition(Locator::class, $locator);

        $service = new Definition(Service::class);
        $service->setPublic(true);
        $service->addTag('test_tag');

        $container->setDefinition(Service::class, $service);

        $decorated = new Definition(DecoratedService::class);
        $decorated->setPublic(true);
        $decorated->setDecoratedService(Service::class);

        $container->setDefinition(DecoratedService::class, $decorated);

        $container->compile();

        /** @var ServiceLocator $locator */
        $locator = $container->get(Locator::class)->locator;
        static::assertTrue($locator->has(Service::class));
        static::assertFalse($locator->has(DecoratedService::class));
        static::assertInstanceOf(DecoratedService::class, $locator->get(Service::class));
    }

    public function testDefinitionOrderIsTheSame()
    {
        $container = new ContainerBuilder();
        $container->register('service-1');
        $container->register('service-2');

        $locator = ServiceLocatorTagPass::register($container, [
            'service-2' => new Reference('service-2'),
            'service-1' => new Reference('service-1'),
        ]);
        $locator = $container->getDefinition($locator);
        $factories = $locator->getArguments()[0];

        static::assertSame(['service-2', 'service-1'], array_keys($factories));
    }

    public function testBindingsAreProcessed()
    {
        $container = new ContainerBuilder();

        $definition = $container->register('foo')
            ->setBindings(['foo' => new ServiceLocatorArgument()]);

        (new ServiceLocatorTagPass())->process($container);

        $this->assertInstanceOf(Reference::class, $definition->getBindings()['foo']->getValues()[0]);
    }
}

class Locator
{
    /**
     * @var ServiceLocator
     */
    public $locator;

    public function __construct(ServiceLocator $locator)
    {
        $this->locator = $locator;
    }
}

class Service
{
}

class DecoratedService
{
}
