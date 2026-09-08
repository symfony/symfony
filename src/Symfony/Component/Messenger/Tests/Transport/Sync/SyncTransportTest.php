<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\Sync;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\SyncMessageFailedEvent;
use Symfony\Component\Messenger\Event\SyncMessageRetryingEvent;
use Symfony\Component\Messenger\Exception\DelayedMessageHandlingException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\InvalidMessageSignatureException;
use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\DispatchAfterCurrentBusMiddleware;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;
use Symfony\Component\Messenger\Retry\RetryStrategyInterface;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Component\Messenger\Stamp\DispatchOnFailureStamp;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\FailedMessageStamp;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\OutboxStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\RedispatchStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Stamp\TrustStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\SecondMessage;
use Symfony\Component\Messenger\Tests\Fixtures\ThirdMessage;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SigningSerializer;
use Symfony\Component\Messenger\Transport\Sync\SyncTransport;

class SyncTransportTest extends TestCase
{
    public function testSend()
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($arg) {
                $this->assertInstanceOf(Envelope::class, $arg);

                return true;
            }))
            ->willReturnArgument(0);
        $message = new \stdClass();
        $envelope = new Envelope($message);
        $transport = new SyncTransport($bus);
        $envelope = $transport->send($envelope);

        $this->assertSame($message, $envelope->getMessage());
        $this->assertNotNull($envelope->last(ReceivedStamp::class));
    }

    public function testSendHandlesTheMessageOnce()
    {
        $calls = 0;
        $transport = new SyncTransport(self::createBus(static function () use (&$calls) { ++$calls; }));

        $envelope = $transport->send(new Envelope(new DummyMessage('Hey')));

        $this->assertSame(1, $calls);
        $this->assertCount(1, $envelope->all(HandledStamp::class));
        $this->assertCount(1, $envelope->all(ReceivedStamp::class));
        $this->assertSame([], $envelope->all(RedeliveryStamp::class));
    }

    public function testSendRethrowsWithoutRetryStrategyNorFailureSender()
    {
        $calls = 0;
        $transport = new SyncTransport(self::createBus(static function () use (&$calls) { throw new \RuntimeException('Attempt '.++$calls); }));

        try {
            $transport->send(new Envelope(new DummyMessage('Hey')));
            $this->fail('An exception should have been thrown.');
        } catch (HandlerFailedException $e) {
            $this->assertSame('Attempt 1', $e->getPrevious()->getMessage());
        }

        $this->assertSame(1, $calls);
    }

    public function testRetryableFailuresAreRetriedUntilTheHandlerSucceeds()
    {
        $calls = 0;
        $recorder = new RecordingMiddleware();
        $bus = self::createBus(static function () use (&$calls) {
            if (3 > ++$calls) {
                throw new \RuntimeException('Attempt '.$calls);
            }
        }, $recorder);
        $transport = new SyncTransport($bus, new MultiplierRetryStrategy(3));

        $envelope = $transport->send(new Envelope(new DummyMessage('Hey'), [new SentStamp(SyncTransport::class, 'my_sync')]));

        $this->assertSame(3, $calls);
        $this->assertCount(1, $envelope->all(HandledStamp::class));
        $this->assertCount(1, $envelope->all(ReceivedStamp::class));
        $this->assertSame([1, 2], self::getRetryCounts($envelope));

        $this->assertCount(3, $recorder->envelopes);
        foreach ($recorder->envelopes as $attempt => $attemptEnvelope) {
            $this->assertCount(1, $attemptEnvelope->all(ReceivedStamp::class));
            $this->assertSame('my_sync', $attemptEnvelope->last(ReceivedStamp::class)->getTransportName());
            $this->assertSame($attempt, RedeliveryStamp::getRetryCountFromEnvelope($attemptEnvelope));
        }
    }

    public function testTheLastExceptionIsRethrownWhenTheStrategyGivesUp()
    {
        $calls = 0;
        $transport = new SyncTransport(self::createBus(static function () use (&$calls) { throw new \RuntimeException('Attempt '.++$calls); }), new MultiplierRetryStrategy(2));

        try {
            $transport->send(new Envelope(new DummyMessage('Hey')));
            $this->fail('An exception should have been thrown.');
        } catch (HandlerFailedException $e) {
            $this->assertSame('Attempt 3', $e->getPrevious()->getMessage());
            $this->assertSame([1, 2], self::getRetryCounts($e->getEnvelope()));
        }

        $this->assertSame(3, $calls);
    }

    public function testTheRetryCountStartsFromTheEnvelope()
    {
        $calls = 0;
        $transport = new SyncTransport(self::createBus(static function () use (&$calls) { throw new \RuntimeException('Attempt '.++$calls); }), new MultiplierRetryStrategy(3));

        try {
            $transport->send(new Envelope(new DummyMessage('Hey'), [new RedeliveryStamp(2)]));
            $this->fail('An exception should have been thrown.');
        } catch (HandlerFailedException $e) {
            $this->assertSame([2, 3], self::getRetryCounts($e->getEnvelope()));
        }

        $this->assertSame(2, $calls);
    }

    public function testUnrecoverableExceptionsAreNotRetried()
    {
        $calls = 0;
        $strategy = $this->createMock(RetryStrategyInterface::class);
        $strategy->expects($this->never())->method('isRetryable');
        $transport = new SyncTransport(self::createBus(static function () use (&$calls) {
            ++$calls;
            throw new UnrecoverableMessageHandlingException('stop');
        }), $strategy);

        $this->expectException(HandlerFailedException::class);
        $this->expectExceptionMessage('stop');

        try {
            $transport->send(new Envelope(new DummyMessage('Hey')));
        } finally {
            $this->assertSame(1, $calls);
        }
    }

    public function testForcedRetriesAreStillBoundedByTheStrategy()
    {
        $calls = 0;
        $transport = new SyncTransport(self::createBus(static function () use (&$calls) {
            ++$calls;
            throw new RecoverableMessageHandlingException('retry');
        }), new MultiplierRetryStrategy(2));

        $this->expectException(HandlerFailedException::class);
        $this->expectExceptionMessage('retry');

        try {
            $transport->send(new Envelope(new DummyMessage('Hey')));
        } finally {
            $this->assertSame(3, $calls);
        }
    }

    public function testRecoverableExceptionsWithoutForcedRetryFollowTheStrategy()
    {
        $calls = 0;
        $transport = new SyncTransport(self::createBus(static function () use (&$calls) {
            ++$calls;
            throw new RecoverableMessageHandlingException('retry', forceRetry: false);
        }), new MultiplierRetryStrategy(0));

        $this->expectException(HandlerFailedException::class);

        try {
            $transport->send(new Envelope(new DummyMessage('Hey')));
        } finally {
            $this->assertSame(1, $calls);
        }
    }

    #[DataProvider('provideNestedExceptions')]
    public function testNestedExceptionsFollowTheWorkerRules(array $exceptions, int $expectedCalls)
    {
        $calls = 0;
        $handlers = [];
        foreach ($exceptions as $alias => $exception) {
            $handlers[] = new HandlerDescriptor(static function () use (&$calls, $exception) {
                ++$calls;
                throw $exception;
            }, ['alias' => $alias]);
        }
        $transport = new SyncTransport(self::createBus(...$handlers), new MultiplierRetryStrategy(1));

        $this->expectException(HandlerFailedException::class);

        try {
            $transport->send(new Envelope(new DummyMessage('Hey')));
        } finally {
            $this->assertSame($expectedCalls, $calls);
        }
    }

    public static function provideNestedExceptions(): iterable
    {
        yield 'all unrecoverable' => [['a' => new UnrecoverableMessageHandlingException('stop'), 'b' => new UnrecoverableMessageHandlingException('stop')], 2];
        yield 'one forced recoverable' => [['a' => new UnrecoverableMessageHandlingException('stop'), 'b' => new RecoverableMessageHandlingException('retry')], 4];
        yield 'one plain exception' => [['a' => new UnrecoverableMessageHandlingException('stop'), 'b' => new \RuntimeException('no!')], 4];
    }

    public function testRetriesSkipTheHandlersThatSucceeded()
    {
        $firstCalls = $secondCalls = 0;
        $bus = self::createBus(
            new HandlerDescriptor(static function () use (&$firstCalls) { ++$firstCalls; }, ['alias' => 'first']),
            new HandlerDescriptor(static function () use (&$secondCalls) {
                if (2 > ++$secondCalls) {
                    throw new \RuntimeException('Attempt '.$secondCalls);
                }
            }, ['alias' => 'second']),
        );
        $transport = new SyncTransport($bus, new MultiplierRetryStrategy(3));

        $envelope = $transport->send(new Envelope(new DummyMessage('Hey')));

        $this->assertSame(1, $firstCalls);
        $this->assertSame(2, $secondCalls);
        $this->assertCount(2, $envelope->all(HandledStamp::class));
        $this->assertCount(1, $envelope->all(ReceivedStamp::class));
    }

    public function testFailuresAreSentToTheFailureTransportAfterTheRetries()
    {
        $calls = 0;
        $failureTransport = new InMemoryTransport();
        $transport = new SyncTransport(self::createBus(static function () use (&$calls) { throw new \RuntimeException('Attempt '.++$calls); }), new MultiplierRetryStrategy(2), $failureTransport);

        $envelope = $transport->send(new Envelope(new DummyMessage('Hey'), [new SentStamp(SyncTransport::class, 'my_sync')]));

        $this->assertSame(3, $calls);
        $this->assertSame('my_sync', $envelope->last(SentToFailureTransportStamp::class)?->getOriginalReceiverName());

        $this->assertCount(1, $failureTransport->getSent());
        $failed = $failureTransport->getSent()[0];
        $this->assertSame('my_sync', $failed->last(SentToFailureTransportStamp::class)?->getOriginalReceiverName());
        $this->assertSame(0, $failed->last(DelayStamp::class)?->getDelay());
        $this->assertSame([1, 2, 0], self::getRetryCounts($failed));
        $this->assertSame(\RuntimeException::class, $failed->last(ErrorDetailsStamp::class)?->getExceptionClass());
        $this->assertSame('Attempt 3', $failed->last(ErrorDetailsStamp::class)->getExceptionMessage());
        $this->assertNull($failed->last(ReceivedStamp::class));
        $this->assertNull($failed->last(HandledStamp::class));
    }

    public function testFailuresKeepTheReceivedStampsTheyCameWith()
    {
        $failureTransport = new InMemoryTransport();
        $transport = new SyncTransport(self::createBus(static function () { throw new \RuntimeException('no!'); }), new MultiplierRetryStrategy(1), $failureTransport);

        $transport->send(new Envelope(new DummyMessage('Hey'), [new ReceivedStamp('outbox'), new SentStamp(SyncTransport::class, 'my_sync')]));

        $this->assertCount(1, $failureTransport->getSent());
        $this->assertSame(['outbox'], array_map(static fn (ReceivedStamp $stamp): string => $stamp->getTransportName(), $failureTransport->getSent()[0]->all(ReceivedStamp::class)));
    }

    public function testAFailureOfAMessageReceivedUnverifiedIsSignedAsUnverified()
    {
        $failureTransport = new InMemoryTransport();
        $transport = new SyncTransport(self::createBus(static function () { throw new \RuntimeException('no!'); }), null, $failureTransport);

        $transport->send(new Envelope(new DummyMessage('Hey'), [new ReceivedStamp('outbox')]));
        $transport->send(new Envelope(new DummyMessage('Hey'), [TrustStamp::untrusted()]));
        $transport->send(new Envelope(new DummyMessage('Hey')));

        $signingSerializer = new SigningSerializer(new PhpSerializer(), 'secret', ['*']);
        [$received, $untrusted, $dispatched] = $failureTransport->getSent();

        $this->assertSame('unverified', $signingSerializer->encode($received)['headers']['Sign-Trust'] ?? null);
        $this->assertSame('unverified', $signingSerializer->encode($untrusted)['headers']['Sign-Trust'] ?? null);
        $this->assertArrayNotHasKey('Sign-Trust', $signingSerializer->encode($dispatched)['headers']);
    }

    public function testEnvelopesDispatchedInThisProcessAreHandledTrusted()
    {
        $calls = 0;
        $recorder = new RecordingMiddleware();
        $transport = new SyncTransport(self::createBus(static function () use (&$calls) {
            if (2 > ++$calls) {
                throw new \RuntimeException('Attempt '.$calls);
            }
        }, $recorder), new MultiplierRetryStrategy(1));

        $envelope = $transport->send(new Envelope(new DummyMessage('Hey')));

        $this->assertCount(2, $recorder->envelopes);
        foreach ($recorder->envelopes as $attemptEnvelope) {
            $this->assertNotNull($attemptEnvelope->last(ReceivedStamp::class));
            $this->assertTrue($attemptEnvelope->last(TrustStamp::class)?->isTrusted());
        }
        $this->assertCount(1, $envelope->all(TrustStamp::class));
        $this->assertTrue($envelope->last(TrustStamp::class)->isTrusted());
    }

    public function testEnvelopesReceivedFromATransportAreHandledWithTheirOwnTrust()
    {
        $recorder = new RecordingMiddleware();
        $transport = new SyncTransport(self::createBus(static function () {}, $recorder));
        $untrusted = TrustStamp::untrusted();
        $trusted = TrustStamp::trusted();

        $transport->send(new Envelope(new DummyMessage('Hey'), [new ReceivedStamp('outbox')]));
        $transport->send(new Envelope(new DummyMessage('Hey'), [$untrusted]));
        $transport->send(new Envelope(new DummyMessage('Hey'), [new ReceivedStamp('scheduler_default'), $trusted]));

        [$received, $receivedUntrusted, $receivedTrusted] = $recorder->envelopes;

        $this->assertNull($received->last(TrustStamp::class));
        $this->assertSame([$untrusted], $receivedUntrusted->all(TrustStamp::class));
        $this->assertSame([$trusted], $receivedTrusted->all(TrustStamp::class));
    }

    public function testMessagesDispatchedOnTheBusAreTrustedButNotTheRelayedOnes()
    {
        $senders = new Container();
        $recorder = new RecordingMiddleware();
        $bus = new MessageBus([
            new SendMessageMiddleware(new SendersLocator([DummyMessage::class => ['sync']], $senders)),
            $recorder,
            new HandleMessageMiddleware(new HandlersLocator([DummyMessage::class => [static function () {}]])),
        ]);
        $senders->set('sync', new SyncTransport($bus));

        $bus->dispatch(new DummyMessage('Hey'));
        $bus->dispatch(new Envelope(new DummyMessage('Hey'), [new ReceivedStamp('outbox'), new OutboxStamp('sync')]));

        [$dispatched, $relayed] = $recorder->envelopes;

        $this->assertSame('sync', $dispatched->last(ReceivedStamp::class)?->getTransportName());
        $this->assertTrue($dispatched->last(TrustStamp::class)?->isTrusted());
        $this->assertSame('sync', $relayed->last(ReceivedStamp::class)?->getTransportName());
        $this->assertNull($relayed->last(TrustStamp::class));
    }

    public function testHandlersThatRequireASignatureRefuseTheEnvelopesMarkedUntrusted()
    {
        $calls = 0;
        $senders = new Container();
        $recorder = new RecordingMiddleware();
        $bus = new MessageBus([
            new SendMessageMiddleware(new SendersLocator([DummyMessage::class => ['sync']], $senders)),
            $recorder,
            new HandleMessageMiddleware(new HandlersLocator([DummyMessage::class => [new HandlerDescriptor(static function () use (&$calls) { ++$calls; }, ['sign' => true])]])),
        ]);
        $senders->set('sync', new SyncTransport($bus, new MultiplierRetryStrategy(3)));

        $bus->dispatch(new DummyMessage('Hey'));
        $this->assertSame(1, $calls);

        try {
            $bus->dispatch(new DummyMessage('Hey'), [TrustStamp::untrusted()]);
            $this->fail('Exception not thrown.');
        } catch (HandlerFailedException $e) {
            $this->assertCount(1, $e->getWrappedExceptions(InvalidMessageSignatureException::class));
        }

        $this->assertSame(1, $calls);
        $this->assertCount(2, $recorder->envelopes);
    }

    public function testFailuresAreSentToTheFailureTransportWithoutRetryStrategy()
    {
        $calls = 0;
        $failureTransport = new InMemoryTransport();
        $transport = new SyncTransport(self::createBus(static function () use (&$calls) {
            ++$calls;
            throw new \RuntimeException('no!');
        }), null, $failureTransport);

        $envelope = $transport->send(new Envelope(new DummyMessage('Hey')));

        $this->assertSame(1, $calls);
        $this->assertSame('sync', $envelope->last(SentToFailureTransportStamp::class)?->getOriginalReceiverName());
        $this->assertCount(1, $failureTransport->getSent());
        $this->assertSame([0], self::getRetryCounts($failureTransport->getSent()[0]));
    }

    public function testAFailureMessageIsDispatchedInsteadOfSendingTheMessageToTheFailureTransport()
    {
        $recorder = new RecordingMiddleware();
        $failureTransport = new InMemoryTransport();
        $transport = new SyncTransport(self::createBusHandlingTheFailureMessage($recorder), new MultiplierRetryStrategy(1), $failureTransport);

        $envelope = $transport->send(new Envelope($failed = new DummyMessage('Hey'), [new BusNameStamp('the_bus'), new DispatchOnFailureStamp($failure = new SecondMessage())]));

        $this->assertSame([], $failureTransport->getSent());
        $this->assertSame($failed, $envelope->getMessage());
        $this->assertNull($envelope->last(SentToFailureTransportStamp::class));
        $this->assertSame(\RuntimeException::class, $envelope->last(ErrorDetailsStamp::class)?->getExceptionClass());
        $this->assertSame('no!', $envelope->last(ErrorDetailsStamp::class)->getExceptionMessage());

        $this->assertCount(3, $recorder->envelopes);
        $dispatched = $recorder->envelopes[2];
        $this->assertSame($failure, $dispatched->getMessage());
        $this->assertSame($failed, $dispatched->last(FailedMessageStamp::class)?->getMessage());
        $this->assertEquals($envelope->last(ErrorDetailsStamp::class), $dispatched->last(ErrorDetailsStamp::class));
        $this->assertSame('the_bus', $dispatched->last(BusNameStamp::class)?->getBusName());
        $this->assertSame([], $dispatched->all(ReceivedStamp::class));
        $this->assertSame([], $dispatched->all(TrustStamp::class));
    }

    public function testTheFailureMessagesOfTheDelayedMessagesThatFailedAreDispatchedBeforeTheMessageIsSentToTheFailureTransport()
    {
        $recorder = new RecordingMiddleware();
        $failureTransport = new InMemoryTransport();
        $delayed = new Envelope($second = new SecondMessage(), [new DispatchAfterCurrentBusStamp(), new DispatchOnFailureStamp($failure = new ThirdMessage())]);
        $bus = new MessageBus([
            $recorder,
            new DispatchAfterCurrentBusMiddleware(),
            new HandleMessageMiddleware(new HandlersLocator([
                DummyMessage::class => [static function () use (&$bus, $delayed) { $bus->dispatch($delayed); }],
                SecondMessage::class => [static function () { throw new \RuntimeException('no!'); }],
                ThirdMessage::class => [static function () {}],
            ])),
        ]);
        $transport = new SyncTransport($bus, null, $failureTransport);

        $envelope = $transport->send(new Envelope($first = new DummyMessage('Hey')));

        $this->assertSame('sync', $envelope->last(SentToFailureTransportStamp::class)?->getOriginalReceiverName());
        $this->assertCount(1, $failureTransport->getSent());
        $this->assertSame($first, $failureTransport->getSent()[0]->getMessage());
        $this->assertSame(DelayedMessageHandlingException::class, $failureTransport->getSent()[0]->last(ErrorDetailsStamp::class)?->getExceptionClass());

        $this->assertCount(3, $recorder->envelopes);
        $dispatched = $recorder->envelopes[2];
        $this->assertSame($failure, $dispatched->getMessage());
        $this->assertSame($second, $dispatched->last(FailedMessageStamp::class)?->getMessage());
        $this->assertSame('no!', $dispatched->last(ErrorDetailsStamp::class)?->getExceptionMessage());
    }

    public function testTheFailureMessageIsUntrustedWhenTheFailedMessageCameFromATransport()
    {
        $recorder = new RecordingMiddleware();
        $transport = new SyncTransport(self::createBusHandlingTheFailureMessage($recorder), null, new InMemoryTransport());

        $transport->send(new Envelope(new DummyMessage('Hey'), [new ReceivedStamp('outbox'), new DispatchOnFailureStamp(new SecondMessage())]));

        $this->assertCount(2, $recorder->envelopes);
        $this->assertCount(1, $recorder->envelopes[1]->all(TrustStamp::class));
        $this->assertFalse($recorder->envelopes[1]->last(TrustStamp::class)->isTrusted());
    }

    public function testTheMessageIsSentToTheFailureTransportWhenItsFailureMessageCannotBeDispatched()
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with($this->stringContains('{failure_class}'), $this->callback(function (array $context) {
            $this->assertSame(DummyMessage::class, $context['class']);
            $this->assertSame(SecondMessage::class, $context['failure_class']);
            $this->assertInstanceOf(NoHandlerForMessageException::class, $context['exception']);

            return true;
        }));
        $failureTransport = new InMemoryTransport();
        $transport = new SyncTransport(self::createBus(static function () { throw new \RuntimeException('no!'); }), null, $failureTransport, null, $logger);

        $envelope = $transport->send(new Envelope(new DummyMessage('Hey'), [new DispatchOnFailureStamp(new SecondMessage())]));

        $this->assertSame('sync', $envelope->last(SentToFailureTransportStamp::class)?->getOriginalReceiverName());
        $this->assertCount(1, $failureTransport->getSent());
        $this->assertSame('no!', $failureTransport->getSent()[0]->last(ErrorDetailsStamp::class)?->getExceptionMessage());
    }

    public function testTheFailureMessageIsNotDispatchedWhenTheExceptionIsRethrown()
    {
        $recorder = new RecordingMiddleware();
        $transport = new SyncTransport(self::createBus(static function () { throw new \RuntimeException('no!'); }, $recorder));

        try {
            $transport->send(new Envelope(new DummyMessage('Hey'), [new DispatchOnFailureStamp(new SecondMessage())]));
            $this->fail('An exception should have been thrown.');
        } catch (HandlerFailedException) {
        }

        $this->assertCount(1, $recorder->envelopes);
    }

    public function testMessagesQueuedByAFailedAttemptAreNotDispatchedAfterARetry()
    {
        $calls = $secondMessageCalls = 0;
        $senders = new Container();
        $bus = self::createBusWithSyncSender($senders, static function () use (&$calls, &$bus) {
            $bus->dispatch(new Envelope(new SecondMessage(), [new DispatchAfterCurrentBusStamp()]));
            if (2 > ++$calls) {
                throw new \RuntimeException('Attempt '.$calls);
            }
        }, static function () use (&$secondMessageCalls) { ++$secondMessageCalls; });
        $senders->set('sync', new SyncTransport($bus, new MultiplierRetryStrategy(3)));

        $bus->dispatch(new DummyMessage('Hey'));

        $this->assertSame(2, $calls);
        $this->assertSame(1, $secondMessageCalls);
    }

    public function testMessagesQueuedByAFailedAttemptAreNotDispatchedWhenSentToTheFailureTransport()
    {
        $calls = $secondMessageCalls = 0;
        $senders = new Container();
        $bus = self::createBusWithSyncSender($senders, static function () use (&$calls, &$bus) {
            $bus->dispatch(new Envelope(new SecondMessage(), [new DispatchAfterCurrentBusStamp()]));
            throw new \RuntimeException('Attempt '.++$calls);
        }, static function () use (&$secondMessageCalls) { ++$secondMessageCalls; });
        $senders->set('sync', new SyncTransport($bus, null, $failureTransport = new InMemoryTransport()));

        $envelope = $bus->dispatch(new DummyMessage('Hey'));

        $this->assertSame(1, $calls);
        $this->assertSame(0, $secondMessageCalls);
        $this->assertNotNull($envelope->last(SentToFailureTransportStamp::class));
        $this->assertCount(1, $failureTransport->getSent());
    }

    public function testARedispatchedMessageRoutedToTheSyncTransportIsHandledOnce()
    {
        $calls = 0;
        $senders = new Container();
        $bus = new MessageBus([
            new LoopGuardMiddleware(),
            new SendMessageMiddleware(new SendersLocator([DummyMessage::class => ['sync']], $senders)),
            new HandleMessageMiddleware(new HandlersLocator([DummyMessage::class => [static function () use (&$calls) { ++$calls; }]])),
        ]);
        $senders->set('sync', new SyncTransport($bus));

        $envelope = $bus->dispatch(new Envelope(new DummyMessage('Hey'), [new ReceivedStamp('scheduler_default'), TrustStamp::trusted(), new RedispatchStamp()]));

        $this->assertSame(1, $calls);
        $this->assertCount(1, $envelope->all(HandledStamp::class));
        $this->assertCount(1, $envelope->all(SentStamp::class));
        $this->assertNull($envelope->last(RedispatchStamp::class));
    }

    public function testEventsAreDispatchedForARetryFollowedBySuccess()
    {
        $firstCalls = $secondCalls = 0;
        $bus = self::createBus(
            new HandlerDescriptor(static function () use (&$firstCalls) { ++$firstCalls; }, ['alias' => 'first']),
            new HandlerDescriptor(static function () use (&$secondCalls) {
                if (2 > ++$secondCalls) {
                    throw new \RuntimeException('Attempt '.$secondCalls);
                }
            }, ['alias' => 'second']),
        );
        $events = [];
        $transport = new SyncTransport($bus, new MultiplierRetryStrategy(3), null, self::createDispatcher($events));

        $transport->send(new Envelope(new DummyMessage('Hey'), [new SentStamp(SyncTransport::class, 'my_sync')]));

        $this->assertSame([SyncMessageFailedEvent::class, SyncMessageRetryingEvent::class], array_map(static fn (object $event) => $event::class, $events));

        [$failed, $retried] = $events;
        $this->assertSame('my_sync', $failed->getTransportName());
        $this->assertTrue($failed->willRetry());
        $this->assertInstanceOf(HandlerFailedException::class, $failed->getThrowable());
        $this->assertSame('Attempt 1', $failed->getThrowable()->getPrevious()->getMessage());
        $this->assertSame('Closure@first', $failed->getEnvelope()->last(HandledStamp::class)?->getHandlerName());
        $this->assertNull($failed->getEnvelope()->last(ReceivedStamp::class));
        $this->assertSame([], $failed->getEnvelope()->all(RedeliveryStamp::class));

        $this->assertSame('my_sync', $retried->getTransportName());
        $this->assertSame([1], self::getRetryCounts($retried->getEnvelope()));
        $this->assertSame($failed->getEnvelope()->all(HandledStamp::class), $retried->getEnvelope()->all(HandledStamp::class));
        $this->assertNull($retried->getEnvelope()->last(ReceivedStamp::class));
        $this->assertSame(1, $firstCalls);
    }

    public function testEventsAreDispatchedUntilTheMessageIsSentToTheFailureTransport()
    {
        $calls = 0;
        $events = [];
        $failureTransport = new InMemoryTransport();
        $transport = new SyncTransport(self::createBus(static function () use (&$calls) { throw new \RuntimeException('Attempt '.++$calls); }), new MultiplierRetryStrategy(1), $failureTransport, self::createDispatcher($events));

        $transport->send(new Envelope(new DummyMessage('Hey')));

        $this->assertSame([SyncMessageFailedEvent::class, SyncMessageRetryingEvent::class, SyncMessageFailedEvent::class], array_map(static fn (object $event) => $event::class, $events));
        $this->assertTrue($events[0]->willRetry());
        $this->assertFalse($events[2]->willRetry());
        $this->assertSame('sync', $events[2]->getTransportName());
        $this->assertSame('Attempt 2', $events[2]->getThrowable()->getPrevious()->getMessage());

        $stampsWithoutTheFailureOnes = $failureTransport->getSent()[0]->all();
        unset($stampsWithoutTheFailureOnes[SentToFailureTransportStamp::class], $stampsWithoutTheFailureOnes[DelayStamp::class], $stampsWithoutTheFailureOnes[ErrorDetailsStamp::class], $stampsWithoutTheFailureOnes[TransportMessageIdStamp::class]);
        array_pop($stampsWithoutTheFailureOnes[RedeliveryStamp::class]);
        $this->assertSame($stampsWithoutTheFailureOnes, $events[2]->getEnvelope()->all());
    }

    public function testAFailedEventIsDispatchedBeforeTheExceptionIsRethrown()
    {
        $events = [];
        $transport = new SyncTransport(self::createBus(static function () { throw new \RuntimeException('no!'); }), null, null, self::createDispatcher($events));

        try {
            $transport->send(new Envelope(new DummyMessage('Hey')));
            $this->fail('An exception should have been thrown.');
        } catch (HandlerFailedException $e) {
        }

        $this->assertCount(1, $events);
        $this->assertInstanceOf(SyncMessageFailedEvent::class, $events[0]);
        $this->assertFalse($events[0]->willRetry());
        $this->assertSame($e, $events[0]->getThrowable());
        $this->assertSame('sync', $events[0]->getTransportName());
    }

    public function testNoEventIsDispatchedWhenTheMessageIsHandled()
    {
        $events = [];
        $transport = new SyncTransport(self::createBus(static function () {}), new MultiplierRetryStrategy(3), new InMemoryTransport(), self::createDispatcher($events));

        $transport->send(new Envelope(new DummyMessage('Hey')));

        $this->assertSame([], $events);
    }

    public function testRetriesAndFailuresAreLogged()
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with($this->stringContains('Retrying'), $this->callback(function (array $context) {
            $this->assertSame(DummyMessage::class, $context['class']);
            $this->assertSame(1, $context['retryCount']);
            $this->assertInstanceOf(HandlerFailedException::class, $context['exception']);

            return true;
        }));
        $logger->expects($this->once())->method('critical')->with($this->stringContains('failure transport'), $this->callback(function (array $context) {
            $this->assertSame(1, $context['retryCount']);

            return true;
        }));

        $transport = new SyncTransport(self::createBus(static function () { throw new \RuntimeException('no!'); }), new MultiplierRetryStrategy(1), new InMemoryTransport(), null, $logger);

        $transport->send(new Envelope(new DummyMessage('Hey')));
    }

    private static function createBus(callable|HandlerDescriptor|MiddlewareInterface ...$handlers): MessageBus
    {
        $middleware = [];
        foreach ($handlers as $i => $handler) {
            if ($handler instanceof MiddlewareInterface) {
                $middleware[] = $handler;
                unset($handlers[$i]);
            }
        }
        $middleware[] = new HandleMessageMiddleware(new HandlersLocator([DummyMessage::class => array_values($handlers)]));

        return new MessageBus($middleware);
    }

    private static function createBusWithSyncSender(Container $senders, callable $handler, callable $secondMessageHandler): MessageBus
    {
        return new MessageBus([
            new DispatchAfterCurrentBusMiddleware(),
            new SendMessageMiddleware(new SendersLocator([DummyMessage::class => ['sync']], $senders)),
            new HandleMessageMiddleware(new HandlersLocator([DummyMessage::class => [$handler], SecondMessage::class => [$secondMessageHandler]])),
        ]);
    }

    private static function createBusHandlingTheFailureMessage(MiddlewareInterface $recorder): MessageBus
    {
        return new MessageBus([
            $recorder,
            new HandleMessageMiddleware(new HandlersLocator([DummyMessage::class => [static function () { throw new \RuntimeException('no!'); }], SecondMessage::class => [static function () {}]])),
        ]);
    }

    /**
     * @param object[] $events
     */
    private static function createDispatcher(array &$events): EventDispatcher
    {
        $dispatcher = new EventDispatcher();
        $listener = static function (object $event) use (&$events) { $events[] = $event; };
        $dispatcher->addListener(SyncMessageFailedEvent::class, $listener);
        $dispatcher->addListener(SyncMessageRetryingEvent::class, $listener);

        return $dispatcher;
    }

    /**
     * @return int[]
     */
    private static function getRetryCounts(Envelope $envelope): array
    {
        return array_map(static fn (RedeliveryStamp $stamp) => $stamp->getRetryCount(), $envelope->all(RedeliveryStamp::class));
    }
}

final class LoopGuardMiddleware implements MiddlewareInterface
{
    private int $depth = 0;

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if (5 < ++$this->depth) {
            throw new \LogicException('The message is dispatched in a loop.');
        }

        try {
            return $stack->next()->handle($envelope, $stack);
        } finally {
            --$this->depth;
        }
    }
}

final class RecordingMiddleware implements MiddlewareInterface
{
    /**
     * @var Envelope[]
     */
    public array $envelopes = [];

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $this->envelopes[] = $envelope;

        return $stack->next()->handle($envelope, $stack);
    }
}
