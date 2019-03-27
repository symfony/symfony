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

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
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
        $sender = $this->getMockBuilder(SenderInterface::class)->getMock();

        $middleware = new SendMessageMiddleware(new SendersLocator([DummyMessage::class => [$sender]]));

        $sender->expects($this->once())->method('send')->with($envelope->with(new SentStamp(\get_class($sender))))->will($this->returnArgument(0));

        $envelope = $middleware->handle($envelope, $this->getStackMock(false));

        /* @var SentStamp $stamp */
        $this->assertInstanceOf(SentStamp::class, $stamp = $envelope->last(SentStamp::class), 'it adds a sent stamp');
        $this->assertNull($stamp->getSenderAlias());
        $this->assertStringMatchesFormat('Mock_SenderInterface_%s', $stamp->getSenderClass());
    }

    public function testItSendsTheMessageToMultipleSenders()
    {
        $envelope = new Envelope(new DummyMessage('Hey'));
        $sender = $this->getMockBuilder(SenderInterface::class)->getMock();
        $sender2 = $this->getMockBuilder(SenderInterface::class)->getMock();

        $middleware = new SendMessageMiddleware(new SendersLocator([
            DummyMessage::class => ['foo' => $sender, 'bar' => $sender2],
        ]));

        $sender->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Envelope $envelope) {
                /** @var SentStamp|null $lastSentStamp */
                $lastSentStamp = $envelope->last(SentStamp::class);

                // last SentStamp should be the "foo" alias
                return null !== $lastSentStamp && 'foo' === $lastSentStamp->getSenderAlias();
            }))
            ->will($this->returnArgument(0));
        $sender2->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Envelope $envelope) {
                /** @var SentStamp|null $lastSentStamp */
                $lastSentStamp = $envelope->last(SentStamp::class);

                // last SentStamp should be the "bar" alias
                return null !== $lastSentStamp && 'bar' === $lastSentStamp->getSenderAlias();
            }))
            ->will($this->returnArgument(0));

        $envelope = $middleware->handle($envelope, $this->getStackMock(false));

        /** @var SentStamp[] $sentStamps */
        $sentStamps = $envelope->all(SentStamp::class);
        $this->assertCount(2, $sentStamps);
    }

    public function testItSendsToOnlyOneSenderOnRedelivery()
    {
        $envelope = new Envelope(new DummyMessage('Hey'), new RedeliveryStamp(5, 'bar'));
        $sender = $this->getMockBuilder(SenderInterface::class)->getMock();
        $sender2 = $this->getMockBuilder(SenderInterface::class)->getMock();

        $middleware = new SendMessageMiddleware(new SendersLocator([
            DummyMessage::class => ['foo' => $sender, 'bar' => $sender2],
        ], [
            // normally, this class sends and handles (but not on retry)
            DummyMessage::class => true,
        ]));

        $sender->expects($this->never())
            ->method('send')
        ;
        $sender2->expects($this->once())
            ->method('send')
            ->will($this->returnArgument(0));

        $mockStack = $this->getStackMock(false); // false because next should not be called
        $envelope = $middleware->handle($envelope, $mockStack);
        $this->assertCount(1, $envelope->all(SentStamp::class));
    }

    public function testItSendsTheMessageToAssignedSenderWithPreWrappedMessage()
    {
        $envelope = new Envelope(new ChildDummyMessage('Hey'));
        $sender = $this->getMockBuilder(SenderInterface::class)->getMock();

        $middleware = new SendMessageMiddleware(new SendersLocator([DummyMessage::class => [$sender]]));

        $sender->expects($this->once())->method('send')->with($envelope->with(new SentStamp(\get_class($sender))))->willReturn($envelope);

        $middleware->handle($envelope, $this->getStackMock(false));
    }

    public function testItAlsoCallsTheNextMiddlewareBasedOnTheMessageClass()
    {
        $message = new DummyMessage('Hey');
        $envelope = new Envelope($message);
        $sender = $this->getMockBuilder(SenderInterface::class)->getMock();

        $middleware = new SendMessageMiddleware(new SendersLocator(['*' => [$sender]], [
            DummyMessage::class => true,
        ]));

        $sender->expects($this->once())->method('send')->with($envelope->with(new SentStamp(\get_class($sender))))->willReturn($envelope);

        $middleware->handle($envelope, $this->getStackMock());
    }

    public function testItAlsoCallsTheNextMiddlewareBasedOnTheMessageParentClass()
    {
        $message = new ChildDummyMessage('Hey');
        $envelope = new Envelope($message);
        $sender = $this->getMockBuilder(SenderInterface::class)->getMock();

        $middleware = new SendMessageMiddleware(new SendersLocator(['*' => [$sender]], [
            DummyMessage::class => true,
        ]));

        $sender->expects($this->once())->method('send')->with($envelope->with(new SentStamp(\get_class($sender))))->willReturn($envelope);

        $middleware->handle($envelope, $this->getStackMock());
    }

    public function testItAlsoCallsTheNextMiddlewareBasedOnTheMessageInterface()
    {
        $message = new DummyMessage('Hey');
        $envelope = new Envelope($message);
        $sender = $this->getMockBuilder(SenderInterface::class)->getMock();

        $middleware = new SendMessageMiddleware(new SendersLocator(['*' => [$sender]], [
            DummyMessageInterface::class => true,
        ]));

        $sender->expects($this->once())->method('send')->with($envelope->with(new SentStamp(\get_class($sender))))->willReturn($envelope);

        $middleware->handle($envelope, $this->getStackMock());
    }

    public function testItAlsoCallsTheNextMiddlewareBasedOnWildcard()
    {
        $message = new DummyMessage('Hey');
        $envelope = new Envelope($message);
        $sender = $this->getMockBuilder(SenderInterface::class)->getMock();

        $middleware = new SendMessageMiddleware(new SendersLocator(['*' => [$sender]], [
            '*' => true,
        ]));

        $sender->expects($this->once())->method('send')->with($envelope->with(new SentStamp(\get_class($sender))))->willReturn($envelope);

        $middleware->handle($envelope, $this->getStackMock());
    }

    public function testItCallsTheNextMiddlewareWhenNoSenderForThisMessage()
    {
        $message = new DummyMessage('Hey');
        $envelope = new Envelope($message);

        $middleware = new SendMessageMiddleware(new SendersLocator([]));

        $middleware->handle($envelope, $this->getStackMock());
    }

    public function testItSkipsReceivedMessages()
    {
        $envelope = (new Envelope(new DummyMessage('Hey')))->with(new ReceivedStamp());

        $sender = $this->getMockBuilder(SenderInterface::class)->getMock();

        $middleware = new SendMessageMiddleware(new SendersLocator(['*' => [$sender]]));

        $sender->expects($this->never())->method('send');

        $envelope = $middleware->handle($envelope, $this->getStackMock());

        $this->assertNull($envelope->last(SentStamp::class), 'it does not add sent stamp for received messages');
    }

    public function testItDispatchesTheEventOneTime()
    {
        $envelope = new Envelope(new DummyMessage('original envelope'));

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(new SendMessageToTransportsEvent($envelope));

        $sender1 = $this->getMockBuilder(SenderInterface::class)->getMock();
        $sender2 = $this->getMockBuilder(SenderInterface::class)->getMock();

        $middleware = new SendMessageMiddleware(new SendersLocator([DummyMessage::class => [$sender1, $sender2]]), $dispatcher);

        $sender1->expects($this->once())->method('send')->willReturn($envelope);
        $sender2->expects($this->once())->method('send')->willReturn($envelope);

        $middleware->handle($envelope, $this->getStackMock(false));
    }

    public function testItDoesNotDispatchWithNoSenders()
    {
        $envelope = new Envelope(new DummyMessage('original envelope'));

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->never())->method('dispatch');

        $middleware = new SendMessageMiddleware(new SendersLocator([]), $dispatcher);

        $middleware->handle($envelope, $this->getStackMock());
    }

    public function testItDoesNotDispatchOnRetry()
    {
        $envelope = new Envelope(new DummyMessage('original envelope'));
        $envelope = $envelope->with(new RedeliveryStamp(3, 'foo_sender'));

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->never())->method('dispatch');

        $sender = $this->getMockBuilder(SenderInterface::class)->getMock();

        $middleware = new SendMessageMiddleware(new SendersLocator([DummyMessage::class => [$sender]]), $dispatcher);

        $middleware->handle($envelope, $this->getStackMock(false));
    }
}
