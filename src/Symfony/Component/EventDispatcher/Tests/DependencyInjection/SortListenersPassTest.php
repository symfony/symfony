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

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\DependencyInjection\SortListenersPass;
use Symfony\Component\EventDispatcher\EventDispatcher;

class SortListenersPassTest extends TestCase
{
    public function testListenersMoveToTheConstructorSortedByPriority()
    {
        $container = new ContainerBuilder();
        $definition = $container->register('event_dispatcher', EventDispatcher::class)
            ->addTag('event_dispatcher.dispatcher');
        $definition->addMethodCall('addListener', ['foo', $low = $this->listener('low'), -10]);
        $definition->addMethodCall('addListener', ['foo', $first = $this->listener('first')]);
        $definition->addMethodCall('addListener', ['bar', $high = $this->listener('high'), 10]);
        $definition->addMethodCall('addListener', ['foo', $second = $this->listener('second')]);

        (new SortListenersPass())->process($container);

        $this->assertSame([], $definition->getMethodCalls());
        $this->assertEquals([[
            'foo' => [0 => [$first, $second], -10 => [$low]],
            'bar' => [10 => [$high]],
        ]], $definition->getArguments());
    }

    public function testOtherMethodCallsAreKept()
    {
        $container = new ContainerBuilder();
        $definition = $container->register('event_dispatcher', EventDispatcher::class)
            ->addTag('event_dispatcher.dispatcher');
        $definition->addMethodCall('addListener', ['foo', $this->listener('foo')]);
        $definition->addMethodCall('setSomething', ['value']);

        (new SortListenersPass())->process($container);

        $this->assertSame([['setSomething', ['value']]], $definition->getMethodCalls());
    }

    public function testADispatcherOfAnotherClassIsLeftAlone()
    {
        $container = new ContainerBuilder();
        $definition = $container->register('event_dispatcher', DecoratingDispatcher::class)
            ->addTag('event_dispatcher.dispatcher');
        $definition->addMethodCall('addListener', ['foo', $this->listener('foo')]);

        (new SortListenersPass())->process($container);

        $this->assertCount(1, $definition->getMethodCalls());
        $this->assertSame([], $definition->getArguments());
    }

    public function testADispatcherThatAlreadyHasArgumentsIsLeftAlone()
    {
        $container = new ContainerBuilder();
        $definition = $container->register('event_dispatcher', EventDispatcher::class)
            ->addTag('event_dispatcher.dispatcher')
            ->addArgument(['foo' => [0 => []]]);
        $definition->addMethodCall('addListener', ['foo', $this->listener('foo')]);

        (new SortListenersPass())->process($container);

        $this->assertCount(1, $definition->getMethodCalls());
    }

    public function testADispatcherWithoutListenersGetsNoArgument()
    {
        $container = new ContainerBuilder();
        $definition = $container->register('event_dispatcher', EventDispatcher::class)
            ->addTag('event_dispatcher.dispatcher');

        (new SortListenersPass())->process($container);

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
