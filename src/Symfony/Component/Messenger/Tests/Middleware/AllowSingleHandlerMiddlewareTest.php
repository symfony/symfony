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
use Symfony\Component\Messenger\Handler\ChainHandler;
use Symfony\Component\Messenger\Handler\Locator\HandlerLocator;
use Symfony\Component\Messenger\Middleware\AllowSingleHandlerMiddleware;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

class AllowSingleHandlerMiddlewareTest extends TestCase
{
    public function testItCallsNextMiddlewareAndReturnsItsResult(): void
    {
        $message = new DummyMessage('Hey');

        $handler = $this->createPartialMock(\stdClass::class, array('__invoke'));
        $handler->method('__invoke')->willReturn('Hello');

        $next = $this->createPartialMock(\stdClass::class, array('__invoke'));
        $next->expects($this->once())->method('__invoke')->with($message)->willReturn('Foo');

        $middleware = new AllowSingleHandlerMiddleware(new HandlerLocator(array(
            DummyMessage::class => $handler,
        )));

        $this->assertSame('Foo', $middleware->handle($message, $next));
    }

    /**
     * @expectedException \Symfony\Component\Messenger\Exception\MoreThanOneHandlerForMessageException
     * @expectedExceptionMessage More than one handler for message "Symfony\Component\Messenger\Tests\Fixtures\DummyMessage".
     */
    public function testItThrowsAnExceptionIfHandlerIsChainHandler(): void
    {
        $next = $this->createPartialMock(\stdClass::class, array('__invoke'));

        $middleware = new AllowSingleHandlerMiddleware(new HandlerLocator(array(
            DummyMessage::class => new ChainHandler(array(new \stdClass(), new \stdClass())),
        )));

        $middleware->handle(new DummyMessage('Hey'), $next);
    }
}
