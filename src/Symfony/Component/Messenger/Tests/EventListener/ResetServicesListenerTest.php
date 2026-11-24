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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServicesResetter;
use Symfony\Component\Messenger\Event\WorkerMessagesCompletedEvent;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Event\WorkerStoppedEvent;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\EventListener\ResetServicesListener;
use Symfony\Component\Messenger\Execution\InFlightMessageCountAwareInterface;
use Symfony\Component\Messenger\Execution\MessageExecutionStrategyInterface;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Worker;
use Symfony\Contracts\Service\ResetInterface;

class ResetServicesListenerTest extends TestCase
{
    public static function provideResetServices(): iterable
    {
        yield [true];
        yield [false];
    }

    #[DataProvider('provideResetServices')]
    public function testResetServices(bool $shouldReset)
    {
        $resettableService = $this->createMock(ResetInterface::class);
        $resettableService->expects($shouldReset ? $this->once() : $this->never())->method('reset');
        $servicesResetter = new ServicesResetter(new \ArrayIterator(['foo' => $resettableService]), ['foo' => 'reset']);

        $event = new WorkerRunningEvent(new Worker([], new MessageBus()), !$shouldReset);

        $resetListener = new ResetServicesListener($servicesResetter);
        $resetListener->resetServices($event);
    }

    public function testResetServicesWaitsUntilNoMessageIsInFlight()
    {
        $resettableService = $this->createMock(ResetInterface::class);
        $resettableService->expects($this->once())->method('reset');
        $servicesResetter = new ServicesResetter(new \ArrayIterator(['foo' => $resettableService]), ['foo' => 'reset']);

        $strategy = new class implements MessageExecutionStrategyInterface, InFlightMessageCountAwareInterface {
            public int $inFlight = 2;

            public function execute(Envelope $envelope, string $transportName, callable $onHandled): void
            {
            }

            public function shouldPauseConsumption(): bool
            {
                return false;
            }

            public function wait(callable $onHandled, ?float $timeout = null): bool
            {
                return false;
            }

            public function flush(callable $onHandled, bool|float $force = false): bool
            {
                return false;
            }

            public function shutdown(): void
            {
            }

            public function getInFlightMessageCount(): int
            {
                return $this->inFlight;
            }
        };
        $worker = new Worker([], new MessageBus(), messageExecutionStrategy: $strategy);

        $resetListener = new ResetServicesListener($servicesResetter);

        // two messages started and handled: the services are shared with the ones still in flight
        $resetListener->resetServices(new WorkerRunningEvent($worker, false));
        $resetListener->resetServices(new WorkerRunningEvent($worker, false));

        // the last message is handled: the reset requested earlier happens now, once
        $strategy->inFlight = 0;
        $resetListener->resetServicesWhenMessagesCompleted(new WorkerMessagesCompletedEvent($worker));
        $resetListener->resetServicesWhenMessagesCompleted(new WorkerMessagesCompletedEvent($worker));
        $resetListener->resetServices(new WorkerRunningEvent($worker, true));
    }

    public function testResetServicesAtStop()
    {
        $resettableService = $this->createMock(ResetInterface::class);
        $resettableService->expects($this->once())->method('reset');
        $servicesResetter = new ServicesResetter(new \ArrayIterator(['foo' => $resettableService]), ['foo' => 'reset']);

        $event = new WorkerStoppedEvent(new Worker([], new MessageBus()));

        $resetListener = new ResetServicesListener($servicesResetter);
        $resetListener->resetServicesAtStop($event);
    }
}
