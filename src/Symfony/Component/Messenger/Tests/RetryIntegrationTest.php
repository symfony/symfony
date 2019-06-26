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
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessageHandlerFailingFirstTimes;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Worker;

class RetryIntegrationTest extends TestCase
{
    public function testRetryMechanism()
    {
        $senderAndReceiver = new DummySenderAndReceiver();

        $senderLocator = $this->createMock(ContainerInterface::class);
        $senderLocator->method('has')->with('transportName')->willReturn(true);
        $senderLocator->method('get')->with('transportName')->willReturn($senderAndReceiver);
        $senderLocator = new SendersLocator([DummyMessage::class => ['transportName']], $senderLocator);

        $handler = new DummyMessageHandlerFailingFirstTimes(0);
        $throwingHandler = new DummyMessageHandlerFailingFirstTimes(1);
        $handlerLocator = new HandlersLocator([
            DummyMessage::class => [
                new HandlerDescriptor($handler, ['alias' => 'first']),
                new HandlerDescriptor($throwingHandler, ['alias' => 'throwing']),
            ],
        ]);

        // dispatch the message, which will get "sent" and then received by DummySenderAndReceiver
        $bus = new MessageBus([new SendMessageMiddleware($senderLocator), new HandleMessageMiddleware($handlerLocator)]);
        $envelope = new Envelope(new DummyMessage('API'));
        $bus->dispatch($envelope);

        $worker = new Worker(['transportName' => $senderAndReceiver], $bus, ['transportName' => new MultiplierRetryStrategy()]);
        $worker->run([], function (?Envelope $envelope) use ($worker) {
            if (null === $envelope) {
                $worker->stop();
            }
        });

        $this->assertSame(1, $handler->getTimesCalledWithoutThrowing());
        $this->assertSame(1, $throwingHandler->getTimesCalledWithoutThrowing());
    }
}

class DummySenderAndReceiver implements ReceiverInterface, SenderInterface
{
    private $messagesWaiting = [];

    private $messagesReceived = [];

    public function get(): iterable
    {
        $message = array_shift($this->messagesWaiting);

        if (null === $message) {
            return [];
        }

        $this->messagesReceived[] = $message;

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
}
