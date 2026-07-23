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
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\ResettableServicePass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServicesResetter;
use Symfony\Component\DependencyInjection\Tests\Fixtures\ClearableService;
use Symfony\Component\DependencyInjection\Tests\Fixtures\MultiResettableService;
use Symfony\Component\DependencyInjection\Tests\Fixtures\ResettableService;

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
        $this->expectExceptionMessage('Tag "kernel.reset" requires the "method" attribute to be set on service "Symfony\Component\DependencyInjection\Tests\Fixtures\ResettableService".');
        $container = new ContainerBuilder();
        $container->register(ResettableService::class)
            ->addTag('kernel.reset');
        $container->register('services_resetter', ServicesResetter::class)
            ->setArguments([null, []]);
        $container->addCompilerPass(new ResettableServicePass());

        $container->compile();
    }

    public function testIgnoreInvalidMethod()
    {
        $container = new ContainerBuilder();
        $container->register(ResettableService::class)
            ->setPublic(true)
            ->addTag('kernel.reset', ['method' => 'missingMethod', 'on_invalid' => 'ignore']);
        $container->register('services_resetter', ServicesResetter::class)
            ->setPublic(true)
            ->setArguments([null, []]);
        $container->addCompilerPass(new ResettableServicePass());

        $container->compile();

        $this->assertSame([ResettableService::class => ['?missingMethod']], $container->getDefinition('services_resetter')->getArgument(1));

        $resettable = $container->get(ResettableService::class);
        $resetter = $container->get('services_resetter');
        $resetter->reset();
    }

    public function testCompilerPassWithNonSharedServices()
    {
        $container = new ContainerBuilder();
        $container->register('shared', ResettableService::class)
            ->setPublic(true)
            ->addTag('kernel.reset', ['method' => 'reset']);
        $container->register('non_shared', ClearableService::class)
            ->setPublic(true)
            ->setShared(false)
            ->addTag('kernel.reset', ['method' => 'clear']);

        $container->register('services_resetter', ServicesResetter::class)
            ->setPublic(true)
            ->setArguments([null, []]);
        $container->addCompilerPass(new ResettableServicePass());

        $container->compile();

        $definition = $container->getDefinition('services_resetter');

        // Non-shared services should NOT be in the IteratorArgument
        $this->assertEquals(
            new IteratorArgument([
                'shared' => new Reference('shared', ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE),
            ]),
            $definition->getArgument(0)
        );

        // Methods should only contain shared services
        $this->assertSame(
            ['shared' => ['reset']],
            $definition->getArgument(1)
        );

        // A Definition with factory [service_container, getResetMap] should be passed as 3rd argument
        $arg2 = $definition->getArgument(2);
        $this->assertInstanceOf(Definition::class, $arg2);
        $this->assertSame(\WeakMap::class, $arg2->getClass());
        $this->assertEquals([new Reference('service_container'), 'getResetMap'], $arg2->getFactory());

        // Non-shared service should have container.tracked_for_reset tag
        $this->assertSame(
            [['method' => 'clear']],
            $container->getDefinition('non_shared')->getTag('container.tracked_for_reset')
        );
    }

    public function testCompilerPassWithOnlyNonSharedServices()
    {
        $container = new ContainerBuilder();
        $container->register('non_shared', ResettableService::class)
            ->setPublic(true)
            ->setShared(false)
            ->addTag('kernel.reset', ['method' => 'reset']);

        $container->register('services_resetter', ServicesResetter::class)
            ->setPublic(true)
            ->setArguments([null, []]);
        $container->addCompilerPass(new ResettableServicePass());

        $container->compile();

        // services_resetter should still exist even with only non-shared services
        $this->assertTrue($container->has('services_resetter'));

        $definition = $container->getDefinition('services_resetter');

        // IteratorArgument should be empty
        $this->assertEquals(new IteratorArgument([]), $definition->getArgument(0));

        // Methods should be empty (non-shared methods are in the container.tracked_for_reset tag)
        $this->assertSame([], $definition->getArgument(1));

        // A Definition with factory should be passed
        $arg2 = $definition->getArgument(2);
        $this->assertInstanceOf(Definition::class, $arg2);
        $this->assertEquals([new Reference('service_container'), 'getResetMap'], $arg2->getFactory());
    }

    public function testCompilerPassWithNonSharedMultipleResetMethods()
    {
        $container = new ContainerBuilder();
        $container->register('non_shared', ResettableService::class)
            ->setPublic(true)
            ->setShared(false)
            ->addTag('kernel.reset', ['method' => 'reset'])
            ->addTag('kernel.reset', ['method' => 'clear']);

        $container->register('services_resetter', ServicesResetter::class)
            ->setPublic(true)
            ->setArguments([null, []]);
        $container->addCompilerPass(new ResettableServicePass());

        $container->compile();

        $this->assertSame(
            [['method' => 'reset'], ['method' => 'clear']],
            $container->getDefinition('non_shared')->getTag('container.tracked_for_reset')
        );
    }

    public function testCompilerPassWithNonSharedOnInvalidIgnore()
    {
        $container = new ContainerBuilder();
        $container->register('non_shared', ResettableService::class)
            ->setPublic(true)
            ->setShared(false)
            ->addTag('kernel.reset', ['method' => 'reset', 'on_invalid' => 'ignore']);

        $container->register('services_resetter', ServicesResetter::class)
            ->setPublic(true)
            ->setArguments([null, []]);
        $container->addCompilerPass(new ResettableServicePass());

        $container->compile();

        $this->assertSame(
            [['method' => '?reset']],
            $container->getDefinition('non_shared')->getTag('container.tracked_for_reset')
        );
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

    public function testNonSharedServiceIsResetThroughWeakMap()
    {
        $container = new ContainerBuilder();
        $container->register('non_shared', ResettableService::class)
            ->setPublic(true)
            ->setShared(false)
            ->addTag('kernel.reset', ['method' => 'reset']);

        $container->register('services_resetter', ServicesResetter::class)
            ->setPublic(true)
            ->setArguments([null, []]);
        $container->addCompilerPass(new ResettableServicePass());

        $container->compile();

        ResettableService::$counter = 0;

        $instance = $container->get('non_shared');

        $resetter = $container->get('services_resetter');
        $resetter->reset();

        $this->assertSame(1, ResettableService::$counter);
    }
}
