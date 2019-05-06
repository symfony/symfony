<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Failure;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Failure\FailedMessage;
use Symfony\Component\Messenger\Failure\FailedMessageHandler;
use Symfony\Component\Messenger\Failure\SendFailedMessageToFailureTransportListener;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Worker;

class FailureIntegrationTest extends TestCase
{
    public function testRequeMechanism()
    {
        $transport1 = new DummySenderAndReceiver();
        $transport2 = new DummySenderAndReceiver();
        $failureTransport = new DummySenderAndReceiver();
        $transports = [
            'transport1' => $transport1,
            'transport2' => $transport2,
            'failure_transport' => $failureTransport,
        ];

        $senderLocator = new SendersLocator([
            DummyMessage::class => ['transport1' => $transport1, 'transport2' => $transport2],
            FailedMessage::class => ['failure_transport' => $failureTransport],
        ]);

        // using to so we can lazily get the bus later and avoid circular problem
        $handlerThatFails = new DummyTestHandler(true);
        $handlerThatWorks1 = new DummyTestHandler(false);
        $handlerThatWorks2 = new DummyTestHandler(false);
        $container = new Container();
        $handlerLocator = new HandlersLocator([
            DummyMessage::class => [
                new HandlerDescriptor($handlerThatFails, [
                    'from_transport' => 'transport1',
                    'alias' => 'handler_that_fails',
                ]),
                new HandlerDescriptor($handlerThatWorks1, [
                    'alias' => 'handler_that_works1',
                ]),
                new HandlerDescriptor($handlerThatWorks2, [
                    'from_transport' => 'transport2',
                    'alias' => 'handler_that_works2',
                ]),
            ],
            FailedMessage::class => [
                new HandlerDescriptor(function (FailedMessage $message) use ($container) {
                    (new FailedMessageHandler($container->get('bus')))($message);
                }),
            ],
        ]);

        $dispatcher = new EventDispatcher();
        $bus = new MessageBus([new SendMessageMiddleware($senderLocator), new HandleMessageMiddleware($handlerLocator)]);
        $container->set('bus', $bus);
        $dispatcher->addSubscriber(new SendFailedMessageToFailureTransportListener($bus));

        $runWorker = function (string $transportName) use ($transports, $bus, $dispatcher) {
            $workerForTransport1 = new Worker([$transportName => $transports[$transportName]], $bus, [$transportName => new MultiplierRetryStrategy(0)], $dispatcher);

            $workerForTransport1->run([], function (?Envelope $envelope) use ($workerForTransport1) {
                // handle one envelope, then stop
                if (null !== $envelope) {
                    $workerForTransport1->stop();
                }
            });
        };

        // send the message
        $envelope = new Envelope(new DummyMessage('API'));
        $bus->dispatch($envelope);

        // message has been sent
        $this->assertCount(1, $transport1->getMessagesWaitingToBeReceived());
        $this->assertCount(1, $transport2->getMessagesWaitingToBeReceived());
        $this->assertCount(0, $failureTransport->getMessagesWaitingToBeReceived());

        // receive the message - it should fail the first time
        // then not be retried and sent to the failure transport
        $runWorker('transport1');

        $this->assertEmpty($transport1->getMessagesWaitingToBeReceived());

        $this->assertCount(1, $failureTransport->getMessagesWaitingToBeReceived());
        /** @var FailedMessage $message */
        $message = $failureTransport->getMessagesWaitingToBeReceived()[0]->getMessage();
        $this->assertInstanceOf(FailedMessage::class, $message);
        $this->assertSame('Failure from call 1', $message->getExceptionMessage());
        // transport 1 only calls these 2 handlers
        $this->assertSame(1, $handlerThatFails->getTimesCalled());
        $this->assertSame(1, $handlerThatWorks1->getTimesCalled());
        $this->assertSame(0, $handlerThatWorks2->getTimesCalled());

        // one message should be handled and re-sent
        $runWorker('failure_transport');
        $this->assertCount(1, $transport1->getMessagesWaitingToBeReceived());
        // still only 1 waiting: was not redelivered here
        $this->assertCount(1, $transport2->getMessagesWaitingToBeReceived());
        $this->assertSame($envelope->getMessage(), $transport1->getMessagesWaitingToBeReceived()[0]->getMessage());

        // should receive the requeued message, but only the failed handler will re-execute
        $runWorker('transport1');
        // message fails again, is sent to failure transport
        $this->assertCount(1, $failureTransport->getMessagesWaitingToBeReceived());
        $this->assertSame(2, $handlerThatFails->getTimesCalled());
        // these counts remain the same
        $this->assertSame(1, $handlerThatWorks1->getTimesCalled());
        $this->assertSame(0, $handlerThatWorks2->getTimesCalled());

        $runWorker('transport2');
        $this->assertSame(2, $handlerThatFails->getTimesCalled());
        // both success handlers are called from transport2
        $this->assertSame(2, $handlerThatWorks1->getTimesCalled());
        $this->assertSame(1, $handlerThatWorks2->getTimesCalled());

        /** @var FailedMessage $failedMessage */
        $failedMessage = $failureTransport->getMessagesWaitingToBeReceived()[0]->getMessage();
        // set message to retry immediately
        $failedMessage->setToRetryStrategy();
        $runWorker('failure_transport');
        $this->assertSame(3, $handlerThatFails->getTimesCalled());
        // message was not requeued
        $this->assertEmpty($transport1->getMessagesWaitingToBeReceived());
        // message failed and due to no retries, messages was sent back to failure transport
        $this->assertCount(1, $failureTransport->getMessagesWaitingToBeReceived());

        // make the handler work
        $handlerThatFails->setShouldThrow(false);
        /** @var FailedMessage $failedMessage */
        $failedMessage = $failureTransport->getMessagesWaitingToBeReceived()[0]->getMessage();
        // set message to retry immediately
        $failedMessage->setToRetryStrategy();
        $runWorker('failure_transport');
        $this->assertSame(4, $handlerThatFails->getTimesCalled());
        // assert these counts have not changed
        $this->assertSame(2, $handlerThatWorks1->getTimesCalled());
        $this->assertSame(1, $handlerThatWorks2->getTimesCalled());
    }
}

class DummySenderAndReceiver implements ReceiverInterface, SenderInterface
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
