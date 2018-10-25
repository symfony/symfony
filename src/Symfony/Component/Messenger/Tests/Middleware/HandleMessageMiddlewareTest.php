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
use Symfony\Component\Messenger\Handler\Locator\HandlerLocator;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\StackMiddleware;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

class HandleMessageMiddlewareTest extends MiddlewareTestCase
{
    public function testItCallsTheHandlerAndNextMiddleware()
    {
        $message = new DummyMessage('Hey');
        $envelope = new Envelope($message);

        $handler = $this->createPartialMock(\stdClass::class, array('__invoke'));

        $middleware = new HandleMessageMiddleware(new HandlerLocator(array(
            DummyMessage::class => $handler,
        )));

        $handler->expects($this->once())->method('__invoke')->with($message);

        $middleware->handle($envelope, $this->getStackMock());
    }

    /**
     * @expectedException \Symfony\Component\Messenger\Exception\NoHandlerForMessageException
     * @expectedExceptionMessage No handler for message "Symfony\Component\Messenger\Tests\Fixtures\DummyMessage"
     */
    public function testThrowsNoHandlerException()
    {
        $middleware = new HandleMessageMiddleware(new HandlerLocator(array()));

        $middleware->handle(new Envelope(new DummyMessage('Hey')), new StackMiddleware());
    }

    public function testAllowNoHandlers()
    {
        $middleware = new HandleMessageMiddleware(new HandlerLocator(array()), true);

        $this->assertInstanceOf(Envelope::class, $middleware->handle(new Envelope(new DummyMessage('Hey')), new StackMiddleware()));
    }
}
