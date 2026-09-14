<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\EventListener\StopWorkerOnIdleListener;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\DummyReceiver;
use Symfony\Component\Messenger\Worker;

class StopWorkerOnIdleListenerTest extends TestCase
{
    public function testWorkerStopsWhenIdle()
    {
        $worker = $this->createMock(Worker::class);
        $worker->expects($this->once())->method('stop');

        $listener = new StopWorkerOnIdleListener();
        $listener->onWorkerRunning(new WorkerRunningEvent($worker, true));
    }

    public function testWorkerKeepsRunningWhileMessagesAreReceived()
    {
        $worker = $this->createMock(Worker::class);
        $worker->expects($this->never())->method('stop');

        $listener = new StopWorkerOnIdleListener();
        $listener->onWorkerRunning(new WorkerRunningEvent($worker, false));
    }

    public function testWorkerLogsWhenStoppedOnIdle()
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')->with('Worker stopped because no message was available');

        $listener = new StopWorkerOnIdleListener($logger);
        $listener->onWorkerRunning(new WorkerRunningEvent(new Worker([], new MessageBus()), true));
    }

    public function testWorkerDrainsTheReceiverAndStopsWithoutSleeping()
    {
        $receiver = new DummyReceiver([
            [new Envelope(new DummyMessage('API'))],
            [new Envelope(new DummyMessage('IPA'))],
            [],
        ]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnIdleListener());

        $clock = new MockClock();
        $startedAt = $clock->now();

        $worker = new Worker(['transport' => $receiver], new MessageBus(), $dispatcher, clock: $clock);
        $worker->run();

        $this->assertSame(2, $receiver->getAcknowledgeCount());
        $this->assertEquals($startedAt, $clock->now());
    }
}
