<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Asynchronous\Middleware;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Asynchronous\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Asynchronous\Routing\SenderLocatorInterface;
use Symfony\Component\Messenger\Asynchronous\Transport\ReceivedMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Tests\Asynchronous\Routing\ChildDummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessageInterface;
use Symfony\Component\Messenger\Transport\SenderInterface;

class SendMessageMiddlewareTest extends TestCase
{
    public function testItSendsTheMessageToAssignedSender()
    {
        $message = new DummyMessage('Hey');
        $sender = $this->getMockBuilder(SenderInterface::class)->getMock();
        $next = $this->createPartialMock(\stdClass::class, array('__invoke'));

        $middleware = new SendMessageMiddleware(new InMemorySenderLocator($sender));

        $sender->expects($this->once())->method('send')->with(Envelope::wrap($message));
        $next->expects($this->never())->method($this->anything());

        $middleware->handle($message, $next);
    }

    public function testItSendsTheMessageToAssignedSenderWithPreWrappedMessage()
    {
        $envelope = Envelope::wrap(new DummyMessage('Hey'));
        $sender = $this->getMockBuilder(SenderInterface::class)->getMock();
        $next = $this->createPartialMock(\stdClass::class, array('__invoke'));

        $middleware = new SendMessageMiddleware(new InMemorySenderLocator($sender));

        $sender->expects($this->once())->method('send')->with($envelope);
        $next->expects($this->never())->method($this->anything());

        $middleware->handle($envelope, $next);
    }

    public function testItAlsoCallsTheNextMiddlewareBasedOnTheMessageClass()
    {
        $message = new DummyMessage('Hey');
        $sender = $this->getMockBuilder(SenderInterface::class)->getMock();
        $next = $this->createPartialMock(\stdClass::class, array('__invoke'));

        $middleware = new SendMessageMiddleware(new InMemorySenderLocator($sender), array(
            DummyMessage::class => true,
        ));

        $sender->expects($this->once())->method('send')->with(Envelope::wrap($message));
        $next->expects($this->once())->method($this->anything());

        $middleware->handle($message, $next);
    }

    public function testItAlsoCallsTheNextMiddlewareBasedOnTheMessageParentClass()
    {
        $message = new ChildDummyMessage('Hey');
        $sender = $this->getMockBuilder(SenderInterface::class)->getMock();
        $next = $this->createPartialMock(\stdClass::class, array('__invoke'));

        $middleware = new SendMessageMiddleware(new InMemorySenderLocator($sender), array(
            DummyMessage::class => true,
        ));

        $sender->expects($this->once())->method('send')->with(Envelope::wrap($message));
        $next->expects($this->once())->method($this->anything());

        $middleware->handle($message, $next);
    }

    public function testItAlsoCallsTheNextMiddlewareBasedOnTheMessageInterface()
    {
        $message = new DummyMessage('Hey');
        $sender = $this->getMockBuilder(SenderInterface::class)->getMock();
        $next = $this->createPartialMock(\stdClass::class, array('__invoke'));

        $middleware = new SendMessageMiddleware(new InMemorySenderLocator($sender), array(
            DummyMessageInterface::class => true,
        ));

        $sender->expects($this->once())->method('send')->with(Envelope::wrap($message));
        $next->expects($this->once())->method($this->anything());

        $middleware->handle($message, $next);
    }

    public function testItAlsoCallsTheNextMiddlewareBasedOnWildcard()
    {
        $message = new DummyMessage('Hey');
        $sender = $this->getMockBuilder(SenderInterface::class)->getMock();
        $next = $this->createPartialMock(\stdClass::class, array('__invoke'));

        $middleware = new SendMessageMiddleware(new InMemorySenderLocator($sender), array(
            '*' => true,
        ));

        $sender->expects($this->once())->method('send')->with(Envelope::wrap($message));
        $next->expects($this->once())->method($this->anything());

        $middleware->handle($message, $next);
    }

    public function testItCallsTheNextMiddlewareWhenNoSenderForThisMessage()
    {
        $message = new DummyMessage('Hey');
        $next = $this->createPartialMock(\stdClass::class, array('__invoke'));

        $middleware = new SendMessageMiddleware(new InMemorySenderLocator(null));

        $next->expects($this->once())->method($this->anything());

        $middleware->handle($message, $next);
    }

    public function testItSkipsReceivedMessages()
    {
        $envelope = Envelope::wrap(new DummyMessage('Hey'))->with(new ReceivedMessage());

        $sender = $this->getMockBuilder(SenderInterface::class)->getMock();
        $next = $this->createPartialMock(\stdClass::class, array('__invoke'));

        $middleware = new SendMessageMiddleware(new InMemorySenderLocator($sender));

        $sender->expects($this->never())->method('send');
        $next->expects($this->once())->method('__invoke')->with($envelope);

        $middleware->handle($envelope, $next);
    }
}

class InMemorySenderLocator implements SenderLocatorInterface
{
    private $sender;

    public function __construct(?SenderInterface $sender)
    {
        $this->sender = $sender;
    }

    public function getSenderForMessage($message): ?SenderInterface
    {
        return $this->sender;
    }
}
