<?php

namespace Symfony\Component\HttpKernel\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\ResettableServicePass;
use Symfony\Component\HttpKernel\DependencyInjection\ServicesResetter;
use Symfony\Component\HttpKernel\Tests\Fixtures\ClearableInterface;
use Symfony\Component\HttpKernel\Tests\Fixtures\ClearableService;
use Symfony\Component\HttpKernel\Tests\Fixtures\ClearableServiceDecorator;
use Symfony\Component\HttpKernel\Tests\Fixtures\MultiResettableService;
use Symfony\Component\HttpKernel\Tests\Fixtures\ResettableService;

class ResettableServicePassTest extends TestCase
{
    public function testCompilerPass()
    {
        $container = new ContainerBuilder();
        $container->register('one', ResettableService::class)
            ->setPublic(true)
            ->addTag('kernel.reset', ['method' => 'reset']);
        $container->register('two', ClearableService::class)
            ->setPublic(true)
            ->addTag('kernel.reset', ['method' => 'clear']);
        $container->register('three', MultiResettableService::class)
            ->setPublic(true)
            ->addTag('kernel.reset', ['method' => 'resetFirst'])
            ->addTag('kernel.reset', ['method' => 'resetSecond']);

        $container->register('services_resetter', ServicesResetter::class)
            ->setPublic(true)
            ->setArguments([null, []]);
        $container->addCompilerPass(new ResettableServicePass());

        $container->compile();

        $definition = $container->getDefinition('services_resetter');

        $this->assertEquals(
            [
                new IteratorArgument([
                    'one' => new Reference('one', ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE),
                    'two' => new Reference('two', ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE),
                    'three' => new Reference('three', ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE),
                ]),
                [
                    'one' => ['reset'],
                    'two' => ['clear'],
                    'three' => ['resetFirst', 'resetSecond'],
                ],
            ],
            $definition->getArguments()
        );
    }

    public function testMissingMethod()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tag "kernel.reset" requires the "method" attribute to be set.');
        $container = new ContainerBuilder();
        $container->register(ResettableService::class)
            ->addTag('kernel.reset');
        $container->register('services_resetter', ServicesResetter::class)
            ->setArguments([null, []]);
        $container->addCompilerPass(new ResettableServicePass());

        $container->compile();
    }

    public function testCompilerPassWithoutResetters()
    {
        $container = new ContainerBuilder();
        $container->register('services_resetter', ServicesResetter::class)
            ->setArguments([null, []]);
        $container->addCompilerPass(new ResettableServicePass());

        $container->compile();

        $this->assertFalse($container->has('services_resetter'));
    }

    public function testDecoratedLastResettableService()
    {
        $container = new ContainerBuilder();
        $container->register('services_resetter', ServicesResetter::class)
            ->setPublic(true)
            ->setArguments([null, []]);
        $container->addCompilerPass(new ResettableServicePass());

        $container->register('clearable_service', ClearableService::class)
            ->setFactory([ClearableService::class, 'create'])
            ->addTag('kernel.reset', ['method' => 'reset']);

        $container->setAlias(ClearableInterface::class, new Alias('clearable_service', true));

        $container->register('clearable_service_decorator', ClearableServiceDecorator::class)
            ->setDecoratedService(ClearableInterface::class)
            ->setArgument(0, new Reference('.inner'));

        $container->register('clearable_service_decorator_2', ClearableServiceDecorator::class)
            ->setDecoratedService(ClearableInterface::class)
            ->setArgument(0, new Reference('.inner'));

        $container->compile();

        $container->get(ClearableInterface::class)->clear();

        self::assertSame(1, ClearableService::$counter);
        self::assertSame(2, ClearableServiceDecorator::$counter);

        $container->get('services_resetter')->reset();
        self::assertSame(0, ClearableService::$counter);
        self::assertSame(2, ClearableServiceDecorator::$counter);
    }

    public function testDecoratedMiddleResettableService()
    {
        $container = new ContainerBuilder();
        $container->register('services_resetter', ServicesResetter::class)
            ->setPublic(true)
            ->setArguments([null, []]);
        $container->addCompilerPass(new ResettableServicePass());

        $container->register('clearable_service', ClearableService::class)
            ->setFactory([ClearableService::class, 'create']);

        $container->setAlias(ClearableInterface::class, new Alias('clearable_service', true));

        $container->register('clearable_service_decorator', ClearableServiceDecorator::class)
            ->setDecoratedService(ClearableInterface::class)
            ->setArgument(0, new Reference('.inner'))
            ->addTag('kernel.reset', ['method' => 'reset']);

        $container->register('clearable_service_decorator_2', ClearableServiceDecorator::class)
            ->setDecoratedService(ClearableInterface::class)
            ->setArgument(0, new Reference('.inner'));

        $container->compile();

        $container->get(ClearableInterface::class)->clear();

        self::assertSame(1, ClearableService::$counter);
        self::assertSame(2, ClearableServiceDecorator::$counter);

        $container->get('services_resetter')->reset();
        self::assertSame(1, ClearableService::$counter);
        self::assertSame(0, ClearableServiceDecorator::$counter);
    }

    public function testDecoratedFirstResettableService()
    {
        $container = new ContainerBuilder();
        $container->register('services_resetter', ServicesResetter::class)
            ->setPublic(true)
            ->setArguments([null, []]);
        $container->addCompilerPass(new ResettableServicePass());

        $container->register('clearable_service', ClearableService::class)
            ->setFactory([ClearableService::class, 'create']);

        $container->setAlias(ClearableInterface::class, new Alias('clearable_service', true));

        $container->register('clearable_service_decorator', ClearableServiceDecorator::class)
            ->setDecoratedService(ClearableInterface::class)
            ->setArgument(0, new Reference('.inner'))
            ->addTag('kernel.reset', ['method' => 'reset']);

        $container->compile();

        $container->get(ClearableInterface::class)->clear();

        self::assertSame(1, ClearableService::$counter);
        self::assertSame(1, ClearableServiceDecorator::$counter);

        $container->get('services_resetter')->reset();
        self::assertSame(1, ClearableService::$counter);
        self::assertSame(0, ClearableServiceDecorator::$counter);
    }
}
