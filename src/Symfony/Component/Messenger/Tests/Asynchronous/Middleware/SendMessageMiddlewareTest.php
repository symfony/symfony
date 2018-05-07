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
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\SenderInterface;

class SendMessageMiddlewareTest extends TestCase
{
    public function testItSendsTheMessageToAssignedSender()
    {
        $message = new DummyMessage('Hey');
        $sender = $this->getMockBuilder(SenderInterface::class)->getMock();
        $next = $this->createPartialMock(\stdClass::class, array('__invoke'));

        $middleware = new SendMessageMiddleware(new InMemorySenderLocator(array(
            $sender,
        )));

        $sender->expects($this->once())->method('send')->with($message);
        $next->expects($this->never())->method($this->anything());

        $middleware->handle($message, $next);
    }

    public function testItAlsoCallsTheNextMiddlewareIfASenderIsNull()
    {
        $message = new DummyMessage('Hey');
        $sender = $this->getMockBuilder(SenderInterface::class)->getMock();
        $next = $this->createPartialMock(\stdClass::class, array('__invoke'));

        $middleware = new SendMessageMiddleware(new InMemorySenderLocator(array(
            $sender,
            null,
        )));

        $sender->expects($this->once())->method('send')->with($message);
        $next->expects($this->once())->method($this->anything());

        $middleware->handle($message, $next);
    }

    public function testItCallsTheNextMiddlewareWhenNoSenderForThisMessage()
    {
        $message = new DummyMessage('Hey');
        $next = $this->createPartialMock(\stdClass::class, array('__invoke'));

        $middleware = new SendMessageMiddleware(new InMemorySenderLocator(array()));

        $next->expects($this->once())->method($this->anything());

        $middleware->handle($message, $next);
    }

    public function testItSkipsReceivedMessages()
    {
        $innerMessage = new DummyMessage('Hey');
        $message = new ReceivedMessage($innerMessage);

        $sender = $this->getMockBuilder(SenderInterface::class)->getMock();
        $next = $this->createPartialMock(\stdClass::class, array('__invoke'));

        $middleware = new SendMessageMiddleware(new InMemorySenderLocator(array(
            $sender,
        )));

        $sender->expects($this->never())->method('send');
        $next->expects($this->once())->method('__invoke')->with($innerMessage);

        $middleware->handle($message, $next);
    }
}

class InMemorySenderLocator implements SenderLocatorInterface
{
    private $senders;

    public function __construct(array $senders)
    {
        $this->senders = $senders;
    }

    public function getSendersForMessage($message): array
    {
        return $this->senders;
    }
}
