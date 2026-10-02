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
use Psr\Log\AbstractLogger;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\EventListener\DispatchOnFailureListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageForRetryListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageToFailureTransportListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnIdleListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\Exception\DelayedMessageHandlingException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\AddBusNameStampMiddleware;
use Symfony\Component\Messenger\Middleware\ChainMiddleware;
use Symfony\Component\Messenger\Middleware\DispatchAfterCurrentBusMiddleware;
use Symfony\Component\Messenger\Middleware\DispatchOnFailureMiddleware;
use Symfony\Component\Messenger\Middleware\FailedMessageProcessingMiddleware;
use Symfony\Component\Messenger\Middleware\FlowContextMiddleware;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;
use Symfony\Component\Messenger\Retry\RetryStrategyInterface;
use Symfony\Component\Messenger\RoutableMessageBus;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\CausationStamp;
use Symfony\Component\Messenger\Stamp\ChainStamp;
use Symfony\Component\Messenger\Stamp\CorrelationStamp;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Component\Messenger\Stamp\DispatchOnFailureStamp;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\FailedMessageStamp;
use Symfony\Component\Messenger\Stamp\MessageIdStamp;
use Symfony\Component\Messenger\Stamp\PropagatedStampInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TrustStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyCommand;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\SecondMessage;
use Symfony\Component\Messenger\Tests\Fixtures\ThirdMessage;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Messenger\Transport\Sync\SyncTransport;
use Symfony\Component\Messenger\Worker;

class DispatchOnFailureIntegrationTest extends TestCase
{
    private array $handled = [];
    private array $attempted = [];
    private array $failing = [];
    private array $failureEnvelopes = [];
    private array $errors = [];

    /**
     * @var \WeakMap<object, Envelope> The message that the handler of each message dispatches with a DispatchAfterCurrentBusStamp
     */
    private \WeakMap $delayedMessages;

    private Container $senders;
    private InMemoryTransport $transport;
    private ?InMemoryTransport $failureTransport = null;
    private ?RetryStrategyInterface $retryStrategy = null;

    protected function setUp(): void
    {
        $this->handled = [];
        $this->attempted = [];
        $this->failing = [];
        $this->failureEnvelopes = [];
        $this->errors = [];
        $this->delayedMessages = new \WeakMap();
        $this->senders = new Container();
        $this->transport = new InMemoryTransport();
        $this->failureTransport = null;
        $this->retryStrategy = null;
    }

    public function testFailureMessageIsDispatchedWhenASingleMessageFailsSynchronously()
    {
        $bus = $this->createBus([DummyMessage::class]);

        try {
            $bus->dispatch($first = new DummyMessage('first'), [new DispatchOnFailureStamp($failure = new ThirdMessage())]);
            $this->fail('The failure of the message should have been reported.');
        } catch (HandlerFailedException) {
        }

        $this->assertSame([$failure], $this->handled);
        $this->assertCount(1, $this->failureEnvelopes);
        $this->assertSame($first, $this->failureEnvelopes[0]->last(FailedMessageStamp::class)->getMessage());
        $this->assertSame(\RuntimeException::class, $this->failureEnvelopes[0]->last(ErrorDetailsStamp::class)->getExceptionClass());
    }

    public function testFailureMessageIsDispatchedOnceWhenASynchronousStepOfAChainFails()
    {
        $bus = $this->createBus([SecondMessage::class]);

        try {
            $bus->dispatch($first = new DummyMessage('first'), [new ChainStamp($second = new SecondMessage(), new ThirdMessage()), new DispatchOnFailureStamp($failure = new DummyMessage('failure'))]);
            $this->fail('The failure of the second step should have been reported.');
        } catch (DelayedMessageHandlingException) {
        }

        $this->assertSame([$first, $failure], $this->handled);
        $this->assertCount(1, $this->failureEnvelopes);
        $this->assertSame($second, $this->failureEnvelopes[0]->last(FailedMessageStamp::class)->getMessage());
    }

    public function testFailureMessageIsDispatchedOnceWhenAnAsynchronousStepOfAChainFailsInAWorker()
    {
        $bus = $this->createBus([SecondMessage::class], [SecondMessage::class => ['async']]);

        $bus->dispatch($first = new DummyMessage('first'), [new ChainStamp($second = new SecondMessage(), new ThirdMessage()), new DispatchOnFailureStamp($failure = new DummyMessage('failure'))]);

        $this->assertSame([$first], $this->handled);
        $this->assertSame([], $this->failureEnvelopes);

        $this->runWorker($bus);

        $this->assertSame([$first, $failure], $this->handled);
        $this->assertCount(1, $this->failureEnvelopes);
        $this->assertSame($second, $this->failureEnvelopes[0]->last(FailedMessageStamp::class)->getMessage());
        $this->assertSame(\RuntimeException::class, $this->failureEnvelopes[0]->last(ErrorDetailsStamp::class)->getExceptionClass());
        $this->assertSame([], $this->failureEnvelopes[0]->all(TrustStamp::class));
        $this->assertCount(1, $this->transport->getRejected());
    }

    public function testFailureMessageIsDispatchedOnceWhenAStepHandledInTheWorkerProcessFails()
    {
        $this->failureTransport = new InMemoryTransport();
        $bus = $this->createBus([SecondMessage::class], [DummyMessage::class => ['async']]);

        $bus->dispatch($first = new DummyMessage('first'), [new ChainStamp($second = new SecondMessage()), new DispatchOnFailureStamp($failure = new ThirdMessage())]);
        $this->runWorker($bus, 1, 'schedule');

        $this->assertSame([$first, $failure], $this->handled);
        $this->assertCount(1, $this->failureEnvelopes);
        $this->assertSame($second, $this->failureEnvelopes[0]->last(FailedMessageStamp::class)->getMessage());
        $this->assertSame(\RuntimeException::class, $this->failureEnvelopes[0]->last(ErrorDetailsStamp::class)->getExceptionClass());
        $this->assertSame([], $this->failureTransport->getSent());
        $this->assertCount(1, $this->transport->getRejected());
    }

    public function testTheFailureMessageOfAStepHandledInTheWorkerProcessIsDispatchedOnceWhenTheMessageThatDispatchedItFailsForGood()
    {
        $this->transport = new InMemoryTransport(null, $clock = new MockClock());
        $this->failureTransport = new InMemoryTransport();
        $this->retryStrategy = new MultiplierRetryStrategy(2, 0, 1, 0, 0);
        $bus = $this->createBus([SecondMessage::class], [DummyMessage::class => ['async']]);

        $bus->dispatch($first = new DummyMessage('first'), [new ChainStamp(new Envelope($second = new SecondMessage(), [new DispatchOnFailureStamp($failure = new ThirdMessage())]))]);
        $this->runWorker($bus, 1, 'schedule');
        $clock->sleep(1);
        $this->runWorker($bus, 1, 'schedule');

        $this->assertSame([], $this->failureEnvelopes);

        $clock->sleep(1);
        $this->runWorker($bus, 1, 'schedule');

        $this->assertSame([$second, $second, $second, $failure], array_values(array_filter($this->attempted, static fn (object $message): bool => !$message instanceof DummyMessage)));
        $this->assertCount(1, $this->failureEnvelopes);
        $this->assertSame($second, $this->failureEnvelopes[0]->last(FailedMessageStamp::class)->getMessage());
        $this->assertSame(\RuntimeException::class, $this->failureEnvelopes[0]->last(ErrorDetailsStamp::class)->getExceptionClass());
        $this->assertCount(1, $this->failureTransport->getSent());
        $this->assertSame($first, $this->failureTransport->getSent()[0]->getMessage());
    }

    public function testTheFailureMessageOfADelayedMessageIsDispatchedOnceWhenTheMessageThatDispatchedItFailsForGoodInAWorker()
    {
        $this->transport = new InMemoryTransport(null, $clock = new MockClock());
        $this->failureTransport = new InMemoryTransport();
        $this->retryStrategy = new MultiplierRetryStrategy(2, 0, 1, 0, 0);
        $this->delayedMessages[$first = new DummyMessage('first')] = new Envelope($second = new SecondMessage(), [new DispatchOnFailureStamp($failure = new ThirdMessage())]);
        $bus = $this->createBus([SecondMessage::class], [DummyMessage::class => ['async']]);

        $bus->dispatch($first);
        $this->runWorker($bus);
        $clock->sleep(1);
        $this->runWorker($bus);

        $this->assertSame([], $this->failureEnvelopes);

        $clock->sleep(1);
        $this->runWorker($bus);

        $this->assertSame([$first, $second, $first, $second, $first, $second, $failure], $this->attempted);
        $this->assertCount(1, $this->failureEnvelopes);
        $this->assertSame($second, $this->failureEnvelopes[0]->last(FailedMessageStamp::class)->getMessage());
        $this->assertCount(1, $this->failureTransport->getSent());
        $this->assertSame($first, $this->failureTransport->getSent()[0]->getMessage());
    }

    public function testTheFailureMessageOfAChainStaysWhenTheMessageIsRetriedAfterItsNextStepCouldNotBeSent()
    {
        $this->transport = new InMemoryTransport(null, $clock = new MockClock());
        $flaky = new InMemoryTransport();
        $this->senders->set('flaky', new class($flaky) implements SenderInterface {
            private int $failures = 1;

            public function __construct(private SenderInterface $sender)
            {
            }

            public function send(Envelope $envelope): Envelope
            {
                return 0 < $this->failures-- ? throw new TransportException('The transport is unavailable.') : $this->sender->send($envelope);
            }
        });
        $this->retryStrategy = new MultiplierRetryStrategy(1, 0, 1, 0, 0);
        $bus = $this->createBus([], [DummyMessage::class => ['async'], SecondMessage::class => ['flaky']]);

        $bus->dispatch(new DummyMessage('first'), [new ChainStamp($second = new SecondMessage()), new DispatchOnFailureStamp($failure = new ThirdMessage())]);
        $this->runWorker($bus);
        $clock->sleep(1);
        $this->runWorker($bus);
        $this->runWorker($bus, 1, 'flaky', $flaky);

        $this->assertCount(2, $sent = $this->transport->getSent());
        $this->assertSame($failure, $sent[1]->last(DispatchOnFailureStamp::class)?->getMessage());
        $this->assertCount(1, $flaky->getSent());
        $this->assertSame($failure, $flaky->getSent()[0]->last(DispatchOnFailureStamp::class)?->getMessage());
        $this->assertSame($second, end($this->handled));
        $this->assertSame([], $this->failureEnvelopes);
    }

    public function testTheFailureMessageOfADelayedMessageIsDispatchedOnceAndTheCallerGetsItsFailure()
    {
        $this->delayedMessages[$first = new DummyMessage('first')] = new Envelope($second = new SecondMessage(), [new DispatchOnFailureStamp($failure = new ThirdMessage())]);
        $bus = $this->createBus([SecondMessage::class]);

        try {
            $bus->dispatch($first);
            $this->fail('The failure of the delayed message should have been reported.');
        } catch (DelayedMessageHandlingException) {
        }

        $this->assertSame([$first, $failure], $this->handled);
        $this->assertCount(1, $this->failureEnvelopes);
        $this->assertSame($second, $this->failureEnvelopes[0]->last(FailedMessageStamp::class)->getMessage());
        $this->assertSame(\RuntimeException::class, $this->failureEnvelopes[0]->last(ErrorDetailsStamp::class)->getExceptionClass());
        $this->assertSame('the_bus', $this->failureEnvelopes[0]->last(BusNameStamp::class)?->getBusName());
    }

    public function testTheFailureMessageOfADelayedMessageIsDispatchedBeforeTheOneOfTheMessageThatDispatchedIt()
    {
        $this->delayedMessages[$first = new DummyMessage('first')] = new Envelope($second = new SecondMessage(), [new DispatchOnFailureStamp($secondFailure = new ThirdMessage())]);
        $bus = $this->createBus([SecondMessage::class]);

        try {
            $bus->dispatch($first, [new DispatchOnFailureStamp($firstFailure = new DummyMessage('failure'))]);
            $this->fail('The failure of the delayed message should have been reported.');
        } catch (DelayedMessageHandlingException) {
        }

        $this->assertSame([$first, $secondFailure, $firstFailure], $this->handled);
        $this->assertSame([$second, $first], array_map(static fn (Envelope $envelope): object => $envelope->last(FailedMessageStamp::class)->getMessage(), $this->failureEnvelopes));
        $this->assertSame(\RuntimeException::class, $this->failureEnvelopes[0]->last(ErrorDetailsStamp::class)->getExceptionClass());
        $this->assertSame(DelayedMessageHandlingException::class, $this->failureEnvelopes[1]->last(ErrorDetailsStamp::class)->getExceptionClass());
    }

    public function testTheDelayedMessagesOfAFailureMessageAreHandled()
    {
        $bus = $this->createBus([DummyMessage::class]);

        try {
            $bus->dispatch(new DummyMessage('first'), [new DispatchOnFailureStamp(new Envelope($failure = new ThirdMessage(), [new ChainStamp($next = new SecondMessage())]))]);
            $this->fail('The failure of the message should have been reported.');
        } catch (HandlerFailedException) {
        }

        $this->assertSame([$failure, $next], $this->handled);
    }

    public function testTheFailureMessageCarriesTheFlowContextOfTheMessageThatFailed()
    {
        $bus = $this->createBus([DummyMessage::class]);

        try {
            $bus->dispatch(new DummyMessage('first'), [new DispatchOnFailureStamp(new ThirdMessage())]);
            $this->fail('The failure of the message should have been reported.');
        } catch (HandlerFailedException $e) {
            $failed = $e->getEnvelope();
        }

        $this->assertCount(1, $this->failureEnvelopes);
        $this->assertSame($failed->last(MessageIdStamp::class)->getId(), $this->failureEnvelopes[0]->last(CausationStamp::class)?->getId());
        $this->assertSame($failed->last(CorrelationStamp::class)->getId(), $this->failureEnvelopes[0]->last(CorrelationStamp::class)?->getId());
    }

    public function testTheFailureMessageCarriesTheFlowContextOfTheMessageThatFailedInAWorker()
    {
        $bus = $this->createBus([DummyMessage::class], [DummyMessage::class => ['async']]);

        $sent = $bus->dispatch(new DummyMessage('first'), [new DispatchOnFailureTenantStamp('acme'), new DispatchOnFailureStamp(new ThirdMessage())]);
        $this->runWorker($bus);

        $this->assertCount(1, $this->failureEnvelopes);
        $this->assertSame($sent->last(MessageIdStamp::class)->getId(), $this->failureEnvelopes[0]->last(CausationStamp::class)?->getId());
        $this->assertSame($sent->last(CorrelationStamp::class)->getId(), $this->failureEnvelopes[0]->last(CorrelationStamp::class)?->getId());
        $this->assertSame('acme', $this->failureEnvelopes[0]->last(DispatchOnFailureTenantStamp::class)?->tenant);
    }

    public function testTheFailureMessageKeepsThePropagatedStampsItCarries()
    {
        $bus = $this->createBus([DummyMessage::class], [DummyMessage::class => ['async']]);

        $bus->dispatch(new DummyMessage('first'), [new DispatchOnFailureTenantStamp('acme'), new DispatchOnFailureStamp(new Envelope(new ThirdMessage(), [new DispatchOnFailureTenantStamp('own')]))]);
        $this->runWorker($bus);

        $this->assertCount(1, $this->failureEnvelopes);
        $this->assertSame(['own'], array_map(static fn (DispatchOnFailureTenantStamp $stamp): string => $stamp->tenant, $this->failureEnvelopes[0]->all(DispatchOnFailureTenantStamp::class)));
    }

    public function testTheFailureMessageOfADelayedMessageNamesTheDelayedMessageAsItsCause()
    {
        $this->delayedMessages[$first = new DummyMessage('first')] = new Envelope(new SecondMessage(), [new DispatchOnFailureStamp(new ThirdMessage())]);
        $bus = $this->createBus([SecondMessage::class]);

        try {
            $bus->dispatch($first);
            $this->fail('The failure of the delayed message should have been reported.');
        } catch (DelayedMessageHandlingException $e) {
            $delayed = $e->getWrappedExceptions(HandlerFailedException::class)[0]->getEnvelope();
        }

        $this->assertCount(1, $this->failureEnvelopes);
        $this->assertSame($delayed->last(MessageIdStamp::class)->getId(), $this->failureEnvelopes[0]->last(CausationStamp::class)?->getId());
        $this->assertSame($delayed->last(CorrelationStamp::class)->getId(), $this->failureEnvelopes[0]->last(CorrelationStamp::class)?->getId());
    }

    public function testFailureMessageIsUntrustedWhenTheMessageThatFailedInAWorkerIs()
    {
        $this->transport = new InMemoryTransport(new PhpSerializer());
        $bus = $this->createBus([SecondMessage::class], [SecondMessage::class => ['async']]);

        $bus->dispatch(new SecondMessage(), [new DispatchOnFailureStamp(new ThirdMessage())]);
        $this->runWorker($bus);

        $this->assertCount(1, $this->failureEnvelopes);
        $this->assertCount(1, $this->failureEnvelopes[0]->all(TrustStamp::class));
        $this->assertFalse($this->failureEnvelopes[0]->last(TrustStamp::class)->isTrusted());
    }

    public function testFailureMessageIsUntrustedWhenTheMessageThatFailedSynchronouslyIs()
    {
        $bus = $this->createBus([DummyMessage::class]);

        try {
            $bus->dispatch(new DummyMessage('first'), [TrustStamp::untrusted(), new DispatchOnFailureStamp(new ThirdMessage())]);
            $this->fail('The failure of the message should have been reported.');
        } catch (HandlerFailedException) {
        }

        $this->assertCount(1, $this->failureEnvelopes);
        $this->assertCount(1, $this->failureEnvelopes[0]->all(TrustStamp::class));
        $this->assertFalse($this->failureEnvelopes[0]->last(TrustStamp::class)->isTrusted());
    }

    public function testFailureMessageTravelsThroughATransportUsingTheSymfonySerializer()
    {
        $this->transport = new InMemoryTransport(new Serializer());
        $bus = $this->createBus([DummyMessage::class], [DummyMessage::class => ['async'], ThirdMessage::class => ['async']]);

        $bus->dispatch(new DummyMessage('first'), [new DispatchOnFailureStamp(new Envelope(new ThirdMessage(), [new DelayStamp(0)]))]);
        $this->runWorker($bus, 2);

        $this->assertEquals([new ThirdMessage()], $this->handled);
        $received = array_values(array_filter($this->failureEnvelopes, static fn (Envelope $envelope): bool => null !== $envelope->last(ReceivedStamp::class)));
        $this->assertCount(1, $received);
        $this->assertEquals(new DummyMessage('first'), $received[0]->last(FailedMessageStamp::class)->getMessage());
        $this->assertSame(\RuntimeException::class, $received[0]->last(ErrorDetailsStamp::class)->getExceptionClass());
        $this->assertSame(\sprintf('Handling "%s" failed.', DummyMessage::class), $received[0]->last(ErrorDetailsStamp::class)->getExceptionMessage());
    }

    public function testFailureMessageTakesThePlaceOfTheFailureTransportOfASynchronousTransport()
    {
        $failureTransport = new InMemoryTransport();
        $bus = $this->createBus([DummyMessage::class], [DummyMessage::class => ['sync']], new MultiplierRetryStrategy(2, 0), $failureTransport);

        $envelope = $bus->dispatch($first = new DummyMessage('first'), [new DispatchOnFailureStamp($failure = new ThirdMessage())]);

        $this->assertNull($envelope->last(SentToFailureTransportStamp::class));
        $this->assertSame(\RuntimeException::class, $envelope->last(ErrorDetailsStamp::class)?->getExceptionClass());
        $this->assertSame(\sprintf('Handling "%s" failed.', DummyMessage::class), $envelope->last(ErrorDetailsStamp::class)->getExceptionMessage());
        $this->assertSame([], $failureTransport->getSent());
        $this->assertSame([$first, $first, $first, $failure], $this->attempted);
        $this->assertCount(1, $this->failureEnvelopes);
        $this->assertSame($first, $this->failureEnvelopes[0]->last(FailedMessageStamp::class)->getMessage());
        $this->assertSame(\RuntimeException::class, $this->failureEnvelopes[0]->last(ErrorDetailsStamp::class)->getExceptionClass());
        $this->assertSame('the_bus', $this->failureEnvelopes[0]->last(BusNameStamp::class)?->getBusName());
        $this->assertSame([], $this->failureEnvelopes[0]->all(TrustStamp::class));
    }

    public function testASynchronousTransportSendsTheMessageToItsFailureTransportWhenItsFailureMessageCannotBeDispatched()
    {
        $failureTransport = new InMemoryTransport();
        $bus = $this->createBus([DummyMessage::class, ThirdMessage::class], [DummyMessage::class => ['sync']], null, $failureTransport);

        $envelope = $bus->dispatch($first = new DummyMessage('first'), [new DispatchOnFailureStamp($failure = new ThirdMessage())]);

        $this->assertSame('sync', $envelope->last(SentToFailureTransportStamp::class)?->getOriginalReceiverName());
        $this->assertCount(1, $failureTransport->getSent());
        $this->assertSame($first, $failureTransport->getSent()[0]->getMessage());
        $this->assertSame([$first, $failure], $this->attempted);
        $this->assertCount(1, $this->failureEnvelopes);
        $this->assertSame([ThirdMessage::class], array_map(static fn (array $context): string => $context['failure_class'], $this->errors));
    }

    public function testFailureMessageTakesThePlaceOfTheFailureTransportWhenAStepOfAChainFailsInASynchronousTransport()
    {
        $failureTransport = new InMemoryTransport();
        $bus = $this->createBus([SecondMessage::class], [DummyMessage::class => ['sync']], null, $failureTransport);

        $envelope = $bus->dispatch($first = new DummyMessage('first'), [new ChainStamp($second = new SecondMessage()), new DispatchOnFailureStamp($failure = new ThirdMessage())]);

        $this->assertNull($envelope->last(ErrorDetailsStamp::class));
        $this->assertSame([$first, $second, $failure], $this->attempted);
        $this->assertCount(1, $this->failureEnvelopes);
        $this->assertSame($second, $this->failureEnvelopes[0]->last(FailedMessageStamp::class)->getMessage());
        $this->assertSame([], $failureTransport->getSent());
    }

    public function testFailureMessageIsDispatchedOnceWhenASynchronousTransportGivesUp()
    {
        $bus = $this->createBus([DummyMessage::class], [DummyMessage::class => ['sync']], new MultiplierRetryStrategy(2, 0));

        try {
            $bus->dispatch($first = new DummyMessage('first'), [new DispatchOnFailureStamp($failure = new ThirdMessage())]);
            $this->fail('The failure of the message should have been reported.');
        } catch (HandlerFailedException) {
        }

        $this->assertSame([$first, $first, $first, $failure], $this->attempted);
        $this->assertCount(1, $this->failureEnvelopes);
        $this->assertSame($first, $this->failureEnvelopes[0]->last(FailedMessageStamp::class)->getMessage());
    }

    public function testFailureMessageIsDispatchedAndTheMessageDroppedWithoutAFailureTransportInAWorker()
    {
        $bus = $this->createBus([DummyMessage::class], [DummyMessage::class => ['async']]);

        $bus->dispatch($first = new DummyMessage('first'), [new DispatchOnFailureStamp($failure = new ThirdMessage())]);
        $this->runWorker($bus);

        $this->assertSame([$first, $failure], $this->attempted);
        $this->assertCount(1, $this->failureEnvelopes);
        $this->assertCount(1, $this->transport->getRejected());
        $this->assertSame([], $this->transport->get());
    }

    public function testFailureMessageTakesThePlaceOfTheFailureTransportInAWorker()
    {
        $this->failureTransport = new InMemoryTransport();
        $bus = $this->createBus([DummyMessage::class], [DummyMessage::class => ['async']]);

        $bus->dispatch($first = new DummyMessage('first'), [new DispatchOnFailureStamp($failure = new ThirdMessage())]);
        $this->runWorker($bus);

        $this->assertSame([$first, $failure], $this->attempted);
        $this->assertCount(1, $this->failureEnvelopes);
        $this->assertSame($first, $this->failureEnvelopes[0]->last(FailedMessageStamp::class)->getMessage());
        $this->assertCount(1, $this->transport->getRejected());
        $this->assertSame([], $this->failureTransport->getSent());
    }

    public function testAFailureMessageChosenByAnUnverifiedMessageCannotReachAHandlerThatRequiresASignature()
    {
        $this->transport = new InMemoryTransport(new PhpSerializer());
        $this->failureTransport = new InMemoryTransport();
        $bus = $this->createBus([DummyMessage::class, SecondMessage::class], [SecondMessage::class => ['async']]);

        try {
            $bus->dispatch(new DummyMessage('in-process'), [new DispatchOnFailureStamp($command = new DummyCommand())]);
            $this->fail('The failure of the message should have been reported.');
        } catch (HandlerFailedException) {
        }

        $this->assertSame([$command], $this->handled, 'a failure message dispatched in this process is trusted');
        $this->handled = $this->attempted = [];

        $bus->dispatch(new SecondMessage(), [new DispatchOnFailureStamp(new DummyCommand())]);
        $this->runWorker($bus);

        $this->assertEquals([new SecondMessage()], $this->attempted);
        $this->assertSame([DummyCommand::class], array_map(static fn (array $context): string => $context['failure_class'], $this->errors));
        $this->assertCount(1, $this->failureTransport->getSent());
    }

    public function testTheMessageIsSentToTheFailureTransportWhenItsFailureMessageCannotBeDispatchedInAWorker()
    {
        $this->failureTransport = new InMemoryTransport();
        $bus = $this->createBus([DummyMessage::class, ThirdMessage::class], [DummyMessage::class => ['async']]);

        $bus->dispatch($first = new DummyMessage('first'), [new DispatchOnFailureStamp($failure = new ThirdMessage())]);
        $this->runWorker($bus);

        $this->assertSame([$first, $failure], $this->attempted);
        $this->assertSame([ThirdMessage::class], array_map(static fn (array $context): string => $context['failure_class'], $this->errors));
        $this->assertCount(1, $this->failureTransport->getSent());
        $this->assertSame($first, $this->failureTransport->getSent()[0]->getMessage());
        $this->assertSame('async', $this->failureTransport->getSent()[0]->last(SentToFailureTransportStamp::class)?->getOriginalReceiverName());
    }

    public function testAFailureMessageSentToAFailureTransportByItsSynchronousTransportCountsAsDispatched()
    {
        $this->failureTransport = new InMemoryTransport();
        $bus = $this->createBus([DummyMessage::class, ThirdMessage::class], [DummyMessage::class => ['async'], ThirdMessage::class => ['sync']], null, $this->failureTransport);

        $bus->dispatch($first = new DummyMessage('first'), [new DispatchOnFailureStamp($failure = new ThirdMessage())]);
        $this->runWorker($bus);

        $this->assertSame([$first, $failure], $this->attempted);
        $this->assertSame([], $this->errors);
        $this->assertCount(1, $this->failureTransport->getSent());
        $this->assertSame($failure, $this->failureTransport->getSent()[0]->getMessage());
    }

    public function testTheFailureMessageIsDispatchedOnceWhenTheFailureTransportIsConsumedAfterwards()
    {
        $this->failureTransport = new InMemoryTransport();
        $bus = $this->createBus([DummyMessage::class], [DummyMessage::class => ['async']]);

        $bus->dispatch(new DummyMessage('first'), [new DispatchOnFailureStamp($failure = new ThirdMessage())]);
        $this->runWorker($bus);
        $this->runWorker($bus, 1, 'failed', $this->failureTransport);

        $this->assertSame([$failure], $this->handled);
        $this->assertCount(1, $this->failureEnvelopes);
    }

    public function testTheFailureMessageIsDispatchedWhenAMessageRetriedFromTheFailureTransportFailsAgain()
    {
        $this->failureTransport = new InMemoryTransport();
        $bus = $this->createBus([DummyMessage::class, ThirdMessage::class], [DummyMessage::class => ['async']]);

        $bus->dispatch($first = new DummyMessage('first'), [new DispatchOnFailureStamp($failure = new ThirdMessage())]);
        $this->runWorker($bus);

        $this->assertCount(1, $this->failureTransport->getSent());
        $this->assertSame([], $this->handled);

        $this->failing = [DummyMessage::class];
        $this->runWorker($bus, 1, 'failed', $this->failureTransport);

        $this->assertSame([$first, $failure, $first, $failure], $this->attempted);
        $this->assertSame([$failure], $this->handled);
        $this->assertSame($first, $this->failureEnvelopes[1]->last(FailedMessageStamp::class)->getMessage());
        $this->assertCount(1, $this->failureTransport->getSent());
        $this->assertCount(1, $this->failureTransport->getRejected());
        $this->assertSame([], $this->failureTransport->get());
    }

    public function testFailureMessageTakesThePlaceOfTheFailureTransportWhenAStepOfAChainFailsInAWorker()
    {
        $this->failureTransport = new InMemoryTransport();
        $bus = $this->createBus([SecondMessage::class], [SecondMessage::class => ['async']]);

        $bus->dispatch($first = new DummyMessage('first'), [new ChainStamp($second = new SecondMessage(), new ThirdMessage()), new DispatchOnFailureStamp($failure = new DummyMessage('failure'))]);
        $this->runWorker($bus);
        $this->runWorker($bus, 1, 'failed', $this->failureTransport);

        $this->assertSame([$first, $second, $failure], $this->attempted);
        $this->assertCount(1, $this->failureEnvelopes);
        $this->assertSame($second, $this->failureEnvelopes[0]->last(FailedMessageStamp::class)->getMessage());
        $this->assertSame([], $this->failureTransport->getSent());
    }

    public function testNothingIsDispatchedWhenTheChainSucceeds()
    {
        $bus = $this->createBus([], [SecondMessage::class => ['async']]);

        $bus->dispatch($first = new DummyMessage('first'), [new ChainStamp($second = new SecondMessage(), $third = new ThirdMessage()), new DispatchOnFailureStamp(new DummyMessage('failure'))]);
        $this->runWorker($bus, 2);

        $this->assertSame([$first, $second, $third], $this->handled);
        $this->assertSame([], $this->failureEnvelopes);
    }

    public function testAFailingFailureMessageDoesNotLoopAndDoesNotHideTheOriginalFailure()
    {
        $bus = $this->createBus([DummyMessage::class, SecondMessage::class]);

        try {
            $bus->dispatch($first = new DummyMessage('first'), [new DispatchOnFailureStamp($failure = new SecondMessage())]);
            $this->fail('The failure of the first message should have been reported.');
        } catch (HandlerFailedException $e) {
            $this->assertSame($first, $e->getEnvelope()->getMessage());
            $this->assertSame(\sprintf('Handling "%s" failed.', DummyMessage::class), $e->getPrevious()->getMessage());
        }

        $this->assertSame([$first, $failure], $this->attempted);
        $this->assertSame([], $this->handled);
        $this->assertCount(1, $this->failureEnvelopes);
    }

    /**
     * @param class-string[]                    $failing The messages whose handler throws
     * @param array<class-string, list<string>> $routing The messages sent to the "async" or "sync" transport
     */
    private function createBus(array $failing = [], array $routing = [], ?RetryStrategyInterface $syncRetryStrategy = null, ?SenderInterface $syncFailureSender = null): MessageBus
    {
        $this->failing = $failing;
        $handler = function (object $message) use (&$bus): void {
            $this->attempted[] = $message;

            if (\in_array($message::class, $this->failing, true)) {
                throw new \RuntimeException(\sprintf('Handling "%s" failed.', $message::class));
            }

            $this->handled[] = $message;

            if (isset($this->delayedMessages[$message])) {
                $bus->dispatch($this->delayedMessages[$message]->with(new DispatchAfterCurrentBusStamp()));
            }
        };
        $handlersLocator = new HandlersLocator([
            DummyMessage::class => [$handler],
            SecondMessage::class => [$handler],
            ThirdMessage::class => [$handler],
            DummyCommand::class => [new HandlerDescriptor($handler, ['sign' => true])],
        ]);

        $senders = $this->senders;
        $senders->set('async', $this->transport);

        $recorder = new class($this->failureEnvelopes) implements MiddlewareInterface {
            public function __construct(private array &$failureEnvelopes)
            {
            }

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                if (null !== $envelope->last(FailedMessageStamp::class)) {
                    $this->failureEnvelopes[] = $envelope;
                }

                return $stack->next()->handle($envelope, $stack);
            }
        };

        $buses = new Container();
        $routableBus = new RoutableMessageBus($buses);
        $bus = new MessageBus([
            new AddBusNameStampMiddleware('the_bus'),
            new FlowContextMiddleware(true),
            $recorder,
            new DispatchOnFailureMiddleware($routableBus, $this->createLogger()),
            new DispatchAfterCurrentBusMiddleware(),
            new FailedMessageProcessingMiddleware(),
            new SendMessageMiddleware($sendersLocator = new SendersLocator($routing, $senders)),
            new ChainMiddleware($routableBus, $sendersLocator),
            new HandleMessageMiddleware($handlersLocator),
        ]);
        $buses->set('the_bus', $bus);
        $senders->set('sync', new SyncTransport($bus, $syncRetryStrategy, $syncFailureSender, null, $this->createLogger()));

        return $bus;
    }

    /**
     * @param string $receiverName A name that is not a sender makes the worker handle the next steps of a chain in its own process, as for a schedule
     */
    private function runWorker(MessageBus $bus, int $messageLimit = 1, string $receiverName = 'async', ?InMemoryTransport $receiver = null): void
    {
        $receiver ??= $this->transport;
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener($messageLimit));
        $dispatcher->addSubscriber(new StopWorkerOnIdleListener());
        $dispatcher->addSubscriber(new DispatchOnFailureListener($bus, $this->createLogger()));

        if (null !== $retryStrategy = $this->retryStrategy) {
            $dispatcher->addSubscriber(new SendFailedMessageForRetryListener(new ServiceLocator([$receiverName => static fn () => $receiver]), new ServiceLocator([$receiverName => static fn () => $retryStrategy])));
        }

        if (null !== $failureTransport = $this->failureTransport) {
            $failureTransportsByName = ['async' => 'failed', 'schedule' => 'failed', 'failed' => 'failed'];
            $dispatcher->addSubscriber(new SendFailedMessageToFailureTransportListener(new ServiceLocator(array_map(static fn () => static fn () => $failureTransport, $failureTransportsByName)), null, $failureTransportsByName));
        }

        (new Worker([$receiverName => $receiver], $bus, $dispatcher))->run();
    }

    private function createLogger(): AbstractLogger
    {
        return new class($this->errors) extends AbstractLogger {
            public function __construct(private array &$errors)
            {
            }

            public function log($level, $message, array $context = []): void
            {
                if ('error' === $level) {
                    $this->errors[] = $context;
                }
            }
        };
    }
}

class DispatchOnFailureTenantStamp implements PropagatedStampInterface
{
    public function __construct(
        public readonly string $tenant,
    ) {
    }
}
