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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\EventListener\AddErrorDetailsStampListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageForRetryListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageToFailureTransportListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\Exception\DelayedMessageHandlingException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\InvalidMessageSignatureException;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Exception\ValidationFailedException;
use Symfony\Component\Messenger\Failure\FailedMessageRepository;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\AddBusNameStampMiddleware;
use Symfony\Component\Messenger\Middleware\DecodeFailedMessageMiddleware;
use Symfony\Component\Messenger\Middleware\DispatchAfterCurrentBusMiddleware;
use Symfony\Component\Messenger\Middleware\FailedMessageProcessingMiddleware;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Middleware\ValidationMiddleware;
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;
use Symfony\Component\Messenger\RoutableMessageBus;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TrustStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Transport\Serialization\ClaimCheckSerializer;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\Serialization\SigningSerializer;
use Symfony\Component\Messenger\Worker;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class FailureIntegrationTest extends TestCase
{
    public function testRequeueMechanism()
    {
        $transport1 = new DummyFailureTestSenderAndReceiver();
        $transport2 = new DummyFailureTestSenderAndReceiver();
        $failureTransport = new DummyFailureTestSenderAndReceiver();
        $sendersLocatorFailureTransport = new ServiceLocator([
            'transport1' => static fn () => $failureTransport,
            'transport2' => static fn () => $failureTransport,
        ]);

        $transports = [
            'transport1' => $transport1,
            'transport2' => $transport2,
            'the_failure_transport' => $failureTransport,
        ];

        $locator = new Container();

        foreach ($transports as $transportName => $transport) {
            $locator->set($transportName, $transport);
        }

        $senderLocator = new SendersLocator(
            [DummyMessage::class => ['transport1', 'transport2']],
            $locator
        );

        $retryStrategyLocator = new Container();
        $retryStrategyLocator->set('the_failure_transport', new MultiplierRetryStrategy(1));
        $retryStrategyLocator->set('transport1', new MultiplierRetryStrategy(1));

        // using to so we can lazily get the bus later and avoid circular problem
        $transport1HandlerThatFails = new DummyTestHandler(true);
        $allTransportHandlerThatWorks = new DummyTestHandler(false);
        $transport2HandlerThatWorks = new DummyTestHandler(false);
        $handlerLocator = new HandlersLocator([
            DummyMessage::class => [
                new HandlerDescriptor($transport1HandlerThatFails, [
                    'from_transport' => 'transport1',
                    'alias' => 'handler_that_fails',
                ]),
                new HandlerDescriptor($allTransportHandlerThatWorks, [
                    'alias' => 'handler_that_works1',
                ]),
                new HandlerDescriptor($transport2HandlerThatWorks, [
                    'from_transport' => 'transport2',
                    'alias' => 'handler_that_works2',
                ]),
            ],
        ]);

        $dispatcher = new EventDispatcher();
        $bus = new MessageBus([
            new FailedMessageProcessingMiddleware(),
            new SendMessageMiddleware($senderLocator),
            new HandleMessageMiddleware($handlerLocator),
        ]);
        $dispatcher->addSubscriber(new AddErrorDetailsStampListener());
        $dispatcher->addSubscriber(new SendFailedMessageForRetryListener($locator, $retryStrategyLocator));

        $dispatcher->addSubscriber(new SendFailedMessageToFailureTransportListener($sendersLocatorFailureTransport));
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));

        $runWorker = static function (string $transportName) use ($transports, $bus, $dispatcher): ?\Throwable {
            $throwable = null;
            $failedListener = static function (WorkerMessageFailedEvent $event) use (&$throwable) {
                $throwable = $event->getThrowable();
            };
            $dispatcher->addListener(WorkerMessageFailedEvent::class, $failedListener);

            $worker = new Worker([$transportName => $transports[$transportName]], $bus, $dispatcher);

            $worker->run();

            $dispatcher->removeListener(WorkerMessageFailedEvent::class, $failedListener);

            return $throwable;
        };

        // send the message
        $envelope = new Envelope(new DummyMessage('API'));
        $bus->dispatch($envelope);

        // message has been sent
        $this->assertCount(1, $transport1->getMessagesWaitingToBeReceived());
        $this->assertCount(1, $transport2->getMessagesWaitingToBeReceived());
        $this->assertCount(0, $failureTransport->getMessagesWaitingToBeReceived());

        // receive the message - one handler will fail and the message
        // will be sent back to transport1 to be retried
        /*
         * Receive the message from "transport1"
         */
        $throwable = $runWorker('transport1');
        // make sure this is failing for the reason we think
        $this->assertInstanceOf(HandlerFailedException::class, $throwable);
        // handler for transport1 and all transports were called
        $this->assertSame(1, $transport1HandlerThatFails->getTimesCalled());
        $this->assertSame(1, $allTransportHandlerThatWorks->getTimesCalled());
        $this->assertSame(0, $transport2HandlerThatWorks->getTimesCalled());
        // one handler failed and the message is retried (resent to transport1)
        $this->assertCount(1, $transport1->getMessagesWaitingToBeReceived());
        $this->assertSame([], $failureTransport->getMessagesWaitingToBeReceived());

        /*
         * Receive the message for a (final) retry
         */
        $runWorker('transport1');
        // only the "failed" handler is called a 2nd time
        $this->assertSame(2, $transport1HandlerThatFails->getTimesCalled());
        $this->assertSame(1, $allTransportHandlerThatWorks->getTimesCalled());
        // handling fails again, message is sent to failure transport
        $this->assertCount(0, $transport1->getMessagesWaitingToBeReceived());
        $this->assertCount(1, $failureTransport->getMessagesWaitingToBeReceived());
        /** @var Envelope $failedEnvelope */
        $failedEnvelope = $failureTransport->getMessagesWaitingToBeReceived()[0];
        /** @var SentToFailureTransportStamp $sentToFailureStamp */
        $sentToFailureStamp = $failedEnvelope->last(SentToFailureTransportStamp::class);
        $this->assertNotNull($sentToFailureStamp);
        /** @var ErrorDetailsStamp $errorDetailsStamp */
        $errorDetailsStamp = $failedEnvelope->last(ErrorDetailsStamp::class);
        $this->assertNotNull($errorDetailsStamp);
        $this->assertSame('Failure from call 1', $errorDetailsStamp->getExceptionMessage());

        /*
         * Failed message is handled, fails, and sent for a retry
         */
        $throwable = $runWorker('the_failure_transport');
        // make sure this is failing for the reason we think
        $this->assertInstanceOf(HandlerFailedException::class, $throwable);
        // only the "failed" handler is called a 3rd time
        $this->assertSame(3, $transport1HandlerThatFails->getTimesCalled());
        $this->assertSame(1, $allTransportHandlerThatWorks->getTimesCalled());
        // handling fails again, message is retried
        $this->assertCount(1, $failureTransport->getMessagesWaitingToBeReceived());
        // transport2 still only holds the original message
        // a new message was never mistakenly delivered to it
        $this->assertCount(1, $transport2->getMessagesWaitingToBeReceived());

        /*
         * Message is retried on failure transport then discarded
         */
        $runWorker('the_failure_transport');
        // only the "failed" handler is called a 4th time
        $this->assertSame(4, $transport1HandlerThatFails->getTimesCalled());
        $this->assertSame(1, $allTransportHandlerThatWorks->getTimesCalled());
        // handling fails again, message is discarded
        $this->assertCount(0, $failureTransport->getMessagesWaitingToBeReceived());

        /*
         * Execute handlers on transport2
         */
        $runWorker('transport2');
        // transport1 handler is not called again
        $this->assertSame(4, $transport1HandlerThatFails->getTimesCalled());
        // all transport handler is now called again
        $this->assertSame(2, $allTransportHandlerThatWorks->getTimesCalled());
        // transport1 handler called for the first time
        $this->assertSame(1, $transport2HandlerThatWorks->getTimesCalled());
        // all transport should be empty
        $this->assertSame([], $transport1->getMessagesWaitingToBeReceived());
        $this->assertSame([], $transport2->getMessagesWaitingToBeReceived());
        $this->assertSame([], $failureTransport->getMessagesWaitingToBeReceived());

        /*
         * Dispatch the original message again
         */
        $bus->dispatch($envelope);
        // handle the failing message so it goes into the failure transport
        $runWorker('transport1');
        $runWorker('transport1');
        // now make the handler work!
        $transport1HandlerThatFails->setShouldThrow(false);
        $runWorker('the_failure_transport');
        // the failure transport is empty because it worked
        $this->assertSame([], $failureTransport->getMessagesWaitingToBeReceived());
    }

    public function testMultipleFailedTransportsWithoutGlobalFailureTransport()
    {
        $transport1 = new DummyFailureTestSenderAndReceiver();
        $transport2 = new DummyFailureTestSenderAndReceiver();
        $failureTransport1 = new DummyFailureTestSenderAndReceiver();
        $failureTransport2 = new DummyFailureTestSenderAndReceiver();

        $sendersLocatorFailureTransport = new ServiceLocator([
            'transport1' => static fn () => $failureTransport1,
            'transport2' => static fn () => $failureTransport2,
        ]);

        $transports = [
            'transport1' => $transport1,
            'transport2' => $transport2,
            'the_failure_transport1' => $failureTransport1,
            'the_failure_transport2' => $failureTransport2,
        ];

        $locator = new Container();

        foreach ($transports as $transportName => $transport) {
            $locator->set($transportName, $transport);
        }

        $senderLocator = new SendersLocator(
            [DummyMessage::class => ['transport1', 'transport2']],
            $locator
        );

        // using to so we can lazily get the bus later and avoid circular problem
        $transport1HandlerThatFails = new DummyTestHandler(true);
        $transport2HandlerThatFails = new DummyTestHandler(true);
        $handlerLocator = new HandlersLocator([
            DummyMessage::class => [
                new HandlerDescriptor($transport1HandlerThatFails, [
                    'from_transport' => 'transport1',
                ]),
                new HandlerDescriptor($transport2HandlerThatFails, [
                    'from_transport' => 'transport2',
                ]),
            ],
        ]);

        $dispatcher = new EventDispatcher();
        $bus = new MessageBus([
            new FailedMessageProcessingMiddleware(),
            new SendMessageMiddleware($senderLocator),
            new HandleMessageMiddleware($handlerLocator),
        ]);

        $dispatcher->addSubscriber(new SendFailedMessageForRetryListener($locator, new Container()));
        $dispatcher->addSubscriber(new SendFailedMessageToFailureTransportListener(
            $sendersLocatorFailureTransport,
            new NullLogger()
        ));
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));

        $runWorker = static function (string $transportName) use ($transports, $bus, $dispatcher): ?\Throwable {
            $throwable = null;
            $failedListener = static function (WorkerMessageFailedEvent $event) use (&$throwable) {
                $throwable = $event->getThrowable();
            };
            $dispatcher->addListener(WorkerMessageFailedEvent::class, $failedListener);

            $worker = new Worker([$transportName => $transports[$transportName]], $bus, $dispatcher);

            $worker->run();

            $dispatcher->removeListener(WorkerMessageFailedEvent::class, $failedListener);

            return $throwable;
        };

        // send the message
        $envelope = new Envelope(new DummyMessage('API'));
        $bus->dispatch($envelope);

        // message has been sent
        $this->assertCount(1, $transport1->getMessagesWaitingToBeReceived());
        $this->assertCount(1, $transport2->getMessagesWaitingToBeReceived());
        $this->assertCount(0, $failureTransport1->getMessagesWaitingToBeReceived());
        $this->assertCount(0, $failureTransport2->getMessagesWaitingToBeReceived());

        // Receive the message from "transport1"
        $throwable = $runWorker('transport1');
        $this->assertInstanceOf(HandlerFailedException::class, $throwable);
        // handler for transport1 is called
        $this->assertSame(1, $transport1HandlerThatFails->getTimesCalled());
        $this->assertSame(0, $transport2HandlerThatFails->getTimesCalled());
        // one handler failed and the message is sent to the failed transport of transport1
        $this->assertCount(1, $failureTransport1->getMessagesWaitingToBeReceived());
        $this->assertCount(0, $failureTransport2->getMessagesWaitingToBeReceived());

        // consume the failure message failed on "transport1"
        $runWorker('the_failure_transport1');
        // "transport1" handler is called again from the "the_failed_transport1" and it fails
        $this->assertSame(2, $transport1HandlerThatFails->getTimesCalled());
        $this->assertSame(0, $transport2HandlerThatFails->getTimesCalled());
        $this->assertCount(0, $failureTransport1->getMessagesWaitingToBeReceived());
        $this->assertCount(0, $failureTransport2->getMessagesWaitingToBeReceived());

        // Receive the message from "transport2"
        $throwable = $runWorker('transport2');
        $this->assertInstanceOf(HandlerFailedException::class, $throwable);
        $this->assertSame(2, $transport1HandlerThatFails->getTimesCalled());
        // handler for "transport2" is called
        $this->assertSame(1, $transport2HandlerThatFails->getTimesCalled());
        $this->assertCount(0, $failureTransport1->getMessagesWaitingToBeReceived());
        // the failure transport "the_failure_transport2" has 1 new message failed from "transport2"
        $this->assertCount(1, $failureTransport2->getMessagesWaitingToBeReceived());

        // Consume the failure message failed on "transport2"
        $runWorker('the_failure_transport2');
        $this->assertSame(2, $transport1HandlerThatFails->getTimesCalled());
        // "transport2" handler is called again from the "the_failed_transport2" and it fails
        $this->assertSame(2, $transport2HandlerThatFails->getTimesCalled());
        $this->assertCount(0, $failureTransport1->getMessagesWaitingToBeReceived());
        // After the message fails again, the message is discarded from the "the_failure_transport2"
        $this->assertCount(0, $failureTransport2->getMessagesWaitingToBeReceived());
    }

    public function testStampsAddedByMiddlewaresDontDisappearWhenDelayedMessageFails()
    {
        $transport1 = new DummyFailureTestSenderAndReceiver();

        $transports = [
            'transport1' => $transport1,
        ];

        $locator = new Container();

        foreach ($transports as $transportName => $transport) {
            $locator->set($transportName, $transport);
        }

        $senderLocator = new SendersLocator([], $locator);

        $retryStrategyLocator = new Container();
        $retryStrategyLocator->set('transport1', new MultiplierRetryStrategy(1));

        $syncHandlerThatFails = new DummyTestHandler(true);

        $middlewareStack = new \ArrayIterator([
            new AddBusNameStampMiddleware('some.bus'),
            new DispatchAfterCurrentBusMiddleware(),
            new SendMessageMiddleware($senderLocator),
        ]);

        $bus = new MessageBus($middlewareStack);

        $transport1Handler = static fn () => $bus->dispatch(new \stdClass(), [new DispatchAfterCurrentBusStamp()]);

        $handlerLocator = new HandlersLocator([
            DummyMessage::class => [new HandlerDescriptor($transport1Handler)],
            \stdClass::class => [new HandlerDescriptor($syncHandlerThatFails)],
        ]);

        $middlewareStack->append(new HandleMessageMiddleware($handlerLocator));

        $dispatcher = new EventDispatcher();

        $dispatcher->addSubscriber(new SendFailedMessageForRetryListener($locator, $retryStrategyLocator));
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));

        $runWorker = static function (string $transportName) use ($transports, $bus, $dispatcher): ?\Throwable {
            $throwable = null;
            $failedListener = static function (WorkerMessageFailedEvent $event) use (&$throwable) {
                $throwable = $event->getThrowable();
            };
            $dispatcher->addListener(WorkerMessageFailedEvent::class, $failedListener);

            $worker = new Worker([$transportName => $transports[$transportName]], $bus, $dispatcher);

            $worker->run();

            $dispatcher->removeListener(WorkerMessageFailedEvent::class, $failedListener);

            return $throwable;
        };

        // Simulate receive from external source
        $transport1->send(new Envelope(new DummyMessage('API')));

        // Receive the message from "transport1"
        $throwable = $runWorker('transport1');

        $this->assertInstanceOf(DelayedMessageHandlingException::class, $throwable, $throwable->getMessage());
        $this->assertSame(1, $syncHandlerThatFails->getTimesCalled());

        $messagesWaiting = $transport1->getMessagesWaitingToBeReceived();

        // Stamps should not be dropped on message that's queued for retry
        $this->assertCount(1, $messagesWaiting);
        $this->assertSame('some.bus', $messagesWaiting[0]->last(BusNameStamp::class)?->getBusName());
    }

    public function testStampsAddedByMiddlewaresDontDisappearWhenValidationFails()
    {
        $transport1 = new DummyFailureTestSenderAndReceiver();

        $transports = [
            'transport1' => $transport1,
        ];

        $locator = new Container();
        $locator->set('transport1', $transport1);

        $senderLocator = new SendersLocator([], $locator);

        $retryStrategyLocator = new Container();
        $retryStrategyLocator->set('transport1', new MultiplierRetryStrategy(1));

        $violationList = new ConstraintViolationList([new ConstraintViolation('validation failed', null, [], null, null, null)]);
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->once())->method('validate')->willReturn($violationList);

        $middlewareStack = new \ArrayIterator([
            new AddBusNameStampMiddleware('some.bus'),
            new ValidationMiddleware($validator),
            new SendMessageMiddleware($senderLocator),
        ]);

        $bus = new MessageBus($middlewareStack);

        $transport1Handler = static fn () => $bus->dispatch(new \stdClass(), [new DispatchAfterCurrentBusStamp()]);

        $handlerLocator = new HandlersLocator([
            DummyMessage::class => [new HandlerDescriptor($transport1Handler)],
        ]);

        $middlewareStack->append(new HandleMessageMiddleware($handlerLocator));

        $dispatcher = new EventDispatcher();

        $dispatcher->addSubscriber(new SendFailedMessageForRetryListener($locator, $retryStrategyLocator));
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));

        $runWorker = static function (string $transportName) use ($transports, $bus, $dispatcher): ?\Throwable {
            $throwable = null;
            $failedListener = static function (WorkerMessageFailedEvent $event) use (&$throwable) {
                $throwable = $event->getThrowable();
            };
            $dispatcher->addListener(WorkerMessageFailedEvent::class, $failedListener);

            $worker = new Worker([$transportName => $transports[$transportName]], $bus, $dispatcher);

            $worker->run();

            $dispatcher->removeListener(WorkerMessageFailedEvent::class, $failedListener);

            return $throwable;
        };

        // Simulate receive from external source
        $transport1->send(new Envelope(new DummyMessage('API')));

        // Receive the message from "transport1"
        $throwable = $runWorker('transport1');

        $this->assertInstanceOf(ValidationFailedException::class, $throwable, $throwable->getMessage());

        $messagesWaiting = $transport1->getMessagesWaitingToBeReceived();

        // Stamps should not be dropped on message that's queued for retry
        $this->assertCount(1, $messagesWaiting);
        $this->assertSame('some.bus', $messagesWaiting[0]->last(BusNameStamp::class)?->getBusName());
    }

    #[DataProvider('provideMessagesRejectedForTheirSignature')]
    public function testMessageRejectedForItsSignatureIsSentToTheFailureTransportWithoutRetry(SerializerInterface $inner, array $encodedEnvelope)
    {
        $serializer = new SigningSerializer($inner, 'signing-key', [DummyMessage::class]);
        $transport = new SerializingFailureTestSenderAndReceiver($serializer, [$encodedEnvelope]);
        $failureTransport = new DummyFailureTestSenderAndReceiver();

        $locator = new Container();
        $locator->set('transport', $transport);

        $retryStrategyLocator = new Container();
        $retryStrategyLocator->set('transport', new MultiplierRetryStrategy(2));

        $handler = new DummyTestHandler(false);
        $bus = new MessageBus([
            new DecodeFailedMessageMiddleware(new ServiceLocator(['transport' => static fn () => $serializer])),
            new FailedMessageProcessingMiddleware(),
            new HandleMessageMiddleware(new HandlersLocator([DummyMessage::class => [new HandlerDescriptor($handler)]])),
        ]);

        $throwables = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new SendFailedMessageForRetryListener($locator, $retryStrategyLocator));
        $dispatcher->addSubscriber(new SendFailedMessageToFailureTransportListener(new ServiceLocator(['transport' => static fn () => $failureTransport])));
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));
        $dispatcher->addListener(WorkerMessageFailedEvent::class, static function (WorkerMessageFailedEvent $event) use (&$throwables) {
            $throwables[] = $event->getThrowable();
        });

        for ($i = 0; $i < 10 && $transport->getMessagesWaitingToBeReceived(); ++$i) {
            (new Worker(['transport' => $transport], $bus, $dispatcher))->run();
        }

        $this->assertCount(1, $throwables);
        $this->assertInstanceOf(InvalidMessageSignatureException::class, $throwables[0]);
        $this->assertSame(0, $handler->getTimesCalled());
        $this->assertSame([], $transport->getMessagesWaitingToBeReceived());
        $this->assertCount(1, $failedEnvelopes = $failureTransport->getMessagesWaitingToBeReceived());
        $this->assertInstanceOf(MessageDecodingFailedException::class, $failedEnvelopes[0]->getMessage());
        $this->assertSame($encodedEnvelope, $failedEnvelopes[0]->getMessage()->encodedEnvelope);
    }

    public static function provideMessagesRejectedForTheirSignature(): iterable
    {
        $envelope = new Envelope(new DummyMessage('API'));

        foreach (['JSON' => new Serializer(), 'PHP' => new PhpSerializer()] as $format => $inner) {
            yield $format.' without signature' => [$inner, $inner->encode($envelope)];
            yield $format.' signed with another key' => [$inner, (new SigningSerializer($inner, 'another-key', [DummyMessage::class]))->encode($envelope)];
        }
    }

    #[DataProvider('provideUnverifiedFailureSerializers')]
    public function testUnverifiedFailureDoesNotPutItsStampsOnTheSignedMessageOfItsClaim(SerializerInterface $inner, bool $failureTransportHasClaimCheck)
    {
        $signingSerializer = new SigningSerializer($inner, 'signing-key', [DummyMessage::class]);
        $claimedData = serialize($signingSerializer->encode(new Envelope(new DummyMessage('API'), [new BusNameStamp('the_bus')])));
        $item = $this->createStub(CacheItemInterface::class);
        $item->method('isHit')->willReturn(true);
        $item->method('get')->willReturn($claimedData);
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $pool->method('getItem')->willReturn($item);
        $serializer = new ClaimCheckSerializer($signingSerializer, $pool, 1000);
        $claim = [
            'body' => json_encode(['id' => 'claim', 'digest' => hash('sha256', $claimedData)]),
            'headers' => ['X-Symfony-Messenger-Claim-Check' => '1', 'X-Symfony-Messenger-Claim-Check-Type' => DummyMessage::class],
        ];
        $forgedStamps = [new SentToFailureTransportStamp('transport'), new HandledStamp(null, DummyTestHandler::class.'::__invoke')];
        // a claim that could not be retrieved is sent again unsigned: this forged failure looks the same
        $forgedFailure = $inner->encode(new Envelope(new MessageDecodingFailedException('Forged.', 0, null, $claim), $forgedStamps));
        $failureTransport = new SerializingFailureTestSenderAndReceiver($failureTransportHasClaimCheck ? $serializer : $signingSerializer, [$forgedFailure]);

        $handler = new DummyTestHandler(false);
        $bus = new MessageBus([
            new DecodeFailedMessageMiddleware(new ServiceLocator(['transport' => static fn () => $serializer])),
            new FailedMessageProcessingMiddleware(),
            new HandleMessageMiddleware(new HandlersLocator([DummyMessage::class => [new HandlerDescriptor($handler)]])),
        ]);

        $handledEnvelope = null;
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));
        $dispatcher->addListener(WorkerMessageHandledEvent::class, static function (WorkerMessageHandledEvent $event) use (&$handledEnvelope) {
            $handledEnvelope = $event->getEnvelope();
        });

        (new Worker(['failed' => $failureTransport], $bus, $dispatcher))->run();

        $this->assertSame(1, $handler->getTimesCalled());
        $this->assertSame('the_bus', $handledEnvelope?->last(BusNameStamp::class)?->getBusName());
    }

    public static function provideUnverifiedFailureSerializers(): iterable
    {
        yield 'JSON' => [new Serializer(), false];
        yield 'PHP' => [new PhpSerializer(), false];
        yield 'PHP with claim check' => [new PhpSerializer(), true];
        yield 'JSON with claim check' => [new Serializer(), true];
    }

    #[DataProvider('provideUnverifiedFailuresOfSignedClaims')]
    public function testUnverifiedFailureIsHandledOnlyOnTheBusOfTheSignedMessageOfItsClaim(SerializerInterface $inner, ?string $failureBusName, ?string $workerBusName, array $expectedCalls)
    {
        $signingSerializer = new SigningSerializer($inner, 'signing-key', [DummyMessage::class]);
        $claimedData = serialize($signingSerializer->encode(new Envelope(new DummyMessage('API'), [new BusNameStamp('command.bus')])));
        $item = $this->createStub(CacheItemInterface::class);
        $item->method('isHit')->willReturn(true);
        $item->method('get')->willReturn($claimedData);
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $pool->method('getItem')->willReturn($item);
        $serializer = new ClaimCheckSerializer($signingSerializer, $pool, 1000);
        $claim = [
            'body' => json_encode(['id' => 'claim', 'digest' => hash('sha256', $claimedData)]),
            'headers' => ['X-Symfony-Messenger-Claim-Check' => '1', 'X-Symfony-Messenger-Claim-Check-Type' => DummyMessage::class, 'X-Symfony-Messenger-Claim-Check-Bus' => 'command.bus'],
        ];
        // a claim that could not be retrieved is sent again unsigned: anyone can write the same failure with another bus name
        $failure = $inner->encode(new Envelope(new MessageDecodingFailedException('Unable to retrieve the claim check.', 0, null, $claim), null === $failureBusName ? [] : [new BusNameStamp($failureBusName)]));
        $transport = new SerializingFailureTestSenderAndReceiver($serializer, [$failure]);
        $failureTransport = new SerializingFailureTestSenderAndReceiver($serializer, []);
        $serializerLocator = new ServiceLocator(['transport' => static fn () => $serializer]);

        $calls = [];
        $buses = [];
        foreach (['command.bus', 'event.bus'] as $busName) {
            $buses[$busName] = new MessageBus([
                new AddBusNameStampMiddleware($busName),
                new DecodeFailedMessageMiddleware($serializerLocator),
                new HandleMessageMiddleware(new HandlersLocator([DummyMessage::class => [static function () use (&$calls, $busName) { $calls[] = $busName; }]])),
            ]);
        }
        $bus = new RoutableMessageBus(new ServiceLocator(['command.bus' => static fn () => $buses['command.bus'], 'event.bus' => static fn () => $buses['event.bus']]), $buses['event.bus']);

        $throwables = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new SendFailedMessageForRetryListener(new ServiceLocator(['transport' => static fn () => $transport]), new ServiceLocator(['transport' => static fn () => new MultiplierRetryStrategy(2, 0)])));
        $dispatcher->addSubscriber(new SendFailedMessageToFailureTransportListener(new ServiceLocator(['transport' => static fn () => $failureTransport])));
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));
        $dispatcher->addListener(WorkerMessageFailedEvent::class, static function (WorkerMessageFailedEvent $event) use (&$throwables) {
            $throwables[] = $event->getThrowable()::class;
        });

        (new Worker(['transport' => $transport], null === $workerBusName ? $bus : $buses[$workerBusName], $dispatcher))->run();

        $this->assertSame($expectedCalls, $calls);
        $this->assertSame($expectedCalls ? [] : [InvalidMessageSignatureException::class], $throwables);
        $this->assertSame([], $transport->getMessagesWaitingToBeReceived());
        $this->assertCount($expectedCalls ? 0 : 1, $failureTransport->getMessagesWaitingToBeReceived());
    }

    public static function provideUnverifiedFailuresOfSignedClaims(): iterable
    {
        foreach (['JSON' => new Serializer(), 'PHP' => new PhpSerializer()] as $format => $inner) {
            yield $format.', on the bus of its message' => [$inner, 'command.bus', null, ['command.bus']];
            yield $format.', naming another bus' => [$inner, 'event.bus', null, []];
            yield $format.', naming no bus' => [$inner, null, null, []];
        }

        yield 'worker with a bus, on the bus of its message' => [new Serializer(), 'command.bus', 'event.bus', ['event.bus']];
        yield 'worker with a bus, naming another bus' => [new Serializer(), 'event.bus', 'command.bus', []];
    }

    #[DataProvider('provideMessagesRefusedByATransportThatSignsEveryMessage')]
    public function testMessageRefusedByATransportThatSignsEveryMessageIsNeverHandled(SerializerInterface $inner, array $encodedEnvelope)
    {
        $serializer = new SigningSerializer($inner, 'signing-key', ['*']);
        $failureSerializer = new SigningSerializer($inner, 'signing-key', ['*'], 'sha256', true);
        $transport = new SerializingFailureTestSenderAndReceiver($serializer, [$encodedEnvelope]);
        $failureTransport = new SerializingFailureTestSenderAndReceiver($failureSerializer, []);

        $handler = new DummyTestHandler(false);
        $bus = new MessageBus([
            new DecodeFailedMessageMiddleware(new ServiceLocator(['transport' => static fn () => $serializer, 'failed' => static fn () => $failureSerializer])),
            new FailedMessageProcessingMiddleware(),
            new HandleMessageMiddleware(new HandlersLocator([DummyMessage::class => [new HandlerDescriptor($handler)]])),
        ]);

        $throwables = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new SendFailedMessageForRetryListener(new ServiceLocator(['transport' => static fn () => $transport, 'failed' => static fn () => $failureTransport]), new ServiceLocator(['transport' => static fn () => new MultiplierRetryStrategy(2, 0), 'failed' => static fn () => new MultiplierRetryStrategy(2, 0)])));
        $dispatcher->addSubscriber(new SendFailedMessageToFailureTransportListener(new ServiceLocator(['transport' => static fn () => $failureTransport])));
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));
        $dispatcher->addListener(WorkerMessageFailedEvent::class, static function (WorkerMessageFailedEvent $event) use (&$throwables) {
            $throwables[] = $event->getThrowable();
        });

        (new Worker(['transport' => $transport], $bus, $dispatcher))->run();

        $this->assertSame([], $transport->getMessagesWaitingToBeReceived());
        $this->assertCount(1, $failed = $failureTransport->getMessagesWaitingToBeReceived());
        $this->assertArrayNotHasKey('Body-Sign', $failed[0]['headers'] ?? []);

        // messenger:failed:retry consumes the failure transport
        (new Worker(['failed' => $failureTransport], $bus, $dispatcher))->run();

        $this->assertSame(0, $handler->getTimesCalled());
        $this->assertCount(2, $throwables);
        $this->assertContainsOnlyInstancesOf(InvalidMessageSignatureException::class, $throwables);
    }

    public static function provideMessagesRefusedByATransportThatSignsEveryMessage(): iterable
    {
        $envelope = new Envelope(new DummyMessage('API'));

        foreach (['JSON' => new Serializer(), 'PHP' => new PhpSerializer()] as $format => $inner) {
            yield $format.' without signature' => [$inner, $inner->encode($envelope)];
            yield $format.' signed with another key' => [$inner, (new SigningSerializer($inner, 'another-key', ['*']))->encode($envelope)];
            yield $format.' signed by a transport that signs the message type with another key' => [$inner, (new SigningSerializer($inner, 'another-key', [DummyMessage::class]))->encode($envelope)];
            yield $format.' with a body-only signature' => [$inner, ['headers' => ['Body-Sign' => hash_hmac('sha256', $inner->encode($envelope)['body'], 'signing-key'), 'Sign-Algo' => 'sha256'] + ($inner->encode($envelope)['headers'] ?? [])] + $inner->encode($envelope)];
            yield $format.' signed as unverified' => [$inner, (new SigningSerializer($inner, 'signing-key', ['*']))->encode($envelope->with(new ReceivedStamp('async')))];
        }
    }

    #[DataProvider('provideInnerSerializers')]
    public function testMessageSignedByTransportsThatSignEveryMessageIsRetriedThenRetriedFromTheFailureTransport(SerializerInterface $inner)
    {
        $serializer = new SigningSerializer($inner, 'signing-key', ['*']);
        $failureSerializer = new SigningSerializer($inner, 'signing-key', ['*'], 'sha256', true);
        $transport = new SerializingFailureTestSenderAndReceiver($serializer, []);
        $failureTransport = new SerializingFailureTestSenderAndReceiver($failureSerializer, []);
        $senders = new ServiceLocator(['transport' => static fn () => $transport, 'failed' => static fn () => $failureTransport]);

        $handler = new DummyTestHandler(true);
        $bus = new MessageBus([
            new DecodeFailedMessageMiddleware(new ServiceLocator(['transport' => static fn () => $serializer, 'failed' => static fn () => $failureSerializer])),
            new FailedMessageProcessingMiddleware(),
            new SendMessageMiddleware(new SendersLocator([DummyMessage::class => ['transport']], $senders)),
            new HandleMessageMiddleware(new HandlersLocator([DummyMessage::class => [new HandlerDescriptor($handler)]])),
        ]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new SendFailedMessageForRetryListener($senders, new ServiceLocator(['transport' => static fn () => new MultiplierRetryStrategy(1, 0), 'failed' => static fn () => new MultiplierRetryStrategy(1, 0)])));
        $dispatcher->addSubscriber(new SendFailedMessageToFailureTransportListener(new ServiceLocator(['transport' => static fn () => $failureTransport])));
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));

        $bus->dispatch(new DummyMessage('API'));
        $this->assertStringStartsWith('v2:', $transport->getMessagesWaitingToBeReceived()[0]['headers']['Body-Sign']);

        (new Worker(['transport' => $transport], $bus, $dispatcher))->run();
        $this->assertStringStartsWith('v2:', $transport->getMessagesWaitingToBeReceived()[0]['headers']['Body-Sign']);

        (new Worker(['transport' => $transport], $bus, $dispatcher))->run();
        $this->assertSame([], $transport->getMessagesWaitingToBeReceived());
        $this->assertStringStartsWith('v2:', $failureTransport->getMessagesWaitingToBeReceived()[0]['headers']['Body-Sign']);
        $this->assertArrayNotHasKey('Sign-Trust', $failureTransport->getMessagesWaitingToBeReceived()[0]['headers']);
        $this->assertTrue($failureSerializer->decode($failureTransport->getMessagesWaitingToBeReceived()[0])->last(TrustStamp::class)?->isTrusted());

        // messenger:failed:retry consumes the failure transport
        (new Worker(['failed' => $failureTransport], $bus, $dispatcher))->run();
        $this->assertStringStartsWith('v2:', $failureTransport->getMessagesWaitingToBeReceived()[0]['headers']['Body-Sign']);
        $this->assertArrayNotHasKey('Sign-Trust', $failureTransport->getMessagesWaitingToBeReceived()[0]['headers']);

        $handler->setShouldThrow(false);
        (new Worker(['failed' => $failureTransport], $bus, $dispatcher))->run();

        $this->assertSame(4, $handler->getTimesCalled());
        $this->assertSame([], $transport->getMessagesWaitingToBeReceived());
        $this->assertSame([], $failureTransport->getMessagesWaitingToBeReceived());
    }

    public static function provideInnerSerializers(): iterable
    {
        yield 'JSON' => [new Serializer()];
        yield 'PHP' => [new PhpSerializer()];
    }

    #[DataProvider('provideInnerSerializers')]
    public function testFailureOfATransportThatDoesNotSignIsSignedAsUnverifiedByAFailureTransportThatSignsEveryMessage(SerializerInterface $inner)
    {
        $serializer = new SigningSerializer($inner, 'signing-key', [\stdClass::class]);
        $failureSerializer = new SigningSerializer($inner, 'signing-key', ['*', \stdClass::class], 'sha256', true);
        $transport = new SerializingFailureTestSenderAndReceiver($serializer, [$serializer->encode(new Envelope(new DummyMessage('API')))]);
        $failureTransport = new SerializingFailureTestSenderAndReceiver($failureSerializer, []);

        $handler = new DummyTestHandler(true);
        $bus = new MessageBus([
            new DecodeFailedMessageMiddleware(new ServiceLocator(['transport' => static fn () => $serializer, 'failed' => static fn () => $failureSerializer])),
            new FailedMessageProcessingMiddleware(),
            new HandleMessageMiddleware(new HandlersLocator([DummyMessage::class => [new HandlerDescriptor($handler)]])),
        ]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new SendFailedMessageForRetryListener(new ServiceLocator(['transport' => static fn () => $transport, 'failed' => static fn () => $failureTransport]), new ServiceLocator(['transport' => static fn () => new MultiplierRetryStrategy(0, 0), 'failed' => static fn () => new MultiplierRetryStrategy(1, 0)])));
        $dispatcher->addSubscriber(new SendFailedMessageToFailureTransportListener(new ServiceLocator(['transport' => static fn () => $failureTransport])));
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));

        (new Worker(['transport' => $transport], $bus, $dispatcher))->run();

        $this->assertSame([], $transport->getMessagesWaitingToBeReceived());
        $this->assertCount(1, $failed = $failureTransport->getMessagesWaitingToBeReceived());
        $this->assertStringStartsWith('v2:', $failed[0]['headers']['Body-Sign']);
        $this->assertSame('unverified', $failed[0]['headers']['Sign-Trust']);

        // messenger:failed:retry consumes the failure transport
        (new Worker(['failed' => $failureTransport], $bus, $dispatcher))->run();

        $this->assertCount(1, $failed = $failureTransport->getMessagesWaitingToBeReceived());
        $this->assertSame('unverified', $failed[0]['headers']['Sign-Trust']);

        $handler->setShouldThrow(false);
        (new Worker(['failed' => $failureTransport], $bus, $dispatcher))->run();

        $this->assertSame(3, $handler->getTimesCalled());
        $this->assertSame([], $failureTransport->getMessagesWaitingToBeReceived());
    }

    #[DataProvider('provideInnerSerializers')]
    public function testFailureOfATransportThatDoesNotSignIsRefusedByATransportThatSignsEveryMessageWhenRedispatched(SerializerInterface $inner)
    {
        $serializer = new SigningSerializer($inner, 'signing-key', [\stdClass::class]);
        $signingSerializer = new SigningSerializer($inner, 'signing-key', ['*', \stdClass::class]);
        $failureSerializer = new SigningSerializer($inner, 'signing-key', ['*', \stdClass::class], 'sha256', true);
        $transport = new SerializingFailureTestSenderAndReceiver($serializer, [$serializer->encode(new Envelope(new DummyMessage('API')))]);
        $signingTransport = new SerializingFailureTestSenderAndReceiver($signingSerializer, []);
        $failureTransport = new SerializingFailureTestSenderAndReceiver($failureSerializer, []);

        $handler = new DummyTestHandler(true);
        $bus = new MessageBus([
            new DecodeFailedMessageMiddleware(new ServiceLocator(['transport' => static fn () => $serializer, 'signing' => static fn () => $signingSerializer, 'failed' => static fn () => $failureSerializer])),
            new FailedMessageProcessingMiddleware(),
            new SendMessageMiddleware(new SendersLocator([DummyMessage::class => ['signing']], new ServiceLocator(['signing' => static fn () => $signingTransport]))),
            new HandleMessageMiddleware(new HandlersLocator([DummyMessage::class => [new HandlerDescriptor($handler)]])),
        ]);

        $throwables = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new SendFailedMessageForRetryListener(new ServiceLocator(['transport' => static fn () => $transport, 'signing' => static fn () => $signingTransport]), new ServiceLocator(['transport' => static fn () => new MultiplierRetryStrategy(0, 0), 'signing' => static fn () => new MultiplierRetryStrategy(0, 0)])));
        $dispatcher->addSubscriber(new SendFailedMessageToFailureTransportListener(new ServiceLocator(['transport' => static fn () => $failureTransport, 'signing' => static fn () => $failureTransport])));
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));
        $dispatcher->addListener(WorkerMessageFailedEvent::class, static function (WorkerMessageFailedEvent $event) use (&$throwables) {
            $throwables[] = $event->getThrowable()::class;
        });

        (new Worker(['transport' => $transport], $bus, $dispatcher))->run();
        $this->assertSame('unverified', $failureTransport->getMessagesWaitingToBeReceived()[0]['headers']['Sign-Trust']);

        // messenger:failed:retry --redispatch
        $failed = iterator_to_array($failureTransport->get());
        (new FailedMessageRepository(new ServiceLocator(['failed' => static fn () => $failureTransport]), 'failed', null, $bus))->redispatch($failed[0]);

        $this->assertCount(1, $redispatched = $signingTransport->getMessagesWaitingToBeReceived());
        $this->assertSame('unverified', $redispatched[0]['headers']['Sign-Trust']);

        (new Worker(['signing' => $signingTransport], $bus, $dispatcher))->run();

        $this->assertSame(1, $handler->getTimesCalled());
        $this->assertSame([HandlerFailedException::class, InvalidMessageSignatureException::class], $throwables);
        $this->assertSame([], $signingTransport->getMessagesWaitingToBeReceived());
        $this->assertCount(1, $refused = $failureTransport->getMessagesWaitingToBeReceived());
        $this->assertArrayNotHasKey('Body-Sign', $refused[0]['headers'] ?? []);

        (new Worker(['failed' => $failureTransport], $bus, $dispatcher))->run();

        $this->assertSame(1, $handler->getTimesCalled());
        $this->assertSame([HandlerFailedException::class, InvalidMessageSignatureException::class, InvalidMessageSignatureException::class], $throwables);
    }

    #[DataProvider('provideInnerSerializers')]
    public function testClaimCheckOverATransportThatSignsEveryMessage(SerializerInterface $inner)
    {
        $values = [];
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $pool->method('getItem')->willReturnCallback(function (string $id) use (&$values): CacheItemInterface {
            $item = $this->createStub(CacheItemInterface::class);
            $item->method('isHit')->willReturn(isset($values[$id]));
            $item->method('get')->willReturn($values[$id] ?? null);
            $item->method('set')->willReturnCallback(static function (mixed $value) use (&$values, $id, $item): CacheItemInterface {
                $values[$id] = $value;

                return $item;
            });

            return $item;
        });
        $pool->method('save')->willReturn(true);
        $serializer = new ClaimCheckSerializer(new SigningSerializer($inner, 'signing-key', ['*']), $pool, 300);

        $forgedData = serialize(['body' => '{"message":"forged"}', 'headers' => ['type' => 'Unknown\Missing\MessageClass', 'X-Message-Stamp-Unknown\Missing\StampClass' => '[{}]']]);
        $values['forged'] = $forgedData;
        $forged = [
            'body' => json_encode(['id' => 'forged', 'digest' => hash('sha256', $forgedData)]),
            'headers' => ['X-Symfony-Messenger-Claim-Check' => '1', 'X-Symfony-Messenger-Claim-Check-Type' => 'Unknown\Missing\MessageClass'],
        ];
        $transport = new SerializingFailureTestSenderAndReceiver($serializer, [$forged, $serializer->encode(new Envelope(new DummyMessage(str_repeat('a', 1000))))]);
        $failureTransport = new DummyFailureTestSenderAndReceiver();
        $this->assertArrayNotHasKey('Body-Sign', $transport->getMessagesWaitingToBeReceived()[1]['headers']);

        $handler = new DummyTestHandler(true);
        $bus = new MessageBus([
            new DecodeFailedMessageMiddleware(new ServiceLocator(['transport' => static fn () => $serializer])),
            new FailedMessageProcessingMiddleware(),
            new HandleMessageMiddleware(new HandlersLocator([DummyMessage::class => [new HandlerDescriptor($handler)]])),
        ]);

        $throwables = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new SendFailedMessageForRetryListener(new ServiceLocator(['transport' => static fn () => $transport]), new ServiceLocator(['transport' => static fn () => new MultiplierRetryStrategy(1, 0)])));
        $dispatcher->addSubscriber(new SendFailedMessageToFailureTransportListener(new ServiceLocator(['transport' => static fn () => $failureTransport])));
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));
        $dispatcher->addListener(WorkerMessageFailedEvent::class, static function (WorkerMessageFailedEvent $event) use (&$throwables) {
            $throwables[] = $event->getThrowable()::class;
        });

        $requested = [];
        $autoloader = static function (string $class) use (&$requested) {
            $requested[] = $class;
        };
        spl_autoload_register($autoloader, true, true);

        try {
            (new Worker(['transport' => $transport], $bus, $dispatcher))->run();
        } finally {
            spl_autoload_unregister($autoloader);
        }

        $this->assertSame([], preg_grep('/^Unknown\\\\/', $requested));
        $this->assertSame([InvalidMessageSignatureException::class], $throwables);
        $this->assertCount(1, $failureTransport->getMessagesWaitingToBeReceived());

        (new Worker(['transport' => $transport], $bus, $dispatcher))->run();
        $handler->setShouldThrow(false);
        (new Worker(['transport' => $transport], $bus, $dispatcher))->run();

        $this->assertSame(2, $handler->getTimesCalled());
        $this->assertSame([InvalidMessageSignatureException::class, HandlerFailedException::class], $throwables);
        $this->assertSame([], $transport->getMessagesWaitingToBeReceived());
        $this->assertCount(1, $failureTransport->getMessagesWaitingToBeReceived());
    }

    #[DataProvider('provideInnerSerializers')]
    public function testClaimThatCouldNotBeRetrievedIsRetriedFromAFailureTransportThatSignsEveryMessage(SerializerInterface $inner)
    {
        $values = [];
        $available = true;
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $pool->method('getItem')->willReturnCallback(function (string $id) use (&$values, &$available): CacheItemInterface {
            $item = $this->createStub(CacheItemInterface::class);
            $item->method('isHit')->willReturnCallback(static function () use (&$values, &$available, $id) { return $available && isset($values[$id]); });
            $item->method('get')->willReturnCallback(static function () use (&$values, $id) { return $values[$id] ?? null; });
            $item->method('set')->willReturnCallback(static function (mixed $value) use (&$values, $id, $item): CacheItemInterface {
                $values[$id] = $value;

                return $item;
            });

            return $item;
        });
        $pool->method('save')->willReturn(true);
        $serializer = new ClaimCheckSerializer(new SigningSerializer($inner, 'signing-key', ['*']), $pool, 300);
        $failureSerializer = new SigningSerializer($inner, 'signing-key', ['*'], 'sha256', true);
        $transport = new SerializingFailureTestSenderAndReceiver($serializer, [$serializer->encode(new Envelope(new DummyMessage(str_repeat('a', 1000))))]);
        $failureTransport = new SerializingFailureTestSenderAndReceiver($failureSerializer, []);

        $handler = new DummyTestHandler(false);
        $bus = new MessageBus([
            new DecodeFailedMessageMiddleware(new ServiceLocator(['transport' => static fn () => $serializer, 'failed' => static fn () => $failureSerializer])),
            new FailedMessageProcessingMiddleware(),
            new HandleMessageMiddleware(new HandlersLocator([DummyMessage::class => [new HandlerDescriptor($handler)]])),
        ]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new SendFailedMessageForRetryListener(new ServiceLocator(['transport' => static fn () => $transport]), new ServiceLocator(['transport' => static fn () => new MultiplierRetryStrategy(0, 0)])));
        $dispatcher->addSubscriber(new SendFailedMessageToFailureTransportListener(new ServiceLocator(['transport' => static fn () => $failureTransport])));
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));

        $available = false;
        (new Worker(['transport' => $transport], $bus, $dispatcher))->run();

        $this->assertSame(0, $handler->getTimesCalled());
        $this->assertCount(1, $failed = $failureTransport->getMessagesWaitingToBeReceived());
        $this->assertSame('unverified', $failed[0]['headers']['Sign-Trust']);

        // messenger:failed:retry consumes the failure transport
        $available = true;
        (new Worker(['failed' => $failureTransport], $bus, $dispatcher))->run();

        $this->assertSame(1, $handler->getTimesCalled());
        $this->assertSame([], $failureTransport->getMessagesWaitingToBeReceived());
    }

    #[DataProvider('provideInnerSerializers')]
    public function testMessageOfASignedTypeFromATransportThatDoesNotSerializeIsRetriedFromAFailureTransportThatSignsIt(SerializerInterface $inner)
    {
        $transport = new InMemoryTransport();
        $failureSerializer = new SigningSerializer($inner, 'signing-key', [DummyMessage::class]);
        $failureTransport = new SerializingFailureTestSenderAndReceiver($failureSerializer, []);

        $handler = new DummyTestHandler(true);
        $bus = new MessageBus([
            new DecodeFailedMessageMiddleware(new ServiceLocator(['failed' => static fn () => $failureSerializer])),
            new FailedMessageProcessingMiddleware(),
            new SendMessageMiddleware(new SendersLocator([DummyMessage::class => ['transport']], new ServiceLocator(['transport' => static fn () => $transport]))),
            new HandleMessageMiddleware(new HandlersLocator([DummyMessage::class => [new HandlerDescriptor($handler)]])),
        ]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new SendFailedMessageToFailureTransportListener(new ServiceLocator(['transport' => static fn () => $failureTransport])));
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));

        $bus->dispatch(new DummyMessage('API'));
        (new Worker(['transport' => $transport], $bus, $dispatcher))->run();

        $this->assertSame(1, $handler->getTimesCalled());
        $this->assertCount(1, $failed = $failureTransport->getMessagesWaitingToBeReceived());
        $this->assertStringStartsWith('v2:', $failed[0]['headers']['Body-Sign']);
        $this->assertArrayNotHasKey('Sign-Trust', $failed[0]['headers']);

        // messenger:failed:retry consumes the failure transport
        $handler->setShouldThrow(false);
        (new Worker(['failed' => $failureTransport], $bus, $dispatcher))->run();

        $this->assertSame(2, $handler->getTimesCalled());
        $this->assertSame([], $failureTransport->getMessagesWaitingToBeReceived());
    }

    public function testRetryThroughTransportUsingTheStandaloneSerializer()
    {
        $transport = new InMemoryTransport(Serializer::create());
        $failureTransport = new InMemoryTransport(Serializer::create());

        $calls = 0;
        $handler = static function () use (&$calls) {
            if (1 === ++$calls) {
                throw new \RuntimeException('Failure from call 1');
            }
        };
        $bus = new MessageBus([new HandleMessageMiddleware(new HandlersLocator([DummyMessage::class => [$handler]]))]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new AddErrorDetailsStampListener());
        $dispatcher->addSubscriber(new SendFailedMessageForRetryListener(new ServiceLocator(['transport' => static fn () => $transport]), new ServiceLocator(['transport' => static fn () => new MultiplierRetryStrategy(1, 0)])));
        $dispatcher->addSubscriber(new SendFailedMessageToFailureTransportListener(new ServiceLocator(['transport' => static fn () => $failureTransport])));
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(3));
        $dispatcher->addListener(WorkerRunningEvent::class, static function (WorkerRunningEvent $event) {
            if ($event->isWorkerIdle()) {
                $event->getWorker()->stop();
            }
        });

        $transport->send(new Envelope(new DummyMessage('Hello')));

        (new Worker(['transport' => $transport], $bus, $dispatcher))->run(['sleep' => 0]);

        $this->assertSame(2, $calls);
        $this->assertSame([], $failureTransport->getSent());

        $acknowledged = $transport->getAcknowledged();
        $this->assertCount(1, $acknowledged);
        $this->assertSame(1, RedeliveryStamp::getRetryCountFromEnvelope($acknowledged[0]));
        $this->assertSame('Failure from call 1', $acknowledged[0]->last(ErrorDetailsStamp::class)->getExceptionMessage());
    }

    #[DataProvider('provideUndecodableMessages')]
    public function testUndecodableMessageIsRetriedThenSentToTheFailureTransport(array $encodedEnvelope)
    {
        $serializer = new Serializer();
        $transport = new SerializingFailureTestSenderAndReceiver($serializer, [$encodedEnvelope]);
        $failureTransport = new DummyFailureTestSenderAndReceiver();

        $locator = new Container();
        $locator->set('transport', $transport);

        $retryStrategyLocator = new Container();
        $retryStrategyLocator->set('transport', new MultiplierRetryStrategy(2));

        $bus = new MessageBus([new DecodeFailedMessageMiddleware(new ServiceLocator(['transport' => static fn () => $serializer]))]);

        $retryCounts = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new SendFailedMessageForRetryListener($locator, $retryStrategyLocator));
        $dispatcher->addSubscriber(new SendFailedMessageToFailureTransportListener(new ServiceLocator(['transport' => static fn () => $failureTransport])));
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));
        $dispatcher->addListener(WorkerMessageFailedEvent::class, static function (WorkerMessageFailedEvent $event) use (&$retryCounts) {
            $retryCounts[] = RedeliveryStamp::getRetryCountFromEnvelope($event->getEnvelope());
        });

        for ($i = 0; $i < 10 && $transport->getMessagesWaitingToBeReceived(); ++$i) {
            (new Worker(['transport' => $transport], $bus, $dispatcher))->run();
        }

        $this->assertSame([0, 1, 2], $retryCounts);
        $this->assertSame([], $transport->getMessagesWaitingToBeReceived());
        $this->assertCount(1, $failedEnvelopes = $failureTransport->getMessagesWaitingToBeReceived());
        $this->assertInstanceOf(MessageDecodingFailedException::class, $failedEnvelopes[0]->getMessage());
        $this->assertSame($encodedEnvelope['body'], $failedEnvelopes[0]->getMessage()->encodedEnvelope['body']);
    }

    #[DataProvider('provideFailureTransportSerializers')]
    public function testMessageWithoutTypeKeepsItsPayloadUntilTheFailureTransport(SerializerInterface $failureSerializer)
    {
        $serializer = new Serializer();
        $encodedEnvelope = $serializer->encode(new Envelope(new DummyMessage('API')));
        unset($encodedEnvelope['headers']['type']);
        $transport = new SerializingFailureTestSenderAndReceiver($serializer, [$encodedEnvelope]);
        $failureTransport = new SerializingFailureTestSenderAndReceiver($failureSerializer, []);

        $locator = new Container();
        $locator->set('transport', $transport);

        $retryStrategyLocator = new Container();
        $retryStrategyLocator->set('transport', new MultiplierRetryStrategy(2));

        $bus = new MessageBus([new DecodeFailedMessageMiddleware(new ServiceLocator(['transport' => static fn () => $serializer]))]);

        $errors = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new SendFailedMessageForRetryListener($locator, $retryStrategyLocator));
        $dispatcher->addSubscriber(new SendFailedMessageToFailureTransportListener(new ServiceLocator(['transport' => static fn () => $failureTransport])));
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));
        $dispatcher->addListener(WorkerMessageFailedEvent::class, static function (WorkerMessageFailedEvent $event) use (&$errors) {
            $errors[RedeliveryStamp::getRetryCountFromEnvelope($event->getEnvelope())] = $event->getThrowable()->getMessage();
        });

        for ($i = 0; $i < 10 && $transport->getMessagesWaitingToBeReceived(); ++$i) {
            (new Worker(['transport' => $transport], $bus, $dispatcher))->run();
        }

        $this->assertSame(array_fill(0, 3, 'Encoded envelope does not have a "type" header.'), $errors);
        $this->assertSame([], $transport->getMessagesWaitingToBeReceived());
        $this->assertCount(1, $failedEnvelopes = $failureTransport->getMessagesWaitingToBeReceived());

        $failure = $failureSerializer->decode($failedEnvelopes[0])->getMessage();

        $this->assertInstanceOf(MessageDecodingFailedException::class, $failure);
        $this->assertSame($encodedEnvelope['body'], $failure->encodedEnvelope['body']);
        $this->assertArrayNotHasKey('type', $failure->encodedEnvelope['headers']);
    }

    public static function provideFailureTransportSerializers(): iterable
    {
        yield 'JSON' => [new Serializer()];
        yield 'PHP' => [new PhpSerializer()];
    }

    #[DataProvider('provideFailureTransportSerializersForClaims')]
    public function testClaimThatCannotBeRetrievedIsRetriedThenSentToTheFailureTransport(?SerializerInterface $failureSerializer)
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $pool->method('getItem')->willReturn($this->createStub(CacheItemInterface::class));
        $serializer = new ClaimCheckSerializer(new Serializer(), $pool, 100);
        $failureSerializer ??= $serializer;
        $claim = [
            'body' => json_encode(['id' => 'missing', 'digest' => hash('sha256', '')]),
            'headers' => ['X-Symfony-Messenger-Claim-Check' => '1', 'X-Symfony-Messenger-Claim-Check-Type' => DummyMessage::class],
        ];
        $transport = new SerializingFailureTestSenderAndReceiver($serializer, [$claim]);
        $failureTransport = new SerializingFailureTestSenderAndReceiver($failureSerializer, []);

        $locator = new Container();
        $locator->set('transport', $transport);

        $retryStrategyLocator = new Container();
        $retryStrategyLocator->set('transport', new MultiplierRetryStrategy(2));

        $bus = new MessageBus([new DecodeFailedMessageMiddleware(new ServiceLocator(['transport' => static fn () => $serializer]))]);

        $errors = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new SendFailedMessageForRetryListener($locator, $retryStrategyLocator));
        $dispatcher->addSubscriber(new SendFailedMessageToFailureTransportListener(new ServiceLocator(['transport' => static fn () => $failureTransport])));
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));
        $dispatcher->addListener(WorkerMessageFailedEvent::class, static function (WorkerMessageFailedEvent $event) use (&$errors) {
            $errors[RedeliveryStamp::getRetryCountFromEnvelope($event->getEnvelope())] = $event->getThrowable()->getMessage();
        });

        for ($i = 0; $i < 10 && $transport->getMessagesWaitingToBeReceived(); ++$i) {
            (new Worker(['transport' => $transport], $bus, $dispatcher))->run();
        }

        $this->assertSame(array_fill(0, 3, 'Claim check "missing" was not found.'), $errors);
        $this->assertSame([], $transport->getMessagesWaitingToBeReceived());
        $this->assertCount(1, $failedEnvelopes = $failureTransport->getMessagesWaitingToBeReceived());

        $failure = $failureSerializer->decode($failedEnvelopes[0])->getMessage();

        $this->assertInstanceOf(MessageDecodingFailedException::class, $failure);
        $this->assertSame($claim['body'], $failure->encodedEnvelope['body']);
        $this->assertSame($claim['headers'], array_intersect_key($failure->encodedEnvelope['headers'], $claim['headers']));
    }

    public static function provideFailureTransportSerializersForClaims(): iterable
    {
        yield 'claim check' => [null];
        yield 'JSON' => [new Serializer()];
        yield 'PHP' => [new PhpSerializer()];
    }

    #[DataProvider('provideClaimsThatCannotBeRetrievedUntilThePoolIsBack')]
    public function testClaimThatCouldNotBeRetrievedIsHandledOnItsOwnBus(string $innerClass, bool $signed, ?string $failureSerializerClass, int $maxRetries)
    {
        $values = [];
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $pool->method('getItem')->willReturnCallback(function (string $id) use (&$values): CacheItemInterface {
            $item = $this->createStub(CacheItemInterface::class);
            $item->method('isHit')->willReturn(isset($values[$id]));
            $item->method('get')->willReturn($values[$id] ?? null);
            $item->method('set')->willReturnCallback(static function (mixed $value) use (&$values, $id, $item): CacheItemInterface {
                $values[$id] = $value;

                return $item;
            });

            return $item;
        });
        $pool->method('save')->willReturn(true);
        $createSerializer = static fn (): SerializerInterface => new ClaimCheckSerializer($signed ? new SigningSerializer(new $innerClass(), 'signing-key', [DummyMessage::class]) : new $innerClass(), $pool, 300);

        $queues = ['transport' => [$createSerializer()->encode(new Envelope(new DummyMessage(str_repeat('a', 1000)), [new BusNameStamp('command.bus')]))], 'failed' => []];
        $calls = [];
        $handled = [];

        $runWorkers = static function (int $messageLimit) use (&$queues, &$calls, &$handled, $createSerializer, $failureSerializerClass, $maxRetries) {
            $serializer = $createSerializer();
            $failureSerializer = null === $failureSerializerClass ? $serializer : new $failureSerializerClass();
            $transport = new SerializingFailureTestSenderAndReceiver($serializer, $queues['transport']);
            $failureTransport = new SerializingFailureTestSenderAndReceiver($failureSerializer, $queues['failed']);
            $serializerLocator = new ServiceLocator(['transport' => static fn () => $serializer, 'failed' => static fn () => $failureSerializer]);

            $commandBus = new MessageBus([
                new AddBusNameStampMiddleware('command.bus'),
                new DecodeFailedMessageMiddleware($serializerLocator),
                new FailedMessageProcessingMiddleware(),
                new HandleMessageMiddleware(new HandlersLocator([DummyMessage::class => [static function () use (&$calls) { $calls[] = 'command.bus'; }]])),
            ]);
            $eventBus = new MessageBus([
                new AddBusNameStampMiddleware('event.bus'),
                new DecodeFailedMessageMiddleware($serializerLocator),
                new FailedMessageProcessingMiddleware(),
                new HandleMessageMiddleware(new HandlersLocator([]), true),
            ]);
            $bus = new RoutableMessageBus(new ServiceLocator(['command.bus' => static fn () => $commandBus, 'event.bus' => static fn () => $eventBus]), $eventBus);

            $dispatcher = new EventDispatcher();
            $dispatcher->addSubscriber(new SendFailedMessageForRetryListener(new ServiceLocator(['transport' => static fn () => $transport]), new ServiceLocator(['transport' => static fn () => new MultiplierRetryStrategy($maxRetries, 0)])));
            $dispatcher->addSubscriber(new SendFailedMessageToFailureTransportListener(new ServiceLocator(['transport' => static fn () => $failureTransport])));
            $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));
            $dispatcher->addListener(WorkerMessageHandledEvent::class, static function (WorkerMessageHandledEvent $event) use (&$handled) {
                $handled[] = $event->getEnvelope();
            });

            for ($i = 0; $i < $messageLimit && $receivers = array_filter(['transport' => $transport, 'failed' => $failureTransport], static fn ($receiver) => $receiver->getMessagesWaitingToBeReceived()); ++$i) {
                (new Worker($receivers, $bus, $dispatcher))->run();
            }

            $queues = ['transport' => $transport->getMessagesWaitingToBeReceived(), 'failed' => $failureTransport->getMessagesWaitingToBeReceived()];
        };

        $claims = $values;
        $values = [];
        $runWorkers(1);

        $this->assertSame([], $handled);

        $values = $claims;
        $runWorkers(10);

        $this->assertSame(['command.bus'], $calls);
        $this->assertCount(1, $handled);
        $this->assertSame('command.bus', $handled[0]->last(BusNameStamp::class)?->getBusName());
        $this->assertSame(['transport' => [], 'failed' => []], $queues);
    }

    public static function provideClaimsThatCannotBeRetrievedUntilThePoolIsBack(): iterable
    {
        foreach (['JSON' => Serializer::class, 'PHP' => PhpSerializer::class] as $format => $innerClass) {
            yield $format.', retried' => [$innerClass, false, null, 1];
            yield $format.' signed, retried' => [$innerClass, true, null, 1];
            yield $format.' signed, from the failure transport' => [$innerClass, true, null, 0];
        }

        yield 'JSON signed, from a PHP failure transport' => [Serializer::class, true, PhpSerializer::class, 0];
        yield 'PHP signed, from a JSON failure transport' => [PhpSerializer::class, true, Serializer::class, 0];
    }

    public static function provideUndecodableMessages(): iterable
    {
        $encodedEnvelope = (new Serializer())->encode(new Envelope(new DummyMessage('API')));

        yield 'empty body' => [['body' => ''] + $encodedEnvelope];
        yield 'body "0"' => [['body' => '0'] + $encodedEnvelope];
        yield 'stamp class not found' => [['headers' => $encodedEnvelope['headers'] + ['X-Message-Stamp-App\NonExistentStamp' => '[{}]']] + $encodedEnvelope];
        yield 'stamp header that is not a stamp' => [['headers' => $encodedEnvelope['headers'] + ['X-Message-Stamp-'.DummyMessage::class => '[{"message":"injected"}]']] + $encodedEnvelope];
        yield 'message class not found' => [['headers' => ['type' => 'App\NonExistentMessage'] + $encodedEnvelope['headers']] + $encodedEnvelope];
    }

    #[DataProvider('provideMessagesThatFailToDecodeUntilTheirClassIsDeployed')]
    public function testMessageThatFailedToDecodeIsHandledOnItsOwnBus(string $messageClass, string $serializerClass, string $failureSerializerClass, int $maxRetries, bool $signAll = false, ?bool $failureTransportSignsAll = null)
    {
        $encodedEnvelope = (new $serializerClass())->encode(new Envelope(new DummyMessage('API'), [new BusNameStamp('command.bus')]));

        if (PhpSerializer::class === $serializerClass) {
            $encodedEnvelope['body'] = addslashes(str_replace('O:'.\strlen(DummyMessage::class).':"'.DummyMessage::class.'"', 'O:'.\strlen($messageClass).':"'.$messageClass.'"', stripslashes($encodedEnvelope['body'])));
        } else {
            $encodedEnvelope['headers']['type'] = $messageClass;
        }

        $failureTransportSignsAll ??= $signAll;
        $createSerializer = static fn (string $class, bool $signAll, bool $acceptUnverified = false): SerializerInterface => $signAll ? new SigningSerializer(new $class(), 'signing-key', ['*'], 'sha256', $acceptUnverified) : new $class();

        if ($signAll) {
            $encodedEnvelope = (new SigningSerializer(new class($encodedEnvelope) implements SerializerInterface {
                public function __construct(
                    private array $encodedEnvelope,
                ) {
                }

                public function decode(array $encodedEnvelope): Envelope
                {
                    throw new \BadMethodCallException();
                }

                public function encode(Envelope $envelope): array
                {
                    return $this->encodedEnvelope;
                }
            }, 'signing-key', ['*']))->encode(new Envelope(new DummyMessage('API')));
        }

        $queues = ['transport' => [$encodedEnvelope], 'failed' => []];
        $calls = [];
        $handled = [];

        $runWorkers = static function (int $messageLimit) use (&$queues, &$calls, &$handled, $serializerClass, $failureSerializerClass, $maxRetries, $createSerializer, $signAll, $failureTransportSignsAll) {
            $serializer = $createSerializer($serializerClass, $signAll);
            $failureSerializer = $createSerializer($failureSerializerClass, $failureTransportSignsAll, true);
            $transport = new SerializingFailureTestSenderAndReceiver($serializer, $queues['transport']);
            $failureTransport = new SerializingFailureTestSenderAndReceiver($failureSerializer, $queues['failed']);
            $serializerLocator = new ServiceLocator(['transport' => static fn () => $serializer, 'failed' => static fn () => $failureSerializer]);

            $commandBus = new MessageBus([
                new AddBusNameStampMiddleware('command.bus'),
                new DecodeFailedMessageMiddleware($serializerLocator),
                new FailedMessageProcessingMiddleware(),
                new HandleMessageMiddleware(new HandlersLocator([DummyMessage::class => [static function () use (&$calls) { $calls[] = 'command.bus'; }]])),
            ]);
            $eventBus = new MessageBus([
                new AddBusNameStampMiddleware('event.bus'),
                new DecodeFailedMessageMiddleware($serializerLocator),
                new FailedMessageProcessingMiddleware(),
                new HandleMessageMiddleware(new HandlersLocator([]), true),
            ]);
            $bus = new RoutableMessageBus(new ServiceLocator(['command.bus' => static fn () => $commandBus, 'event.bus' => static fn () => $eventBus]), $eventBus);

            $dispatcher = new EventDispatcher();
            $dispatcher->addSubscriber(new SendFailedMessageForRetryListener(new ServiceLocator(['transport' => static fn () => $transport]), new ServiceLocator(['transport' => static fn () => new MultiplierRetryStrategy($maxRetries, 0)])));
            $dispatcher->addSubscriber(new SendFailedMessageToFailureTransportListener(new ServiceLocator(['transport' => static fn () => $failureTransport])));
            $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));
            $dispatcher->addListener(WorkerMessageHandledEvent::class, static function (WorkerMessageHandledEvent $event) use (&$handled) {
                $handled[] = $event->getEnvelope();
            });

            for ($i = 0; $i < $messageLimit && $receivers = array_filter(['transport' => $transport, 'failed' => $failureTransport], static fn ($receiver) => $receiver->getMessagesWaitingToBeReceived()); ++$i) {
                (new Worker($receivers, $bus, $dispatcher))->run();
            }

            $queues = ['transport' => $transport->getMessagesWaitingToBeReceived(), 'failed' => $failureTransport->getMessagesWaitingToBeReceived()];
        };

        $runWorkers(1);

        $this->assertSame([], $handled);

        class_alias(DummyMessage::class, $messageClass);
        $runWorkers(10);

        $this->assertSame(['command.bus'], $calls);
        $this->assertCount(1, $handled);
        $this->assertSame('command.bus', $handled[0]->last(BusNameStamp::class)?->getBusName());
        $this->assertSame(['transport' => [], 'failed' => []], $queues);
    }

    public static function provideMessagesThatFailToDecodeUntilTheirClassIsDeployed(): iterable
    {
        yield 'PHP, retried' => ['App\PhpMessageRetried', PhpSerializer::class, PhpSerializer::class, 1];
        yield 'PHP, from a PHP failure transport' => ['App\PhpMessageFromPhpFailureTransport', PhpSerializer::class, PhpSerializer::class, 0];
        yield 'PHP, from a JSON failure transport' => ['App\PhpMessageFromJsonFailureTransport', PhpSerializer::class, Serializer::class, 0];
        yield 'JSON, retried' => ['App\JsonMessageRetried', Serializer::class, Serializer::class, 1];
        yield 'JSON, from a JSON failure transport' => ['App\JsonMessageFromJsonFailureTransport', Serializer::class, Serializer::class, 0];
        yield 'JSON, from a PHP failure transport' => ['App\JsonMessageFromPhpFailureTransport', Serializer::class, PhpSerializer::class, 0];
        yield 'PHP signing every message, retried' => ['App\PhpSignedMessageRetried', PhpSerializer::class, PhpSerializer::class, 1, true];
        yield 'PHP signing every message, from a failure transport' => ['App\PhpSignedMessageFromFailureTransport', PhpSerializer::class, PhpSerializer::class, 0, true];
        yield 'JSON signing every message, retried' => ['App\JsonSignedMessageRetried', Serializer::class, Serializer::class, 1, true];
        yield 'JSON signing every message, from a failure transport' => ['App\JsonSignedMessageFromFailureTransport', Serializer::class, Serializer::class, 0, true];
        yield 'PHP signing every message, from a failure transport that does not sign' => ['App\PhpSignedMessageFromUnsignedFailureTransport', PhpSerializer::class, PhpSerializer::class, 0, true, false];
        yield 'JSON signing every message, from a failure transport that does not sign' => ['App\JsonSignedMessageFromUnsignedFailureTransport', Serializer::class, Serializer::class, 0, true, false];
        yield 'PHP not signing, from a failure transport that signs every message' => ['App\PhpUnsignedMessageFromSignedFailureTransport', PhpSerializer::class, PhpSerializer::class, 0, false, true];
        yield 'JSON not signing, from a failure transport that signs every message' => ['App\JsonUnsignedMessageFromSignedFailureTransport', Serializer::class, Serializer::class, 0, false, true];
    }

    public function testRetryThroughTransportSkipsTheHandlerWhoseResultCannotBeEncoded()
    {
        $transport = new InMemoryTransport(Serializer::create());

        $calls = ['a' => 0, 'b' => 0];
        $handlerA = static function () use (&$calls) {
            ++$calls['a'];
            $result = new \stdClass();
            $result->self = $result;

            return $result;
        };
        $handlerB = static function () use (&$calls) {
            if (1 === ++$calls['b']) {
                throw new \RuntimeException('Failure from call 1');
            }
        };
        $bus = new MessageBus([new HandleMessageMiddleware(new HandlersLocator([DummyMessage::class => [
            new HandlerDescriptor($handlerA, ['alias' => 'a']),
            new HandlerDescriptor($handlerB, ['alias' => 'b']),
        ]]))]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new SendFailedMessageForRetryListener(new ServiceLocator(['transport' => static fn () => $transport]), new ServiceLocator(['transport' => static fn () => new MultiplierRetryStrategy(1, 0)])));
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(3));
        $dispatcher->addListener(WorkerRunningEvent::class, static function (WorkerRunningEvent $event) {
            if ($event->isWorkerIdle()) {
                $event->getWorker()->stop();
            }
        });

        $transport->send(new Envelope(new DummyMessage('Hello')));

        (new Worker(['transport' => $transport], $bus, $dispatcher))->run(['sleep' => 0]);

        $this->assertSame(['a' => 1, 'b' => 2], $calls);

        $acknowledged = $transport->getAcknowledged();
        $this->assertCount(1, $acknowledged);
        $this->assertSame(['Closure@a', 'Closure@b'], array_map(static fn (HandledStamp $stamp) => $stamp->getHandlerName(), $acknowledged[0]->all(HandledStamp::class)));
    }

    public function testFailedMessageMovesFromJsonToXmlFailureTransport()
    {
        $this->assertHandledFromTheFailureTransport(new Serializer(null, 'json'), new Serializer(null, 'xml'));
    }

    public function testFailedMessageMovesFromXmlToJsonFailureTransport()
    {
        $this->assertHandledFromTheFailureTransport(new Serializer(null, 'xml'), new Serializer(null, 'json'));
    }

    public function testFailedMessageMovesToAFailureTransportThatUsesTheSameFormat()
    {
        $this->assertHandledFromTheFailureTransport(new Serializer(null, 'json'), new Serializer(null, 'json'));

        $serializer = new Serializer(null, 'xml');
        $this->assertHandledFromTheFailureTransport($serializer, $serializer);
    }

    private function assertHandledFromTheFailureTransport(Serializer $serializer, Serializer $failureSerializer): void
    {
        $transport = new InMemoryTransport($serializer);
        $failureTransport = new InMemoryTransport($failureSerializer);

        $calls = 0;
        $handler = static function () use (&$calls) {
            if (3 > ++$calls) {
                throw new \RuntimeException('Failure from call '.$calls);
            }
        };
        $bus = new MessageBus([new HandleMessageMiddleware(new HandlersLocator([DummyMessage::class => [$handler]]))]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new SendFailedMessageForRetryListener(new ServiceLocator(['transport' => static fn () => $transport]), new ServiceLocator(['transport' => static fn () => new MultiplierRetryStrategy(1, 0)])));
        $dispatcher->addSubscriber(new SendFailedMessageToFailureTransportListener(new ServiceLocator(['transport' => static fn () => $failureTransport])));
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(3));
        $dispatcher->addListener(WorkerRunningEvent::class, static function (WorkerRunningEvent $event) {
            if ($event->isWorkerIdle()) {
                $event->getWorker()->stop();
            }
        });

        $transport->send(new Envelope(new DummyMessage('Hello')));

        (new Worker(['transport' => $transport], $bus, $dispatcher))->run(['sleep' => 0]);

        $this->assertSame(2, $calls);
        $this->assertCount(1, $failureTransport->getSent());

        (new Worker(['failure_transport' => $failureTransport], $bus, $dispatcher))->run(['sleep' => 0]);

        $this->assertSame(3, $calls);
        $this->assertSame([], $failureTransport->get());

        $acknowledged = $failureTransport->getAcknowledged();
        $this->assertCount(1, $acknowledged);
        $this->assertEquals(new DummyMessage('Hello'), $acknowledged[0]->getMessage());
        $this->assertSame('transport', $acknowledged[0]->last(SentToFailureTransportStamp::class)->getOriginalReceiverName());
    }
}

class DummyFailureTestSenderAndReceiver implements ReceiverInterface, SenderInterface
{
    private array $messagesWaiting = [];

    public function get(): iterable
    {
        $message = array_shift($this->messagesWaiting);

        if (null === $message) {
            return [];
        }

        return [$message];
    }

    public function ack(Envelope $envelope): void
    {
    }

    public function reject(Envelope $envelope): void
    {
    }

    public function send(Envelope $envelope): Envelope
    {
        $this->messagesWaiting[] = $envelope;

        return $envelope;
    }

    /**
     * @return Envelope[]
     */
    public function getMessagesWaitingToBeReceived(): array
    {
        return $this->messagesWaiting;
    }
}

class SerializingFailureTestSenderAndReceiver implements ReceiverInterface, SenderInterface
{
    public function __construct(
        private SerializerInterface $serializer,
        private array $encodedEnvelopes,
    ) {
    }

    public function get(): iterable
    {
        $encodedEnvelope = array_shift($this->encodedEnvelopes);

        return null === $encodedEnvelope ? [] : [$this->serializer->decode($encodedEnvelope)];
    }

    public function ack(Envelope $envelope): void
    {
    }

    public function reject(Envelope $envelope): void
    {
    }

    public function send(Envelope $envelope): Envelope
    {
        $this->encodedEnvelopes[] = $this->serializer->encode($envelope);

        return $envelope;
    }

    public function getMessagesWaitingToBeReceived(): array
    {
        return $this->encodedEnvelopes;
    }
}

class DummyTestHandler
{
    private int $timesCalled = 0;

    public function __construct(
        private bool $shouldThrow,
    ) {
    }

    public function __invoke()
    {
        ++$this->timesCalled;

        if ($this->shouldThrow) {
            throw new \Exception('Failure from call '.$this->timesCalled);
        }
    }

    public function getTimesCalled(): int
    {
        return $this->timesCalled;
    }

    public function setShouldThrow(bool $shouldThrow)
    {
        $this->shouldThrow = $shouldThrow;
    }
}
