<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Scheduler\EventListener\TaskSubscriber;
use Symfony\Component\Scheduler\SchedulerInterface;
use Symfony\Component\Scheduler\SchedulerRegistryInterface;
use Symfony\Component\Scheduler\Worker\WorkerRegistryInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskSubscriberTest extends TestCase
{
    public function testEventsAreCorrectlyListened(): void
    {
        static::assertArrayHasKey(KernelEvents::REQUEST, TaskSubscriber::getSubscribedEvents());
    }

    public function testInvalidPathCannotBeHandled(): void
    {
        $schedulerRegistry = $this->createMock(SchedulerRegistryInterface::class);
        $workerRegistry = $this->createMock(WorkerRegistryInterface::class);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('http://www.foo.com/_foo');
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST);

        $subscriber = new TaskSubscriber($schedulerRegistry, $workerRegistry);

        $expected = $request->attributes->all();
        $subscriber->onKernelRequest($event);

        static::assertSame($expected, $request->attributes->all());
    }

    public function testValidPathCannotBeHandledWithoutParams(): void
    {
        $schedulerRegistry = $this->createMock(SchedulerRegistryInterface::class);
        $workerRegistry = $this->createMock(WorkerRegistryInterface::class);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('http://www.foo.com/_tasks');
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST);

        $subscriber = new TaskSubscriber($schedulerRegistry, $workerRegistry);

        static::expectException(\InvalidArgumentException::class);
        $subscriber->onKernelRequest($event);
    }

    public function testValidPathCanBeHandledWithValidName(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks');

        $schedulerRegistry = $this->createMock(SchedulerRegistryInterface::class);
        $schedulerRegistry->expects(self::once())->method('toArray')->willReturn([$scheduler]);
        $workerRegistry = $this->createMock(WorkerRegistryInterface::class);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('http://www.foo.com/_tasks?name=app.bar');
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST);

        $subscriber = new TaskSubscriber($schedulerRegistry, $workerRegistry);

        $subscriber->onKernelRequest($event);

        static::assertArrayHasKey('task_filter', $request->attributes->all());
        static::assertSame('app.bar', $request->attributes->get('task_filter'));
    }

    public function testValidPathCanBeHandledWithValidExpression(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks');

        $schedulerRegistry = $this->createMock(SchedulerRegistryInterface::class);
        $schedulerRegistry->expects(self::once())->method('toArray')->willReturn([$scheduler]);
        $workerRegistry = $this->createMock(WorkerRegistryInterface::class);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('http://www.foo.com/_tasks?expression=*****');
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST);

        $subscriber = new TaskSubscriber($schedulerRegistry, $workerRegistry);

        $subscriber->onKernelRequest($event);

        static::assertArrayHasKey('task_filter', $request->attributes->all());
        static::assertSame('*****', $request->attributes->get('task_filter'));
    }
}
