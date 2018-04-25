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
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\TraceableMessageBus;

class TraceableMessageBusTest extends TestCase
{
    public function testItTracesResult()
    {
        $message = new DummyMessage('Hello');

        $bus = $this->getMockBuilder(MessageBusInterface::class)->getMock();
        $bus->expects($this->once())->method('dispatch')->with($message)->willReturn($result = array('foo' => 'bar'));

        $traceableBus = new TraceableMessageBus($bus);
        $this->assertSame($result, $traceableBus->dispatch($message));
        $this->assertSame(array(array('message' => $message, 'result' => $result)), $traceableBus->getDispatchedMessages());
    }

    public function testItTracesExceptions()
    {
        $message = new DummyMessage('Hello');

        $bus = $this->getMockBuilder(MessageBusInterface::class)->getMock();
        $bus->expects($this->once())->method('dispatch')->with($message)->will($this->throwException($exception = new \RuntimeException('Meh.')));

        $traceableBus = new TraceableMessageBus($bus);

        try {
            $traceableBus->dispatch($message);
        } catch (\RuntimeException $e) {
            $this->assertSame($exception, $e);
        }

        $this->assertSame(array(array('message' => $message, 'exception' => $exception)), $traceableBus->getDispatchedMessages());
    }
}
