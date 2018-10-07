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
use Symfony\Component\Messenger\Middleware\EnforceNullResultMiddleware;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

class EnforceNullResultMiddlewareTest extends TestCase
{
    public function testItCallsNextMiddleware()
    {
        $message = new DummyMessage('Hey');

        $next = $this->createPartialMock(\stdClass::class, array('__invoke'));
        $next->expects($this->once())->method('__invoke')->with($message);

        $middleware = new EnforceNullResultMiddleware();
        $middleware->handle($message, $next);
    }

    /**
     * @expectedException \Symfony\Component\Messenger\Exception\NonNullResultException
     * @expectedExceptionMessage Non null result for message "Symfony\Component\Messenger\Tests\Fixtures\DummyMessage": at least one handler returned something but this is prohibited by this middleware.
     */
    public function testItThrowExceptionOnNonNullResult()
    {
        $next = $this->createPartialMock(\stdClass::class, array('__invoke'));
        $next->expects($this->once())->method('__invoke')->will($this->returnValue('Non null value'));

        $middleware = new EnforceNullResultMiddleware();
        $middleware->handle(new DummyMessage('Hey'), $next);
    }
}
