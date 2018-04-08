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
use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;
use Symfony\Component\Messenger\Middleware\TolerateNoHandler;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

class TolerateNoHandlerTest extends TestCase
{
    public function testItCallsNextMiddlewareAndReturnsItsResult()
    {
        $message = new DummyMessage('Hey');

        $next = $this->createPartialMock(\stdClass::class, array('__invoke'));
        $next->expects($this->once())->method('__invoke')->with($message)->willReturn('Foo');

        $middleware = new TolerateNoHandler();
        $this->assertSame('Foo', $middleware->handle($message, $next));
    }

    public function testItCatchesTheNoHandlerException()
    {
        $next = $this->createPartialMock(\stdClass::class, array('__invoke'));
        $next->expects($this->once())->method('__invoke')->will($this->throwException(new NoHandlerForMessageException()));

        $middleware = new TolerateNoHandler();

        $this->assertNull($middleware->handle(new DummyMessage('Hey'), $next));
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Something went wrong.
     */
    public function testItDoesNotCatchOtherExceptions()
    {
        $next = $this->createPartialMock(\stdClass::class, array('__invoke'));
        $next->expects($this->once())->method('__invoke')->will($this->throwException(new \RuntimeException('Something went wrong.')));

        $middleware = new TolerateNoHandler();
        $middleware->handle(new DummyMessage('Hey'), $next);
    }
}
