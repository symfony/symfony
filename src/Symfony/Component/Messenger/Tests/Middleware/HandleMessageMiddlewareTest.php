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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Handler\Locator\HandlerLocator;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

class HandleMessageMiddlewareTest extends TestCase
{
    public function testItCallsTheHandlerAndNextMiddleware()
    {
        $message = new DummyMessage('Hey');

        $handler = $this->createPartialMock(\stdClass::class, array('__invoke'));
        $handler->method('__invoke')->willReturn('Hello');

        $next = $this->createPartialMock(\stdClass::class, array('__invoke'));

        $middleware = new HandleMessageMiddleware(new HandlerLocator(array(
            DummyMessage::class => $handler,
        )));

        $handler->expects($this->once())->method('__invoke')->with($message);
        $next->expects($this->once())->method('__invoke')->with($message);

        $middleware->handle($message, $next);
    }
}
