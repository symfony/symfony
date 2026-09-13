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
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\EventListener\DispatchOnFailureListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageToFailureTransportListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\DispatchOnFailureStamp;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\FailedMessageStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\SecondMessage;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

class DispatchOnFailureListenerTest extends TestCase
{
    public function testNothingIsDispatchedWhenTheMessageWillBeRetried()
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $event = new WorkerMessageFailedEvent(new Envelope(new DummyMessage('failed'), [new DispatchOnFailureStamp(new SecondMessage())]), 'my_receiver', new \RuntimeException('It failed.'));
        $event->setForRetry();

        (new DispatchOnFailureListener($bus))->onMessageFailed($event);
    }

    public function testNothingIsDispatchedWithoutTheStamp()
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $event = new WorkerMessageFailedEvent(new Envelope(new DummyMessage('failed')), 'my_receiver', new \RuntimeException('It failed.'));

        (new DispatchOnFailureListener($bus))->onMessageFailed($event);
    }

    public function testFailureMessageIsDispatchedWithTheErrorDetailsOfTheFailedEnvelope()
    {
        $failed = new DummyMessage('failed');
        $failure = new SecondMessage();
        $errorDetailsStamp = ErrorDetailsStamp::create(new \RuntimeException('Recorded by the worker.'));
        $envelope = new Envelope($failed, [new BusNameStamp('the_bus'), new DispatchOnFailureStamp($failure), $errorDetailsStamp]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (Envelope $dispatched) use ($failed, $failure, $errorDetailsStamp) {
                $this->assertSame($failure, $dispatched->getMessage());
                $this->assertSame($failed, $dispatched->last(FailedMessageStamp::class)->getMessage());
                $this->assertSame([$errorDetailsStamp], $dispatched->all(ErrorDetailsStamp::class));
                $this->assertSame('the_bus', $dispatched->last(BusNameStamp::class)->getBusName());
                $this->assertSame([], $dispatched->all(DispatchOnFailureStamp::class));

                return true;
            }))
            ->willReturnArgument(0);

        $event = new WorkerMessageFailedEvent($envelope, 'my_receiver', new \RuntimeException('It failed.'));

        (new DispatchOnFailureListener($bus))->onMessageFailed($event);
    }

    public function testErrorDetailsAreCreatedFromTheThrowableWhenTheFailedEnvelopeHasNone()
    {
        $exception = new \RuntimeException('It failed.');
        $envelope = new Envelope(new DummyMessage('failed'), [new DispatchOnFailureStamp(new SecondMessage())]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (Envelope $dispatched) use ($exception) {
                $this->assertEquals(ErrorDetailsStamp::create($exception), $dispatched->last(ErrorDetailsStamp::class));
                $this->assertNull($dispatched->last(BusNameStamp::class));

                return true;
            }))
            ->willReturnArgument(0);

        $event = new WorkerMessageFailedEvent($envelope, 'my_receiver', $exception);

        (new DispatchOnFailureListener($bus))->onMessageFailed($event);
    }

    public function testEnvelopeItemKeepsItsStamps()
    {
        $failure = new SecondMessage();
        $delayStamp = new DelayStamp(1000);
        $ownFailureStamp = new DispatchOnFailureStamp(new DummyMessage('nested'));
        $envelope = new Envelope(new DummyMessage('failed'), [new BusNameStamp('the_bus'), new DispatchOnFailureStamp(new Envelope($failure, [$delayStamp, new BusNameStamp('other_bus'), $ownFailureStamp]))]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (Envelope $dispatched) use ($failure, $delayStamp, $ownFailureStamp) {
                $this->assertSame($failure, $dispatched->getMessage());
                $this->assertSame([$delayStamp], $dispatched->all(DelayStamp::class));
                $this->assertSame('other_bus', $dispatched->last(BusNameStamp::class)->getBusName());
                $this->assertCount(1, $dispatched->all(BusNameStamp::class));
                $this->assertSame([$ownFailureStamp], $dispatched->all(DispatchOnFailureStamp::class));

                return true;
            }))
            ->willReturnArgument(0);

        $event = new WorkerMessageFailedEvent($envelope, 'my_receiver', new \RuntimeException('It failed.'));

        (new DispatchOnFailureListener($bus))->onMessageFailed($event);
    }

    public function testTheFailureToDispatchTheFailureMessageIsLogged()
    {
        $dispatchException = new \LogicException('No handler for the failure message.');
        $envelope = new Envelope(new DummyMessage('failed'), [new DispatchOnFailureStamp(new SecondMessage())]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())->method('dispatch')->willThrowException($dispatchException);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with($this->callback(function (string $message) {
                $this->assertStringContainsString('{failure_class}', $message);
                $this->assertStringContainsString('{class}', $message);

                return true;
            }), $this->callback(function (array $context) use ($dispatchException) {
                $this->assertSame(DummyMessage::class, $context['class']);
                $this->assertSame(SecondMessage::class, $context['failure_class']);
                $this->assertSame($dispatchException, $context['exception']);

                return true;
            }));

        $event = new WorkerMessageFailedEvent($envelope, 'my_receiver', new \RuntimeException('It failed.'));

        (new DispatchOnFailureListener($bus, $logger))->onMessageFailed($event);
    }

    public function testTheFailedMessageStillReachesTheFailureTransportWhenTheFailureMessageCannotBeDispatched()
    {
        $failed = new DummyMessage('failed');
        $envelope = new Envelope($failed, [new DispatchOnFailureStamp(new SecondMessage())]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())->method('dispatch')->willThrowException(new \LogicException('No handler for the failure message.'));

        $failureTransport = new InMemoryTransport();

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new DispatchOnFailureListener($bus));
        $dispatcher->addSubscriber(new SendFailedMessageToFailureTransportListener(new ServiceLocator(['my_receiver' => static fn () => $failureTransport])));

        $dispatcher->dispatch(new WorkerMessageFailedEvent($envelope, 'my_receiver', new \RuntimeException('It failed.')));

        $sent = $failureTransport->getSent();
        $this->assertCount(1, $sent);
        $this->assertSame($failed, $sent[0]->getMessage());
    }

    public function testSubscribedEvents()
    {
        $this->assertSame([WorkerMessageFailedEvent::class => ['onMessageFailed', 0]], DispatchOnFailureListener::getSubscribedEvents());
    }
}
