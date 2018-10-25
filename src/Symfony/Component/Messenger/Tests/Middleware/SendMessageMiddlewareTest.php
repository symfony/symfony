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
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Tests\Fixtures\ChildDummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessageInterface;
use Symfony\Component\Messenger\Transport\Sender\Locator\SenderLocatorInterface;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

class SendMessageMiddlewareTest extends MiddlewareTestCase
{
    public function testItSendsTheMessageToAssignedSender()
    {
        $message = new DummyMessage('Hey');
        $envelope = new Envelope($message);
        $sender = $this->getMockBuilder(SenderInterface::class)->getMock();

        $middleware = new SendMessageMiddleware(new InMemorySenderLocator($sender));

        $sender->expects($this->once())->method('send')->with($envelope)->willReturn($envelope);

        $middleware->handle($envelope, $this->getStackMock(false));
    }

    public function testItSendsTheMessageToAssignedSenderWithPreWrappedMessage()
    {
        $envelope = new Envelope(new DummyMessage('Hey'));
        $sender = $this->getMockBuilder(SenderInterface::class)->getMock();

        $middleware = new SendMessageMiddleware(new InMemorySenderLocator($sender));

        $sender->expects($this->once())->method('send')->with($envelope)->willReturn($envelope);

        $middleware->handle($envelope, $this->getStackMock(false));
    }

    public function testItAlsoCallsTheNextMiddlewareBasedOnTheMessageClass()
    {
        $message = new DummyMessage('Hey');
        $envelope = new Envelope($message);
        $sender = $this->getMockBuilder(SenderInterface::class)->getMock();

        $middleware = new SendMessageMiddleware(new InMemorySenderLocator($sender), array(
            DummyMessage::class => true,
        ));

        $sender->expects($this->once())->method('send')->with($envelope)->willReturn($envelope);

        $middleware->handle($envelope, $this->getStackMock());
    }

    public function testItAlsoCallsTheNextMiddlewareBasedOnTheMessageParentClass()
    {
        $message = new ChildDummyMessage('Hey');
        $envelope = new Envelope($message);
        $sender = $this->getMockBuilder(SenderInterface::class)->getMock();

        $middleware = new SendMessageMiddleware(new InMemorySenderLocator($sender), array(
            DummyMessage::class => true,
        ));

        $sender->expects($this->once())->method('send')->with($envelope)->willReturn($envelope);

        $middleware->handle($envelope, $this->getStackMock());
    }

    public function testItAlsoCallsTheNextMiddlewareBasedOnTheMessageInterface()
    {
        $message = new DummyMessage('Hey');
        $envelope = new Envelope($message);
        $sender = $this->getMockBuilder(SenderInterface::class)->getMock();

        $middleware = new SendMessageMiddleware(new InMemorySenderLocator($sender), array(
            DummyMessageInterface::class => true,
        ));

        $sender->expects($this->once())->method('send')->with($envelope)->willReturn($envelope);

        $middleware->handle($envelope, $this->getStackMock());
    }

    public function testItAlsoCallsTheNextMiddlewareBasedOnWildcard()
    {
        $message = new DummyMessage('Hey');
        $envelope = new Envelope($message);
        $sender = $this->getMockBuilder(SenderInterface::class)->getMock();

        $middleware = new SendMessageMiddleware(new InMemorySenderLocator($sender), array(
            '*' => true,
        ));

        $sender->expects($this->once())->method('send')->with($envelope)->willReturn($envelope);

        $middleware->handle($envelope, $this->getStackMock());
    }

    public function testItCallsTheNextMiddlewareWhenNoSenderForThisMessage()
    {
        $message = new DummyMessage('Hey');
        $envelope = new Envelope($message);

        $middleware = new SendMessageMiddleware(new InMemorySenderLocator(null));
        $handler = $this->getMockBuilder(MiddlewareInterface::class)->getMock();

        $middleware->handle($envelope, $this->getStackMock());
    }

    public function testItSkipsReceivedMessages()
    {
        $envelope = (new Envelope(new DummyMessage('Hey')))->with(new ReceivedStamp());

        $sender = $this->getMockBuilder(SenderInterface::class)->getMock();

        $middleware = new SendMessageMiddleware(new InMemorySenderLocator($sender));

        $sender->expects($this->never())->method('send');

        $middleware->handle($envelope, $this->getStackMock());
    }
}

class InMemorySenderLocator implements SenderLocatorInterface
{
    private $sender;

    public function __construct(?SenderInterface $sender)
    {
        $this->sender = $sender;
    }

    public function getSender(Envelope $envelope): ?SenderInterface
    {
        return $this->sender;
    }
}
