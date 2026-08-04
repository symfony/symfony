<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Handler;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\Handler\RedispatchMessageHandler;
use Symfony\Component\Messenger\Message\RedispatchMessage;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;

class RedispatchMessageHandlerTest extends TestCase
{
    public function testRedispatchWithTransportNamesAddsTransportNamesStamp()
    {
        $message = new DummyMessage('hello');
        $resultEnvelope = new Envelope($message, [new HandledStamp('result', 'handler')]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($message, $this->callback(function (array $stamps) {
                $this->assertCount(1, $stamps);
                $this->assertInstanceOf(TransportNamesStamp::class, $stamps[0]);
                $this->assertSame(['async'], $stamps[0]->getTransportNames());

                return true;
            }))
            ->willReturn($resultEnvelope);

        $handler = new RedispatchMessageHandler($bus);

        $this->assertSame('result', $handler(new RedispatchMessage($message, ['async'])));
    }

    public function testRedispatchWithoutTransportNamesAddsNoTransportNamesStamp()
    {
        $message = new DummyMessage('hello');
        $resultEnvelope = new Envelope($message);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($message, [])
            ->willReturn($resultEnvelope);

        $handler = new RedispatchMessageHandler($bus);

        $handler(new RedispatchMessage($message));
    }

    #[TestWith([[]])]
    #[TestWith([''])]
    #[TestWith([['']])]
    public function testRedispatchWithoutTransportNamesSendsToTheConfiguredSender(array|string $transportNames)
    {
        $sender = $this->createSender();
        $handled = [];
        $bus = $this->createBus([DummyMessage::class => ['async']], ['async' => $sender], $handled);

        $envelope = $bus->dispatch(new RedispatchMessage(new DummyMessage('hello'), $transportNames));

        $this->assertCount(1, $sender->sent);
        $this->assertSame([], $handled);
        $this->assertNull($envelope->last(HandledStamp::class)->getResult());
    }

    public function testRedispatchWithoutTransportNamesIsHandledInProcessWhenNoSenderIsConfigured()
    {
        $sender = $this->createSender();
        $handled = [];
        $bus = $this->createBus([], ['async' => $sender], $handled);

        $envelope = $bus->dispatch(new RedispatchMessage(new DummyMessage('hello')));

        $this->assertCount(0, $sender->sent);
        $this->assertCount(1, $handled);
        $this->assertSame('handled in process', $envelope->last(HandledStamp::class)->getResult());
    }

    #[TestWith(['0'])]
    #[TestWith([['0']])]
    public function testRedispatchUsesATransportNamedZero(array|string $transportNames)
    {
        $zero = $this->createSender();
        $async = $this->createSender();
        $handled = [];
        $bus = $this->createBus([DummyMessage::class => ['async']], ['0' => $zero, 'async' => $async], $handled);

        $bus->dispatch(new RedispatchMessage(new DummyMessage('hello'), $transportNames));

        $this->assertCount(1, $zero->sent);
        $this->assertCount(0, $async->sent);
    }

    public function testRedispatchKeepsTheTransportNamesStampOfTheInnerEnvelope()
    {
        $inner = $this->createSender();
        $async = $this->createSender();
        $handled = [];
        $bus = $this->createBus([DummyMessage::class => ['async']], ['inner' => $inner, 'async' => $async], $handled);

        $message = new DummyMessage('hello');
        $bus->dispatch(new RedispatchMessage(new Envelope($message, [new TransportNamesStamp(['inner'])])));

        $this->assertCount(1, $inner->sent);
        $this->assertCount(0, $async->sent);
    }

    public function testRedispatchWithAnEmptyTransportNamesStampIsHandledInProcess()
    {
        $async = $this->createSender();
        $handled = [];
        $bus = $this->createBus([DummyMessage::class => ['async']], ['async' => $async], $handled);

        $message = new DummyMessage('hello');
        $bus->dispatch(new RedispatchMessage(new Envelope($message, [new TransportNamesStamp([])])));

        $this->assertCount(0, $async->sent);
        $this->assertCount(1, $handled);
    }

    private function createSender()
    {
        return new class implements SenderInterface {
            public array $sent = [];

            public function send(Envelope $envelope): Envelope
            {
                return $this->sent[] = $envelope;
            }
        };
    }

    private function createBus(array $sendersMap, array $senders, array &$handled): MessageBus
    {
        $bus = null;

        $sendersLocator = new Container();
        foreach ($senders as $alias => $sender) {
            $sendersLocator->set($alias, $sender);
        }

        $handlers = new HandlersLocator([
            RedispatchMessage::class => [static function (RedispatchMessage $message) use (&$bus) {
                return (new RedispatchMessageHandler($bus))($message);
            }],
            DummyMessage::class => [static function (DummyMessage $message) use (&$handled) {
                $handled[] = $message;

                return 'handled in process';
            }],
        ]);

        return $bus = new MessageBus([
            new SendMessageMiddleware(new SendersLocator($sendersMap, $sendersLocator)),
            new HandleMessageMiddleware($handlers),
        ]);
    }
}
