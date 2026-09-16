<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\CompiledEventDispatcher;
use Symfony\Component\EventDispatcher\DependencyInjection\CompileListenersPass;
use Symfony\Component\EventDispatcher\EventDispatcher;

class CompileListenersPassTest extends TestCase
{
    public function testTheListenersBecomeAMapSortedByPriority()
    {
        $container = new ContainerBuilder();
        $definition = $container->register('event_dispatcher', EventDispatcher::class)
            ->addTag('event_dispatcher.dispatcher');
        $definition->addMethodCall('addListener', ['foo', $this->listener('low'), -10]);
        $definition->addMethodCall('addListener', ['foo', $this->listener('first')]);
        $definition->addMethodCall('addListener', ['bar', $this->listener('high'), 10]);
        $definition->addMethodCall('addListener', ['foo', $this->listener('second')]);

        (new CompileListenersPass())->process($container);

        $this->assertSame(CompiledEventDispatcher::class, $definition->getClass());
        $this->assertSame([], $definition->getMethodCalls());

        [$listeners, $locator] = $definition->getArguments();

        $this->assertSame([
            'foo' => [0 => [['first', 'onEvent'], ['second', 'onEvent']], -10 => [['low', 'onEvent']]],
            'bar' => [10 => [['high', 'onEvent']]],
        ], $listeners);
        $this->assertInstanceOf(ServiceLocatorArgument::class, $locator);
        $this->assertSame(['low', 'first', 'high', 'second'], array_keys($locator->getValues()));
    }

    public function testAListenerThatIsNotAServiceLeavesTheDispatcherAlone()
    {
        $container = new ContainerBuilder();
        $definition = $container->register('event_dispatcher', EventDispatcher::class)
            ->addTag('event_dispatcher.dispatcher');
        $definition->addMethodCall('addListener', ['foo', $this->listener('foo')]);
        $definition->addMethodCall('addListener', ['foo', 'strlen']);

        $pass = new CompileListenersPass();
        $pass->process($container);

        $this->assertSame(EventDispatcher::class, $definition->getClass());
        $this->assertCount(2, $definition->getMethodCalls());
        $this->assertSame([], $definition->getArguments());
        $this->assertSame([$pass::class.': Not compiling the listeners of "event_dispatcher": one of them is not a lazy service.'], $container->getCompiler()->getLog());
    }

    #[TestWith(['addSubscriber', [new Reference('subscriber')]])]
    #[TestWith(['setSomething', ['value']])]
    public function testACallOtherThanAddListenerLeavesTheDispatcherAlone(string $method, array $arguments)
    {
        $container = new ContainerBuilder();
        $definition = $container->register('event_dispatcher', EventDispatcher::class)
            ->addTag('event_dispatcher.dispatcher');
        $definition->addMethodCall('addListener', ['foo', $this->listener('foo')]);
        $definition->addMethodCall($method, $arguments);

        $pass = new CompileListenersPass();
        $pass->process($container);

        $this->assertSame(EventDispatcher::class, $definition->getClass());
        $this->assertCount(2, $definition->getMethodCalls());
        $this->assertSame([$pass::class.\sprintf(': Not compiling the listeners of "event_dispatcher": "%s()" is called on it.', $method)], $container->getCompiler()->getLog());
    }

    public function testADispatcherOfAnotherClassIsLeftAlone()
    {
        $container = new ContainerBuilder();
        $definition = $container->register('event_dispatcher', DecoratingDispatcher::class)
            ->addTag('event_dispatcher.dispatcher');
        $definition->addMethodCall('addListener', ['foo', $this->listener('foo')]);

        (new CompileListenersPass())->process($container);

        $this->assertSame(DecoratingDispatcher::class, $definition->getClass());
        $this->assertCount(1, $definition->getMethodCalls());
    }

    public function testADispatcherThatAlreadyHasArgumentsIsLeftAlone()
    {
        $container = new ContainerBuilder();
        $definition = $container->register('event_dispatcher', EventDispatcher::class)
            ->addTag('event_dispatcher.dispatcher')
            ->addArgument('something');
        $definition->addMethodCall('addListener', ['foo', $this->listener('foo')]);

        (new CompileListenersPass())->process($container);

        $this->assertSame(EventDispatcher::class, $definition->getClass());
        $this->assertCount(1, $definition->getMethodCalls());
    }

    public function testADispatcherWithoutListenersIsLeftAlone()
    {
        $container = new ContainerBuilder();
        $definition = $container->register('event_dispatcher', EventDispatcher::class)
            ->addTag('event_dispatcher.dispatcher');

        (new CompileListenersPass())->process($container);

        $this->assertSame(EventDispatcher::class, $definition->getClass());
        $this->assertSame([], $definition->getArguments());
    }

    private function listener(string $id): array
    {
        return [new ServiceClosureArgument(new Reference($id)), 'onEvent'];
    }
}

class DecoratingDispatcher extends EventDispatcher
{
}
