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
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Tests\Fixtures\AnEnvelopeItem;
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
        $line = __LINE__ + 1;
        $this->assertSame($result, $traceableBus->dispatch($message));
        $this->assertCount(1, $tracedMessages = $traceableBus->getDispatchedMessages());
        $this->assertArraySubset(array(
            'message' => $message,
            'result' => $result,
            'envelopeItems' => null,
            'caller' => array(
                'name' => 'TraceableMessageBusTest.php',
                'file' => __FILE__,
                'line' => $line,
            ),
        ), $tracedMessages[0], true);
    }

    public function testItTracesResultWithEnvelope()
    {
        $envelope = Envelope::wrap($message = new DummyMessage('Hello'))->with($envelopeItem = new AnEnvelopeItem());

        $bus = $this->getMockBuilder(MessageBusInterface::class)->getMock();
        $bus->expects($this->once())->method('dispatch')->with($envelope)->willReturn($result = array('foo' => 'bar'));

        $traceableBus = new TraceableMessageBus($bus);
        $line = __LINE__ + 1;
        $this->assertSame($result, $traceableBus->dispatch($envelope));
        $this->assertCount(1, $tracedMessages = $traceableBus->getDispatchedMessages());
        $this->assertArraySubset(array(
            'message' => $message,
            'result' => $result,
            'envelopeItems' => array($envelopeItem),
            'caller' => array(
                'name' => 'TraceableMessageBusTest.php',
                'file' => __FILE__,
                'line' => $line,
            ),
        ), $tracedMessages[0], true);
    }

    public function testItTracesExceptions()
    {
        $message = new DummyMessage('Hello');

        $bus = $this->getMockBuilder(MessageBusInterface::class)->getMock();
        $bus->expects($this->once())->method('dispatch')->with($message)->will($this->throwException($exception = new \RuntimeException('Meh.')));

        $traceableBus = new TraceableMessageBus($bus);

        try {
            $line = __LINE__ + 1;
            $traceableBus->dispatch($message);
        } catch (\RuntimeException $e) {
            $this->assertSame($exception, $e);
        }

        $this->assertCount(1, $tracedMessages = $traceableBus->getDispatchedMessages());
        $this->assertArraySubset(array(
            'message' => $message,
            'exception' => $exception,
            'envelopeItems' => null,
            'caller' => array(
                'name' => 'TraceableMessageBusTest.php',
                'file' => __FILE__,
                'line' => $line,
            ),
        ), $tracedMessages[0], true);
    }
}
