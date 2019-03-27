<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Retry\RetryStrategyInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;
use Symfony\Component\Messenger\Tests\Fixtures\CallbackReceiver;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Worker;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class WorkerTest extends TestCase
{
    public function testWorkerDispatchTheReceivedMessage()
    {
        $apiMessage = new DummyMessage('API');
        $ipaMessage = new DummyMessage('IPA');

        $receiver = new CallbackReceiver(function ($handler) use ($apiMessage, $ipaMessage) {
            $handler(new Envelope($apiMessage));
            $handler(new Envelope($ipaMessage));
        });

        $bus = $this->getMockBuilder(MessageBusInterface::class)->getMock();

        $bus->expects($this->at(0))->method('dispatch')->with($envelope = new Envelope($apiMessage, new ReceivedStamp()))->willReturn($envelope);
        $bus->expects($this->at(1))->method('dispatch')->with($envelope = new Envelope($ipaMessage, new ReceivedStamp()))->willReturn($envelope);

        $worker = new Worker($receiver, $bus, 'receiver_id');
        $worker->run();

        $this->assertSame(2, $receiver->getAcknowledgeCount());
    }

    public function testWorkerDoesNotWrapMessagesAlreadyWrappedWithReceivedMessage()
    {
        $envelope = new Envelope(new DummyMessage('API'));
        $receiver = new CallbackReceiver(function ($handler) use ($envelope) {
            $handler($envelope);
        });
        $envelope = $envelope->with(new ReceivedStamp());

        $bus = $this->getMockBuilder(MessageBusInterface::class)->getMock();
        $bus->expects($this->at(0))->method('dispatch')->with($envelope)->willReturn($envelope);
        $retryStrategy = $this->getMockBuilder(RetryStrategyInterface::class)->getMock();

        $worker = new Worker($receiver, $bus, 'receiver_id', $retryStrategy);
        $worker->run();
    }

    public function testDispatchCausesRetry()
    {
        $receiver = new CallbackReceiver(function ($handler) {
            $handler(new Envelope(new DummyMessage('Hello'), new SentStamp('Some\Sender', 'sender_alias')));
        });

        $bus = $this->getMockBuilder(MessageBusInterface::class)->getMock();
        $bus->expects($this->at(0))->method('dispatch')->willThrowException(new \InvalidArgumentException('Why not'));

        // 2nd call will be the retry
        $bus->expects($this->at(1))->method('dispatch')->with($this->callback(function (Envelope $envelope) {
            /** @var RedeliveryStamp|null $redeliveryStamp */
            $redeliveryStamp = $envelope->last(RedeliveryStamp::class);
            $this->assertNotNull($redeliveryStamp);
            // retry count now at 1
            $this->assertSame(1, $redeliveryStamp->getRetryCount());
            $this->assertTrue($redeliveryStamp->shouldRedeliverToSender('Some\Sender', 'sender_alias'));

            // received stamp is removed
            $this->assertNull($envelope->last(ReceivedStamp::class));

            return true;
        }))->willReturnArgument(0);

        $retryStrategy = $this->getMockBuilder(RetryStrategyInterface::class)->getMock();
        $retryStrategy->expects($this->once())->method('isRetryable')->willReturn(true);

        $worker = new Worker($receiver, $bus, 'receiver_id', $retryStrategy);
        $worker->run();

        // old message acknowledged
        $this->assertSame(1, $receiver->getAcknowledgeCount());
    }

    public function testDispatchCausesRejectWhenNoRetry()
    {
        $receiver = new CallbackReceiver(function ($handler) {
            $handler(new Envelope(new DummyMessage('Hello'), new SentStamp('Some\Sender', 'sender_alias')));
        });

        $bus = $this->getMockBuilder(MessageBusInterface::class)->getMock();
        $bus->method('dispatch')->willThrowException(new \InvalidArgumentException('Why not'));

        $retryStrategy = $this->getMockBuilder(RetryStrategyInterface::class)->getMock();
        $retryStrategy->expects($this->once())->method('isRetryable')->willReturn(false);

        $worker = new Worker($receiver, $bus, 'receiver_id', $retryStrategy);
        $worker->run();
        $this->assertSame(1, $receiver->getRejectCount());
        $this->assertSame(0, $receiver->getAcknowledgeCount());
    }

    public function testDispatchCausesRejectOnUnrecoverableMessage()
    {
        $receiver = new CallbackReceiver(function ($handler) {
            $handler(new Envelope(new DummyMessage('Hello')));
        });

        $bus = $this->getMockBuilder(MessageBusInterface::class)->getMock();
        $bus->method('dispatch')->willThrowException(new UnrecoverableMessageHandlingException('Will never work'));

        $retryStrategy = $this->getMockBuilder(RetryStrategyInterface::class)->getMock();
        $retryStrategy->expects($this->never())->method('isRetryable');

        $worker = new Worker($receiver, $bus, 'receiver_id', $retryStrategy);
        $worker->run();
        $this->assertSame(1, $receiver->getRejectCount());
    }

    public function testWorkerDoesNotSendNullMessagesToTheBus()
    {
        $receiver = new CallbackReceiver(function ($handler) {
            $handler(null);
        });

        $bus = $this->getMockBuilder(MessageBusInterface::class)->getMock();
        $bus->expects($this->never())->method('dispatch');
        $retryStrategy = $this->getMockBuilder(RetryStrategyInterface::class)->getMock();

        $worker = new Worker($receiver, $bus, 'receiver_id', $retryStrategy);
        $worker->run();
    }

    public function testWorkerDispatchesEventsOnSuccess()
    {
        $envelope = new Envelope(new DummyMessage('Hello'));
        $receiver = new CallbackReceiver(function ($handler) use ($envelope) {
            $handler($envelope);
        });

        $bus = $this->getMockBuilder(MessageBusInterface::class)->getMock();
        $bus->method('dispatch')->willReturn($envelope);

        $retryStrategy = $this->getMockBuilder(RetryStrategyInterface::class)->getMock();
        $eventDispatcher = $this->getMockBuilder(EventDispatcherInterface::class)->getMock();

        $eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->withConsecutive(
                [$this->isInstanceOf(WorkerMessageReceivedEvent::class)],
                [$this->isInstanceOf(WorkerMessageHandledEvent::class)]
            );

        $worker = new Worker($receiver, $bus, 'receiver_id', $retryStrategy, $eventDispatcher);
        $worker->run();
    }

    public function testWorkerDispatchesEventsOnError()
    {
        $envelope = new Envelope(new DummyMessage('Hello'));
        $receiver = new CallbackReceiver(function ($handler) use ($envelope) {
            $handler($envelope);
        });

        $bus = $this->getMockBuilder(MessageBusInterface::class)->getMock();
        $exception = new \InvalidArgumentException('Oh no!');
        $bus->method('dispatch')->willThrowException($exception);

        $retryStrategy = $this->getMockBuilder(RetryStrategyInterface::class)->getMock();
        $eventDispatcher = $this->getMockBuilder(EventDispatcherInterface::class)->getMock();

        $eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->withConsecutive(
                [$this->isInstanceOf(WorkerMessageReceivedEvent::class)],
                [$this->isInstanceOf(WorkerMessageFailedEvent::class)]
            );

        $worker = new Worker($receiver, $bus, 'receiver_id', $retryStrategy, $eventDispatcher);
        $worker->run();
    }
}
