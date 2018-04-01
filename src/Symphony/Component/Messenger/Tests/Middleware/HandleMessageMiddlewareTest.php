<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Messenger\Tests\Middleware;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Messenger\HandlerLocator;
use Symphony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symphony\Component\Messenger\Tests\Fixtures\DummyMessage;

class HandleMessageMiddlewareTest extends TestCase
{
    public function testItCallsTheHandlerAndNextMiddleware()
    {
        $message = new DummyMessage('Hey');

        $handler = $this->createPartialMock(\stdClass::class, ['__invoke']);
        $handler->method('__invoke')->willReturn('Hello');

        $next = $this->createPartialMock(\stdClass::class, ['__invoke']);

        $middleware = new HandleMessageMiddleware(new HandlerLocator(array(
            DummyMessage::class => $handler,
        )));

        $handler->expects($this->once())->method('__invoke')->with($message);
        $next->expects($this->once())->method('__invoke')->with($message);

        $middleware->handle($message, $next);
    }
}
