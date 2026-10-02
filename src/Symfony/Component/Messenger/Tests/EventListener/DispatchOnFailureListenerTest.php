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
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\EventListener\DispatchOnFailureListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageForRetryListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageToFailureTransportListener;
use Symfony\Component\Messenger\Exception\DelayedMessageHandlingException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\DispatchOnFailureStamp;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\FailedMessageStamp;
use Symfony\Component\Messenger\Stamp\HandlerArgumentsStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Stamp\TrustStamp;
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

    public function testFailureMessageIsDispatchedWithTheErrorDetailsOfTheCurrentFailure()
    {
        $failed = new DummyMessage('failed');
        $failure = new SecondMessage();
        $exception = new \RuntimeException('It failed.');
        $envelope = new Envelope($failed, [new BusNameStamp('the_bus'), new DispatchOnFailureStamp($failure), ErrorDetailsStamp::create(new \RuntimeException('It failed before.'))]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (Envelope $dispatched) use ($failed, $failure, $exception) {
                $this->assertSame($failure, $dispatched->getMessage());
                $this->assertSame($failed, $dispatched->last(FailedMessageStamp::class)->getMessage());
                $this->assertEquals([ErrorDetailsStamp::create($exception)], $dispatched->all(ErrorDetailsStamp::class));
                $this->assertSame('the_bus', $dispatched->last(BusNameStamp::class)->getBusName());
                $this->assertSame([], $dispatched->all(DispatchOnFailureStamp::class));

                return true;
            }))
            ->willReturnArgument(0);

        $event = new WorkerMessageFailedEvent($envelope, 'my_receiver', $exception);

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

    /**
     * @param list<StampInterface> $stamps
     */
    #[DataProvider('provideTrust')]
    public function testTheFailureMessageIsUntrustedWhenTheFailedMessageIs(array $stamps, bool $trusted)
    {
        $envelope = new Envelope(new DummyMessage('failed'), [...$stamps, new DispatchOnFailureStamp(new Envelope(new SecondMessage(), [TrustStamp::trusted()]))]);

        $dispatched = $this->dispatchFailureMessage($envelope);

        if ($trusted) {
            $this->assertSame([], $dispatched->all(TrustStamp::class));
        } else {
            $this->assertCount(1, $dispatched->all(TrustStamp::class));
            $this->assertFalse($dispatched->last(TrustStamp::class)->isTrusted());
        }
    }

    public static function provideTrust(): iterable
    {
        yield 'received' => [[new ReceivedStamp('async')], false];
        yield 'received and trusted' => [[new ReceivedStamp('async'), TrustStamp::trusted()], true];
        yield 'received and untrusted' => [[new ReceivedStamp('async'), TrustStamp::untrusted()], false];
    }

    public function testTheNonSendableStampsOfTheFailureMessageAreDropped()
    {
        $failureStamp = (new \ReflectionClass(DispatchOnFailureStamp::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(DispatchOnFailureStamp::class, 'message'))->setValue($failureStamp, new Envelope(new SecondMessage(), [new HandlerArgumentsStamp(['forged']), new ReceivedStamp('forged'), TrustStamp::trusted(), $delayStamp = new DelayStamp(1000)]));

        $dispatched = $this->dispatchFailureMessage(new Envelope(new DummyMessage('failed'), [new ReceivedStamp('async'), $failureStamp]));

        $this->assertSame([], $dispatched->all(HandlerArgumentsStamp::class));
        $this->assertSame([], $dispatched->all(ReceivedStamp::class));
        $this->assertFalse($dispatched->last(TrustStamp::class)->isTrusted());
        $this->assertSame([$delayStamp], $dispatched->all(DelayStamp::class));
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

    public function testTheFailedMessageSkipsTheFailureTransportOnceItsFailureMessageIsDispatched()
    {
        $envelope = new Envelope(new DummyMessage('failed'), [new DispatchOnFailureStamp(new SecondMessage())]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())->method('dispatch')->willReturnArgument(0);

        $failureTransport = new InMemoryTransport();

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new DispatchOnFailureListener($bus));
        $dispatcher->addSubscriber(new SendFailedMessageToFailureTransportListener(new ServiceLocator(['my_receiver' => static fn () => $failureTransport])));

        $dispatcher->dispatch($event = new WorkerMessageFailedEvent($envelope, 'my_receiver', new \RuntimeException('It failed.')));

        $this->assertSame([], $failureTransport->getSent());
        $this->assertSame('my_receiver', $event->getEnvelope()->last(SentToFailureTransportStamp::class)?->getOriginalReceiverName());
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
        $this->assertSame('my_receiver', $sent[0]->last(SentToFailureTransportStamp::class)?->getOriginalReceiverName());
    }

    public function testTheFailureMessagesOfTheDelayedMessagesThatFailedAreDispatchedFirst()
    {
        $failed = new DummyMessage('failed');
        $delayed = new DummyMessage('delayed');
        $handlerException = new HandlerFailedException(new Envelope($delayed, [new BusNameStamp('other_bus'), new DispatchOnFailureStamp($delayedFailure = new SecondMessage())]), [new \RuntimeException('The delayed message failed.')]);
        $exception = new DelayedMessageHandlingException([new TransportException('The transport is unavailable.'), $handlerException]);
        $envelope = new Envelope($failed, [new BusNameStamp('the_bus'), new DispatchOnFailureStamp($failure = new DummyMessage('failure'))]);

        $dispatched = [];
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function (Envelope $envelope) use (&$dispatched): Envelope {
                return $dispatched[] = $envelope;
            });

        (new DispatchOnFailureListener($bus))->onMessageFailed($event = new WorkerMessageFailedEvent($envelope, 'my_receiver', $exception));

        $this->assertSame([$delayedFailure, $failure], array_map(static fn (Envelope $envelope): object => $envelope->getMessage(), $dispatched));
        $this->assertSame($delayed, $dispatched[0]->last(FailedMessageStamp::class)->getMessage());
        $this->assertEquals([ErrorDetailsStamp::create($handlerException)], $dispatched[0]->all(ErrorDetailsStamp::class));
        $this->assertSame('other_bus', $dispatched[0]->last(BusNameStamp::class)->getBusName());
        $this->assertSame($failed, $dispatched[1]->last(FailedMessageStamp::class)->getMessage());
        $this->assertEquals([ErrorDetailsStamp::create($exception)], $dispatched[1]->all(ErrorDetailsStamp::class));
        $this->assertSame('the_bus', $dispatched[1]->last(BusNameStamp::class)->getBusName());
        $this->assertSame('my_receiver', $event->getEnvelope()->last(SentToFailureTransportStamp::class)?->getOriginalReceiverName());
    }

    public function testTheFailureMessageOfADelayedMessageIsUntrustedWhenThatMessageIs()
    {
        $handlerException = new HandlerFailedException(new Envelope(new DummyMessage('delayed'), [TrustStamp::untrusted(), new DispatchOnFailureStamp(new SecondMessage())]), [new \RuntimeException('The delayed message failed.')]);
        $envelope = new Envelope(new DummyMessage('failed'), [new ReceivedStamp('async'), TrustStamp::trusted()]);

        $dispatched = null;
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function (Envelope $envelope) use (&$dispatched): Envelope {
                return $dispatched = $envelope;
            });

        (new DispatchOnFailureListener($bus))->onMessageFailed(new WorkerMessageFailedEvent($envelope, 'async', new DelayedMessageHandlingException([$handlerException])));

        $this->assertCount(1, $dispatched->all(TrustStamp::class));
        $this->assertFalse($dispatched->last(TrustStamp::class)->isTrusted());
    }

    public function testAFailureMessageSharedWithADelayedMessageIsDispatchedOnceForTheDelayedMessage()
    {
        $failureStamp = new DispatchOnFailureStamp($failure = new SecondMessage());
        $handlerException = new HandlerFailedException(new Envelope($step = new DummyMessage('step'), [$failureStamp]), [new \RuntimeException('The step failed.')]);
        $envelope = new Envelope(new DummyMessage('carrier'), [$failureStamp]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (Envelope $dispatched) use ($failure, $step) {
                $this->assertSame($failure, $dispatched->getMessage());
                $this->assertSame($step, $dispatched->last(FailedMessageStamp::class)->getMessage());
                $this->assertSame(\RuntimeException::class, $dispatched->last(ErrorDetailsStamp::class)->getExceptionClass());

                return true;
            }))
            ->willReturnArgument(0);

        (new DispatchOnFailureListener($bus))->onMessageFailed($event = new WorkerMessageFailedEvent($envelope, 'my_receiver', new DelayedMessageHandlingException([$handlerException])));

        $this->assertSame('my_receiver', $event->getEnvelope()->last(SentToFailureTransportStamp::class)?->getOriginalReceiverName());
    }

    public function testTheFailedMessageReachesTheFailureTransportWhenItsSharedFailureMessageCannotBeDispatched()
    {
        $failureStamp = new DispatchOnFailureStamp(new SecondMessage());
        $handlerException = new HandlerFailedException(new Envelope(new DummyMessage('step'), [$failureStamp]), [new \RuntimeException('The step failed.')]);
        $envelope = new Envelope(new DummyMessage('carrier'), [$failureStamp]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())->method('dispatch')->willThrowException(new \LogicException('No handler for the failure message.'));

        (new DispatchOnFailureListener($bus))->onMessageFailed($event = new WorkerMessageFailedEvent($envelope, 'my_receiver', new DelayedMessageHandlingException([$handlerException])));

        $this->assertNull($event->getEnvelope()->last(SentToFailureTransportStamp::class));
    }

    public function testTheFailureMessagesOfTheDelayedMessagesAreDispatchedWhenTheFailedMessageHasNone()
    {
        $handlerException = new HandlerFailedException(new Envelope($delayed = new DummyMessage('delayed'), [new DispatchOnFailureStamp($failure = new SecondMessage())]), [new \RuntimeException('The delayed message failed.')]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (Envelope $dispatched) use ($failure, $delayed) {
                $this->assertSame($failure, $dispatched->getMessage());
                $this->assertSame($delayed, $dispatched->last(FailedMessageStamp::class)->getMessage());

                return true;
            }))
            ->willReturnArgument(0);

        (new DispatchOnFailureListener($bus))->onMessageFailed($event = new WorkerMessageFailedEvent(new Envelope(new DummyMessage('failed')), 'my_receiver', new DelayedMessageHandlingException([$handlerException])));

        $this->assertNull($event->getEnvelope()->last(SentToFailureTransportStamp::class));
    }

    public function testTheListenerRunsAfterTheRetryListenerAndBeforeTheFailureTransportListener()
    {
        $subscribed = DispatchOnFailureListener::getSubscribedEvents()[WorkerMessageFailedEvent::class];

        $this->assertSame(SendFailedMessageForRetryListener::class, $subscribed['after']);
        $this->assertSame(SendFailedMessageToFailureTransportListener::class, $subscribed['before']);
        $this->assertLessThan(SendFailedMessageForRetryListener::getSubscribedEvents()[WorkerMessageFailedEvent::class]['priority'], $subscribed['priority']);
        $this->assertGreaterThan(SendFailedMessageToFailureTransportListener::getSubscribedEvents()[WorkerMessageFailedEvent::class][1], $subscribed['priority']);
    }

    private function dispatchFailureMessage(Envelope $envelope): Envelope
    {
        $dispatched = null;
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function (Envelope $envelope) use (&$dispatched): Envelope {
                return $dispatched = $envelope;
            });

        (new DispatchOnFailureListener($bus))->onMessageFailed(new WorkerMessageFailedEvent($envelope, 'async', new \RuntimeException('It failed.')));

        return $dispatched;
    }
}
