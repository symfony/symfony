<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Messenger\Tests;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Messenger\MessageBus;
use Symphony\Component\Messenger\MessageBusInterface;
use Symphony\Component\Messenger\MiddlewareInterface;
use Symphony\Component\Messenger\Tests\Fixtures\DummyMessage;

class MessageBusTest extends TestCase
{
    public function testItHasTheRightInterface()
    {
        $bus = new MessageBus();

        $this->assertInstanceOf(MessageBusInterface::class, $bus);
    }

    public function testItCallsTheMiddlewaresAndChainTheReturnValue()
    {
        $message = new DummyMessage('Hello');
        $responseFromDepthMiddleware = 1234;

        $firstMiddleware = $this->createMock(MiddlewareInterface::class);
        $firstMiddleware->expects($this->once())
            ->method('handle')
            ->with($message, $this->anything())
            ->will($this->returnCallback(function($message, $next) {
                return $next($message);
            }));

        $secondMiddleware = $this->createMock(MiddlewareInterface::class);
        $secondMiddleware->expects($this->once())
            ->method('handle')
            ->with($message, $this->anything())
            ->willReturn($responseFromDepthMiddleware);

        $bus = new MessageBus([
            $firstMiddleware,
            $secondMiddleware,
        ]);

        $this->assertEquals($responseFromDepthMiddleware, $bus->dispatch($message));
    }
}
