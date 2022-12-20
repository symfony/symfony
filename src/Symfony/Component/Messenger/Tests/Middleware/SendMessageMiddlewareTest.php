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

use Psr\Container\ContainerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;
use Symfony\Component\Messenger\Tests\Fixtures\ChildDummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessageInterface;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class SendMessageMiddlewareTest extends MiddlewareTestCase
{
    public function testItSendsTheMessageToAssignedSender()
    {
        $message = new DummyMessage('Hey');
        $envelope = new Envelope($message);
        $sender = self::createMock(SenderInterface::class);

        $sendersLocator = $this->createSendersLocator([DummyMessage::class => ['my_sender']], ['my_sender' => $sender]);
        $middleware = new SendMessageMiddleware($sendersLocator);

        $sender->expects(self::once())->method('send')->with($envelope->with(new SentStamp(\get_class($sender), 'my_sender')))->willReturnArgument(0);

        $envelope = $middleware->handle($envelope, $this->getStackMock(false));

        /* @var SentStamp $stamp */
        self::assertInstanceOf(SentStamp::class, $stamp = $envelope->last(SentStamp::class), 'it adds a sent stamp');
        self::assertSame('my_sender', $stamp->getSenderAlias());
        self::assertStringMatchesFormat('Mock_SenderInterface_%s', $stamp->getSenderClass());
    }

    public function testItSendsTheMessageToMultipleSenders()
    {
        $envelope = new Envelope(new DummyMessage('Hey'));
        $sender = self::createMock(SenderInterface::class);
        $sender2 = self::createMock(SenderInterface::class);

        $sendersLocator = $this->createSendersLocator([DummyMessage::class => ['foo', 'bar']], ['foo' => $sender, 'bar' => $sender2]);
        $middleware = new SendMessageMiddleware($sendersLocator);

        $sender->expects(self::once())
            ->method('send')
            ->with(self::callback(function (Envelope $envelope) {
                /** @var SentStamp|null $lastSentStamp */
                $lastSentStamp = $envelope->last(SentStamp::class);

                // last SentStamp should be the "foo" alias
                return null !== $lastSentStamp && 'foo' === $lastSentStamp->getSenderAlias();
            }))
            ->willReturnArgument(0);
        $sender2->expects(self::once())
            ->method('send')
            ->with(self::callback(function (Envelope $envelope) {
                /** @var SentStamp|null $lastSentStamp */
                $lastSentStamp = $envelope->last(SentStamp::class);

                // last SentStamp should be the "bar" alias
                return null !== $lastSentStamp && 'bar' === $lastSentStamp->getSenderAlias();
            }))
            ->willReturnArgument(0);

        $envelope = $middleware->handle($envelope, $this->getStackMock(false));

        /** @var SentStamp[] $sentStamps */
        $sentStamps = $envelope->all(SentStamp::class);
        self::assertCount(2, $sentStamps);
    }

    public function testItSendsTheMessageToAssignedSenderWithPreWrappedMessage()
    {
        $envelope = new Envelope(new ChildDummyMessage('Hey'));
        $sender = self::createMock(SenderInterface::class);

        $sendersLocator = $this->createSendersLocator([DummyMessage::class => ['foo_sender']], ['foo_sender' => $sender]);
        $middleware = new SendMessageMiddleware($sendersLocator);

        $sender->expects(self::once())->method('send')->with($envelope->with(new SentStamp(\get_class($sender), 'foo_sender')))->willReturn($envelope);

        $middleware->handle($envelope, $this->getStackMock(false));
    }

    public function testItSendsTheMessageBasedOnTheMessageParentClass()
    {
        $message = new ChildDummyMessage('Hey');
        $envelope = new Envelope($message);
        $sender = self::createMock(SenderInterface::class);

        $sendersLocator = $this->createSendersLocator([DummyMessage::class => ['foo_sender']], ['foo_sender' => $sender]);
        $middleware = new SendMessageMiddleware($sendersLocator);

        $sender->expects(self::once())->method('send')->with($envelope->with(new SentStamp(\get_class($sender), 'foo_sender')))->willReturn($envelope);

        $middleware->handle($envelope, $this->getStackMock(false));
    }

    public function testItSendsTheMessageBasedOnTheMessageInterface()
    {
        $message = new DummyMessage('Hey');
        $envelope = new Envelope($message);
        $sender = self::createMock(SenderInterface::class);

        $sendersLocator = $this->createSendersLocator([DummyMessageInterface::class => ['foo_sender']], ['foo_sender' => $sender]);
        $middleware = new SendMessageMiddleware($sendersLocator);

        $sender->expects(self::once())->method('send')->with($envelope->with(new SentStamp(\get_class($sender), 'foo_sender')))->willReturn($envelope);

        $middleware->handle($envelope, $this->getStackMock(false));
    }

    public function testItSendsTheMessageBasedOnWildcard()
    {
        $message = new DummyMessage('Hey');
        $envelope = new Envelope($message);
        $sender = self::createMock(SenderInterface::class);

        $sendersLocator = $this->createSendersLocator(['*' => ['foo_sender']], ['foo_sender' => $sender]);
        $middleware = new SendMessageMiddleware($sendersLocator);

        $sender->expects(self::once())->method('send')->with($envelope->with(new SentStamp(\get_class($sender), 'foo_sender')))->willReturn($envelope);

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

        $sender = self::createMock(SenderInterface::class);

        $sendersLocator = $this->createSendersLocator(['*' => ['foo']], ['foo' => $sender]);
        $middleware = new SendMessageMiddleware($sendersLocator);

        $sender->expects(self::never())->method('send');

        $envelope = $middleware->handle($envelope, $this->getStackMock());

        self::assertNull($envelope->last(SentStamp::class), 'it does not add sent stamp for received messages');
    }

    public function testItDispatchesTheEventOneTime()
    {
        $envelope = new Envelope(new DummyMessage('original envelope'));

        $dispatcher = self::createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::once())
            ->method('dispatch')
            ->with(new SendMessageToTransportsEvent($envelope));

        $sender1 = self::createMock(SenderInterface::class);
        $sender2 = self::createMock(SenderInterface::class);

        $sendersLocator = $this->createSendersLocator([DummyMessage::class => ['foo', 'bar']], ['foo' => $sender1, 'bar' => $sender2]);
        $middleware = new SendMessageMiddleware($sendersLocator, $dispatcher);

        $sender1->expects(self::once())->method('send')->willReturn($envelope);
        $sender2->expects(self::once())->method('send')->willReturn($envelope);

        $middleware->handle($envelope, $this->getStackMock(false));
    }

    public function testItDoesNotDispatchWithNoSenders()
    {
        $envelope = new Envelope(new DummyMessage('original envelope'));

        $dispatcher = self::createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::never())->method('dispatch');

        $middleware = new SendMessageMiddleware($this->createSendersLocator([], []), $dispatcher);

        $middleware->handle($envelope, $this->getStackMock());
    }

    private function createSendersLocator(array $sendersMap, array $senders): SendersLocator
    {
        $container = self::createMock(ContainerInterface::class);
        $container->expects(self::any())
            ->method('has')
            ->willReturnCallback(function ($id) use ($senders) {
                return isset($senders[$id]);
            });
        $container->expects(self::any())
            ->method('get')
            ->willReturnCallback(function ($id) use ($senders) {
                return $senders[$id];
            });

        return new SendersLocator($sendersMap, $container);
    }
}
