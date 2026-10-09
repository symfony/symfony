<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Middleware;

use PHPUnit\Framework\Attributes\DataProvider;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\MessageSentToTransportsEvent;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;
use Symfony\Component\Messenger\Exception\NoSenderForMessageException;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Stamp\OutboxStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\RedispatchStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Messenger\Stamp\TrustStamp;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;
use Symfony\Component\Messenger\Tests\Fixtures\ChildDummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessageInterface;
use Symfony\Component\Messenger\Transport\Sender\OutboxSender;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;

class SendMessageMiddlewareTest extends MiddlewareTestCase
{
    public function testItSendsTheMessageToAssignedSender()
    {
        $message = new DummyMessage('Hey');
        $envelope = new Envelope($message);
        $sender = $this->createMock(SenderInterface::class);

        $sendersLocator = $this->createSendersLocator([DummyMessage::class => ['my_sender']], ['my_sender' => $sender]);
        $middleware = new SendMessageMiddleware($sendersLocator);

        $sender->expects($this->once())->method('send')->with($envelope->with(new SentStamp($sender::class, 'my_sender')))->willReturnArgument(0);

        $envelope = $middleware->handle($envelope, $this->getStackMock(false));

        $this->assertInstanceOf(SentStamp::class, $stamp = $envelope->last(SentStamp::class), 'it adds a sent stamp');
        $this->assertSame('my_sender', $stamp->getSenderAlias());
        $this->assertSame($sender::class, $stamp->getSenderClass());
    }

    public function testItSendsTheMessageToMultipleSenders()
    {
        $envelope = new Envelope(new DummyMessage('Hey'));
        $sender = $this->createMock(SenderInterface::class);
        $sender2 = $this->createMock(SenderInterface::class);

        $sendersLocator = $this->createSendersLocator([DummyMessage::class => ['foo', 'bar']], ['foo' => $sender, 'bar' => $sender2]);
        $middleware = new SendMessageMiddleware($sendersLocator);

        $sender->expects($this->once())
            ->method('send')
            ->with($this->callback(static function (Envelope $envelope) {
                $lastSentStamp = $envelope->last(SentStamp::class);

                // last SentStamp should be the "foo" alias
                return null !== $lastSentStamp && 'foo' === $lastSentStamp->getSenderAlias();
            }))
            ->willReturnArgument(0);
        $sender2->expects($this->once())
            ->method('send')
            ->with($this->callback(static function (Envelope $envelope) {
                $lastSentStamp = $envelope->last(SentStamp::class);

                // last SentStamp should be the "bar" alias
                return null !== $lastSentStamp && 'bar' === $lastSentStamp->getSenderAlias();
            }))
            ->willReturnArgument(0);

        $envelope = $middleware->handle($envelope, $this->getStackMock(false));

        $sentStamps = $envelope->all(SentStamp::class);
        $this->assertCount(2, $sentStamps);
    }

    public function testItSendsTheMessageToAssignedSenderWithPreWrappedMessage()
    {
        $envelope = new Envelope(new ChildDummyMessage('Hey'));
        $sender = $this->createMock(SenderInterface::class);

        $sendersLocator = $this->createSendersLocator([DummyMessage::class => ['foo_sender']], ['foo_sender' => $sender]);
        $middleware = new SendMessageMiddleware($sendersLocator);

        $sender->expects($this->once())->method('send')->with($envelope->with(new SentStamp($sender::class, 'foo_sender')))->willReturn($envelope);

        $middleware->handle($envelope, $this->getStackMock(false));
    }

    public function testItSendsTheMessageBasedOnTheMessageParentClass()
    {
        $message = new ChildDummyMessage('Hey');
        $envelope = new Envelope($message);
        $sender = $this->createMock(SenderInterface::class);

        $sendersLocator = $this->createSendersLocator([DummyMessage::class => ['foo_sender']], ['foo_sender' => $sender]);
        $middleware = new SendMessageMiddleware($sendersLocator);

        $sender->expects($this->once())->method('send')->with($envelope->with(new SentStamp($sender::class, 'foo_sender')))->willReturn($envelope);

        $middleware->handle($envelope, $this->getStackMock(false));
    }

    public function testItSendsTheMessageBasedOnTheMessageInterface()
    {
        $message = new DummyMessage('Hey');
        $envelope = new Envelope($message);
        $sender = $this->createMock(SenderInterface::class);

        $sendersLocator = $this->createSendersLocator([DummyMessageInterface::class => ['foo_sender']], ['foo_sender' => $sender]);
        $middleware = new SendMessageMiddleware($sendersLocator);

        $sender->expects($this->once())->method('send')->with($envelope->with(new SentStamp($sender::class, 'foo_sender')))->willReturn($envelope);

        $middleware->handle($envelope, $this->getStackMock(false));
    }

    public function testItSendsTheMessageBasedOnWildcard()
    {
        $message = new DummyMessage('Hey');
        $envelope = new Envelope($message);
        $sender = $this->createMock(SenderInterface::class);

        $sendersLocator = $this->createSendersLocator(['*' => ['foo_sender']], ['foo_sender' => $sender]);
        $middleware = new SendMessageMiddleware($sendersLocator);

        $sender->expects($this->once())->method('send')->with($envelope->with(new SentStamp($sender::class, 'foo_sender')))->willReturn($envelope);

        $middleware->handle($envelope, $this->getStackMock(false));
    }

    public function testItCallsTheNextMiddlewareWhenNoSenderForThisMessage()
    {
        $message = new DummyMessage('Hey');
        $envelope = new Envelope($message);

        $middleware = new SendMessageMiddleware($this->createSendersLocator([], []));

        $middleware->handle($envelope, $this->getStackMock());
    }

    public function testItSkipsReceivedMessages()
    {
        $envelope = (new Envelope(new DummyMessage('Hey')))->with(new ReceivedStamp('transport'));

        $sender = $this->createMock(SenderInterface::class);

        $sendersLocator = $this->createSendersLocator(['*' => ['foo']], ['foo' => $sender]);
        $middleware = new SendMessageMiddleware($sendersLocator);

        $sender->expects($this->never())->method('send');

        $envelope = $middleware->handle($envelope, $this->getStackMock());

        $this->assertNull($envelope->last(SentStamp::class), 'it does not add sent stamp for received messages');
    }

    public function testItForwardsAReceivedMessageCarryingAnOutboxStamp()
    {
        $envelope = (new Envelope(new DummyMessage('Hey')))->with(new ReceivedStamp('outbox'), new OutboxStamp('orders'));
        $target = $this->createMock(SenderInterface::class);
        $routed = $this->createMock(SenderInterface::class);

        $sendersLocator = $this->createSendersLocator([DummyMessage::class => ['routed']], ['orders' => new OutboxSender($target, $this->createStub(SenderInterface::class), 'orders', 'outbox'), 'routed' => $routed]);
        $middleware = new SendMessageMiddleware($sendersLocator);

        $target->expects($this->once())->method('send')->with($envelope->withoutAll(OutboxStamp::class)->with(new SentStamp(OutboxSender::class, 'orders')))->willReturnArgument(0);
        $routed->expects($this->never())->method('send');

        $envelope = $middleware->handle($envelope, $this->getStackMock(false));

        $this->assertSame('orders', $envelope->last(SentStamp::class)?->getSenderAlias());
    }

    public function testItDispatchesTheEventsWhenForwardingFromTheOutbox()
    {
        $envelope = (new Envelope(new DummyMessage('Hey')))->with(new ReceivedStamp('outbox'), new OutboxStamp('orders'));
        $target = $this->createMock(SenderInterface::class);
        $target->expects($this->once())->method('send')->willReturnArgument(0);
        $sender = new OutboxSender($target, $this->createStub(SenderInterface::class), 'orders', 'outbox');

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $expectedEvents = [
            new SendMessageToTransportsEvent($envelope, $senders = ['orders' => $sender]),
            new MessageSentToTransportsEvent($envelope->withoutAll(OutboxStamp::class)->with(new SentStamp(OutboxSender::class, 'orders')), $senders),
        ];
        $dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function (object $event) use (&$expectedEvents) {
                $expectedEvent = array_shift($expectedEvents);

                $this->assertEquals($expectedEvent, $event);
            });

        $middleware = new SendMessageMiddleware($this->createSendersLocator([], ['orders' => $sender]), $dispatcher);

        $middleware->handle($envelope, $this->getStackMock(false));
    }

    public function testTheOutboxStampIsRestoredWhenAListenerRemovesIt()
    {
        $envelope = (new Envelope(new DummyMessage('Hey')))->with(new ReceivedStamp('outbox'), new OutboxStamp('orders'));
        $target = $this->createMock(SenderInterface::class);
        $target->expects($this->once())->method('send')->willReturnArgument(0);
        $outbox = $this->createMock(SenderInterface::class);
        $outbox->expects($this->never())->method('send');

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(SendMessageToTransportsEvent::class, static function (SendMessageToTransportsEvent $event) {
            $event->setEnvelope($event->getEnvelope()->withoutAll(OutboxStamp::class));
        });

        $middleware = new SendMessageMiddleware($this->createSendersLocator([], ['orders' => new OutboxSender($target, $outbox, 'orders', 'outbox')]), $dispatcher);

        $middleware->handle($envelope, $this->getStackMock(false));
    }

    public function testItHandlesAMessageWithAnOutboxStampReceivedFromAnotherTransportThanTheOutbox()
    {
        $envelope = (new Envelope(new DummyMessage('Hey')))->with(new ReceivedStamp('external'), new OutboxStamp('orders'));
        $target = $this->createMock(SenderInterface::class);
        $target->expects($this->never())->method('send');
        $sender = new OutboxSender($target, $this->createStub(SenderInterface::class), 'orders', 'outbox');

        $middleware = new SendMessageMiddleware($this->createSendersLocator([], ['orders' => $sender]));

        $envelope = $middleware->handle($envelope, $this->getStackMock());

        $this->assertNull($envelope->last(SentStamp::class));
    }

    public function testItHandlesAMessageWithAnOutboxStampNamingATransportWithoutOutbox()
    {
        $envelope = (new Envelope(new DummyMessage('Hey')))->with(new ReceivedStamp('outbox'), new OutboxStamp('orders'));
        $target = $this->createMock(SenderInterface::class);
        $target->expects($this->never())->method('send');

        $middleware = new SendMessageMiddleware($this->createSendersLocator([], ['orders' => $target]));

        $envelope = $middleware->handle($envelope, $this->getStackMock());

        $this->assertNull($envelope->last(SentStamp::class));
    }

    #[DataProvider('provideMessagesReceivedFromTheFailureTransportOfTheOutbox')]
    public function testItForwardsFromTheFailureTransportOfTheOutboxWhatTheOutboxFailedToForward(?string $originalReceiverName, bool $forwarded)
    {
        $envelope = (new Envelope(new DummyMessage('Hey')))->with(new ReceivedStamp('failed'), new OutboxStamp('orders'));
        if (null !== $originalReceiverName) {
            $envelope = $envelope->with(new SentToFailureTransportStamp($originalReceiverName));
        }
        $target = $this->createMock(SenderInterface::class);
        $target->expects($forwarded ? $this->once() : $this->never())->method('send')->willReturnArgument(0);
        $sender = new OutboxSender($target, $this->createStub(SenderInterface::class), 'orders', 'outbox', 'failed');

        $middleware = new SendMessageMiddleware($this->createSendersLocator([], ['orders' => $sender]));

        $middleware->handle($envelope, $this->getStackMock(!$forwarded));
    }

    public static function provideMessagesReceivedFromTheFailureTransportOfTheOutbox(): iterable
    {
        yield 'failed in the outbox' => ['outbox', true];
        yield 'failed elsewhere' => ['external', false];
        yield 'never failed' => [null, false];
    }

    public function testItHandlesAMessageReceivedFromTheTargetOfItsOutboxStamp()
    {
        $envelope = (new Envelope(new DummyMessage('Hey')))->with(new ReceivedStamp('orders'), new OutboxStamp('orders'));
        $target = $this->createMock(SenderInterface::class);
        $target->expects($this->never())->method('send');

        $middleware = new SendMessageMiddleware($this->createSendersLocator([], ['orders' => $target]));

        $envelope = $middleware->handle($envelope, $this->getStackMock());

        $this->assertNull($envelope->last(SentStamp::class));
    }

    public function testItSendsAReceivedMessageCarryingARedispatchStampToItsTransports()
    {
        $envelope = (new Envelope(new DummyMessage('Hey')))->with(new ReceivedStamp('scheduler_default'), TrustStamp::trusted(), new RedispatchStamp(), new TransportNamesStamp(['orders']));
        $target = $this->createMock(SenderInterface::class);
        $routed = $this->createMock(SenderInterface::class);

        $sendersLocator = $this->createSendersLocator([DummyMessage::class => ['routed']], ['orders' => $target, 'routed' => $routed]);
        $middleware = new SendMessageMiddleware($sendersLocator);

        $target->expects($this->once())->method('send')->with($envelope->withoutAll(RedispatchStamp::class)->with(new SentStamp($target::class, 'orders')))->willReturnArgument(0);
        $routed->expects($this->never())->method('send');

        $envelope = $middleware->handle($envelope, $this->getStackMock(false));

        $this->assertSame('orders', $envelope->last(SentStamp::class)?->getSenderAlias());
        $this->assertNull($envelope->last(RedispatchStamp::class));
    }

    public function testItSendsAReceivedMessageCarryingARedispatchStampToItsConfiguredSenders()
    {
        $envelope = (new Envelope(new DummyMessage('Hey')))->with(new ReceivedStamp('scheduler_default'), TrustStamp::trusted(), new RedispatchStamp());
        $routed = $this->createMock(SenderInterface::class);

        $middleware = new SendMessageMiddleware($this->createSendersLocator([DummyMessage::class => ['routed']], ['routed' => $routed]));

        $routed->expects($this->once())->method('send')->with($envelope->withoutAll(RedispatchStamp::class)->with(new SentStamp($routed::class, 'routed')))->willReturnArgument(0);

        $middleware->handle($envelope, $this->getStackMock(false));
    }

    public function testItHandlesAReceivedMessageCarryingARedispatchStampWhenItHasNoSender()
    {
        $envelope = (new Envelope(new DummyMessage('Hey')))->with(new ReceivedStamp('scheduler_default'), TrustStamp::trusted(), new RedispatchStamp());

        $middleware = new SendMessageMiddleware($this->createSendersLocator([], []));

        $envelope = $middleware->handle($envelope, $this->getStackMock());

        $this->assertNull($envelope->last(SentStamp::class));
    }

    #[DataProvider('provideUntrustedEnvelopesCarryingARedispatchStamp')]
    public function testItHandlesAReceivedMessageCarryingARedispatchStampWhenItIsNotTrusted(Envelope $envelope)
    {
        $routed = $this->createMock(SenderInterface::class);
        $routed->expects($this->never())->method('send');

        $middleware = new SendMessageMiddleware($this->createSendersLocator([DummyMessage::class => ['routed']], ['routed' => $routed]));

        $middleware->handle($envelope, $this->getStackMock());
    }

    public static function provideUntrustedEnvelopesCarryingARedispatchStamp(): iterable
    {
        yield 'without trust stamp' => [new Envelope(new DummyMessage('Hey'), [new ReceivedStamp('async'), new RedispatchStamp()])];
        yield 'untrusted' => [new Envelope(new DummyMessage('Hey'), [new ReceivedStamp('async'), TrustStamp::untrusted(), new RedispatchStamp()])];
        yield 'with a trust stamp that this process did not create' => [new Envelope(new DummyMessage('Hey'), [new ReceivedStamp('async'), unserialize(serialize(TrustStamp::trusted())), new RedispatchStamp()])];
    }

    public function testItDispatchesTheEventOneTime()
    {
        $envelope = new Envelope(new DummyMessage('original envelope'));

        $sender1 = $this->createMock(SenderInterface::class);
        $sender2 = $this->createMock(SenderInterface::class);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $expectedEvents = [
            new SendMessageToTransportsEvent($envelope, $senders = ['foo' => $sender1, 'bar' => $sender2]),
            new MessageSentToTransportsEvent($envelope, $senders),
        ];
        $dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function (object $event) use (&$expectedEvents) {
                $expectedEvent = array_shift($expectedEvents);

                $this->assertEquals($expectedEvent, $event);
            });

        $sendersLocator = $this->createSendersLocator([DummyMessage::class => ['foo', 'bar']], ['foo' => $sender1, 'bar' => $sender2]);
        $middleware = new SendMessageMiddleware($sendersLocator, $dispatcher);

        $sender1->expects($this->once())->method('send')->willReturn($envelope);
        $sender2->expects($this->once())->method('send')->willReturn($envelope);

        $middleware->handle($envelope, $this->getStackMock(false));
    }

    public function testItDoesNotDispatchWithNoSenders()
    {
        $envelope = new Envelope(new DummyMessage('original envelope'));

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->never())->method('dispatch');

        $middleware = new SendMessageMiddleware($this->createSendersLocator([], []), $dispatcher);

        $middleware->handle($envelope, $this->getStackMock());
    }

    public function testThrowsNoRoutingException()
    {
        $envelope = new Envelope(new DummyMessage('original envelope'));

        $sendersLocator = $this->createSendersLocator([DummyMessage::class => []], []);

        $this->expectException(NoSenderForMessageException::class);
        $this->expectExceptionMessage('No sender for message "Symfony\Component\Messenger\Tests\Fixtures\DummyMessage"');

        $middleware = new SendMessageMiddleware($sendersLocator, new EventDispatcher(), false);
        $middleware->handle($envelope, $this->getStackMock(false));
    }

    public function testAllowNoRouting()
    {
        $envelope = new Envelope(new DummyMessage('original envelope'));

        $sender = $this->createMock(SenderInterface::class);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $expectedEvents = [
            new SendMessageToTransportsEvent($envelope, $senders = ['foo' => $sender]),
            new MessageSentToTransportsEvent($envelope, $senders),
        ];
        $dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function (object $event) use (&$expectedEvents) {
                $expectedEvent = array_shift($expectedEvents);

                $this->assertEquals($expectedEvent, $event);
            });

        $sendersLocator = $this->createSendersLocator([DummyMessage::class => ['foo']], ['foo' => $sender]);
        $middleware = new SendMessageMiddleware($sendersLocator, $dispatcher);

        $sender->expects($this->once())->method('send')->willReturn($envelope);

        $middleware->handle($envelope, $this->getStackMock(false));
    }

    private function createSendersLocator(array $sendersMap, array $senders): SendersLocator
    {
        $container = new Container();

        foreach ($senders as $id => $sender) {
            $container->set($id, $sender);
        }

        return new SendersLocator($sendersMap, $container);
    }
}
