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
use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\EventListener\SendFailedMessageForRetryListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageToFailureTransportListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\FailedMessageProcessingMiddleware;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Worker;

class FailureIntegrationTest extends TestCase
{
    public function testRequeMechanism()
    {
        $transport1 = new DummyFailureTestSenderAndReceiver();
        $transport2 = new DummyFailureTestSenderAndReceiver();
        $failureTransport = new DummyFailureTestSenderAndReceiver();
        $transports = [
            'transport1' => $transport1,
            'transport2' => $transport2,
            'the_failure_transport' => $failureTransport,
        ];

        $locator = $this->createMock(ContainerInterface::class);
        $locator->expects($this->any())
            ->method('has')
            ->willReturn(true);
        $locator->expects($this->any())
            ->method('get')
            ->willReturnCallback(function ($transportName) use ($transports) {
                return $transports[$transportName];
            });
        $senderLocator = new SendersLocator(
            [DummyMessage::class => ['transport1', 'transport2']],
            $locator
        );

        $retryStrategyLocator = $this->createMock(ContainerInterface::class);
        $retryStrategyLocator->expects($this->any())
            ->method('has')
            ->willReturn(true);
        $retryStrategyLocator->expects($this->any())
            ->method('get')
            ->willReturn(new MultiplierRetryStrategy(1));

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
        $dispatcher->addSubscriber(new SendFailedMessageForRetryListener($locator, $retryStrategyLocator));
        $dispatcher->addSubscriber(new SendFailedMessageToFailureTransportListener($failureTransport));
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));

        $runWorker = function (string $transportName) use ($transports, $bus, $dispatcher): ?\Throwable {
            $throwable = null;
            $failedListener = function (WorkerMessageFailedEvent $event) use (&$throwable) {
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
        $this->assertEmpty($failureTransport->getMessagesWaitingToBeReceived());

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
        /** @var RedeliveryStamp $redeliveryStamp */
        $redeliveryStamp = $failedEnvelope->last(RedeliveryStamp::class);
        $this->assertNotNull($redeliveryStamp);
        $this->assertSame('Failure from call 2', $redeliveryStamp->getExceptionMessage());

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
        $this->assertEmpty($transport1->getMessagesWaitingToBeReceived());
        $this->assertEmpty($transport2->getMessagesWaitingToBeReceived());
        $this->assertEmpty($failureTransport->getMessagesWaitingToBeReceived());

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
        $this->assertEmpty($failureTransport->getMessagesWaitingToBeReceived());
    }
}

class DummyFailureTestSenderAndReceiver implements ReceiverInterface, SenderInterface
{
    private $messagesWaiting = [];

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

class DummyTestHandler
{
    private $timesCalled = 0;
    private $shouldThrow;

    public function __construct(bool $shouldThrow)
    {
        $this->shouldThrow = $shouldThrow;
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
