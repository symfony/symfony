<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\MiddlewareInterface;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

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

        $firstMiddleware = $this->getMockBuilder(MiddlewareInterface::class)->getMock();
        $firstMiddleware->expects($this->once())
            ->method('handle')
            ->with($message, $this->anything())
            ->will($this->returnCallback(function ($message, $next) {
                return $next($message);
            }));

        $secondMiddleware = $this->getMockBuilder(MiddlewareInterface::class)->getMock();
        $secondMiddleware->expects($this->once())
            ->method('handle')
            ->with($message, $this->anything())
            ->willReturn($responseFromDepthMiddleware);

        $bus = new MessageBus(array(
            $firstMiddleware,
            $secondMiddleware,
        ));

        $this->assertEquals($responseFromDepthMiddleware, $bus->dispatch($message));
    }
}
