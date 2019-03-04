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
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;
use Symfony\Component\Messenger\Tests\Fixtures\ChildDummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessageInterface;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;

class SendMessageMiddlewareTest extends MiddlewareTestCase
{
    public function testItSendsTheMessageToAssignedSender()
    {
        $message = new DummyMessage('Hey');
        $envelope = new Envelope($message);
        $sender = $this->getMockBuilder(SenderInterface::class)->getMock();

        $middleware = new SendMessageMiddleware(new SendersLocator([DummyMessage::class => [$sender]]));

        $sender->expects($this->once())->method('send')->with($envelope)->willReturn($envelope);

        $envelope = $middleware->handle($envelope, $this->getStackMock(false));

        /* @var SentStamp $stamp */
        $this->assertInstanceOf(SentStamp::class, $stamp = $envelope->last(SentStamp::class), 'it adds a sent stamp');
        $this->assertNull($stamp->getSenderAlias());
        $this->assertStringMatchesFormat('Mock_SenderInterface_%s', $stamp->getSenderClass());
    }

    public function testItSendsTheMessageToAssignedSenderWithPreWrappedMessage()
    {
        $envelope = new Envelope(new ChildDummyMessage('Hey'));
        $sender = $this->getMockBuilder(SenderInterface::class)->getMock();

        $middleware = new SendMessageMiddleware(new SendersLocator([DummyMessage::class => [$sender]]));

        $sender->expects($this->once())->method('send')->with($envelope)->willReturn($envelope);

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

        $sender->expects($this->once())->method('send')->with($envelope)->willReturn($envelope);

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

        $sender->expects($this->once())->method('send')->with($envelope)->willReturn($envelope);

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

        $sender->expects($this->once())->method('send')->with($envelope)->willReturn($envelope);

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

        $sender->expects($this->once())->method('send')->with($envelope)->willReturn($envelope);

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
}
