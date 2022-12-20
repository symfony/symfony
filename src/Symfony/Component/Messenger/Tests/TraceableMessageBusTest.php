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
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Tests\Fixtures\AnEnvelopeStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\TestTracesWithHandleTraitAction;
use Symfony\Component\Messenger\TraceableMessageBus;

class TraceableMessageBusTest extends TestCase
{
    public function testItTracesDispatch()
    {
        $message = new DummyMessage('Hello');

        $stamp = new DelayStamp(5);
        $bus = self::createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->with($message, [$stamp])->willReturn(new Envelope($message, [$stamp]));

        $traceableBus = new TraceableMessageBus($bus);
        $line = __LINE__ + 1;
        $traceableBus->dispatch($message, [$stamp]);
        self::assertCount(1, $tracedMessages = $traceableBus->getDispatchedMessages());
        $actualTracedMessage = $tracedMessages[0];
        unset($actualTracedMessage['callTime']); // don't check, too variable
        self::assertEquals([
            'message' => $message,
            'stamps' => [$stamp],
            'stamps_after_dispatch' => [$stamp],
            'caller' => [
                'name' => 'TraceableMessageBusTest.php',
                'file' => __FILE__,
                'line' => $line,
            ],
        ], $actualTracedMessage);
    }

    public function testItTracesDispatchWhenHandleTraitIsUsed()
    {
        $message = new DummyMessage('Hello');

        $bus = self::createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->with($message)->willReturn((new Envelope($message))->with($stamp = new HandledStamp('result', 'handlerName')));

        $traceableBus = new TraceableMessageBus($bus);
        (new TestTracesWithHandleTraitAction($traceableBus))($message);
        self::assertCount(1, $tracedMessages = $traceableBus->getDispatchedMessages());
        $actualTracedMessage = $tracedMessages[0];
        unset($actualTracedMessage['callTime']); // don't check, too variable
        self::assertEquals([
            'message' => $message,
            'stamps' => [],
            'stamps_after_dispatch' => [$stamp],
            'caller' => [
                'name' => 'TestTracesWithHandleTraitAction.php',
                'file' => (new \ReflectionClass(TestTracesWithHandleTraitAction::class))->getFileName(),
                'line' => (new \ReflectionMethod(TestTracesWithHandleTraitAction::class, '__invoke'))->getStartLine() + 2,
            ],
        ], $actualTracedMessage);
    }

    public function testItTracesDispatchWithEnvelope()
    {
        $message = new DummyMessage('Hello');
        $envelope = (new Envelope($message))->with($stamp = new AnEnvelopeStamp());

        $bus = self::createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->with($envelope)->willReturn($envelope);

        $traceableBus = new TraceableMessageBus($bus);
        $line = __LINE__ + 1;
        $traceableBus->dispatch($envelope);
        self::assertCount(1, $tracedMessages = $traceableBus->getDispatchedMessages());
        $actualTracedMessage = $tracedMessages[0];
        unset($actualTracedMessage['callTime']); // don't check, too variable
        self::assertEquals([
            'message' => $message,
            'stamps' => [$stamp],
            'stamps_after_dispatch' => [$stamp],
            'caller' => [
                'name' => 'TraceableMessageBusTest.php',
                'file' => __FILE__,
                'line' => $line,
            ],
        ], $actualTracedMessage);
    }

    public function testItCollectsStampsAddedDuringDispatch()
    {
        $message = new DummyMessage('Hello');
        $envelope = (new Envelope($message))->with($stamp = new AnEnvelopeStamp());

        $bus = self::createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->with($envelope)->willReturn($envelope->with($anotherStamp = new AnEnvelopeStamp()));

        $traceableBus = new TraceableMessageBus($bus);
        $line = __LINE__ + 1;
        $traceableBus->dispatch($envelope);
        self::assertCount(1, $tracedMessages = $traceableBus->getDispatchedMessages());
        $actualTracedMessage = $tracedMessages[0];
        unset($actualTracedMessage['callTime']); // don't check, too variable
        self::assertEquals([
            'message' => $message,
            'stamps' => [$stamp],
            'stamps_after_dispatch' => [$stamp, $anotherStamp],
            'caller' => [
                'name' => 'TraceableMessageBusTest.php',
                'file' => __FILE__,
                'line' => $line,
            ],
        ], $actualTracedMessage);
    }

    public function testItTracesExceptions()
    {
        $message = new DummyMessage('Hello');

        $bus = self::createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->with($message)->willThrowException($exception = new \RuntimeException('Meh.'));

        $traceableBus = new TraceableMessageBus($bus);

        try {
            $line = __LINE__ + 1;
            $traceableBus->dispatch($message);
        } catch (\RuntimeException $e) {
            self::assertSame($exception, $e);
        }

        self::assertCount(1, $tracedMessages = $traceableBus->getDispatchedMessages());
        $actualTracedMessage = $tracedMessages[0];
        unset($actualTracedMessage['callTime']); // don't check, too variable
        self::assertEquals([
            'message' => $message,
            'exception' => $exception,
            'stamps' => [],
            'stamps_after_dispatch' => [],
            'caller' => [
                'name' => 'TraceableMessageBusTest.php',
                'file' => __FILE__,
                'line' => $line,
            ],
        ], $actualTracedMessage);
    }

    public function testItTracesExceptionsWhenMessageBusIsFiredFromArrayCallback()
    {
        $message = new DummyMessage('Hello');
        $exception = new \RuntimeException();

        $bus = self::createMock(MessageBusInterface::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->with($message)
            ->willThrowException($exception);

        $traceableBus = new TraceableMessageBus($bus);

        self::expectExceptionObject($exception);

        array_map([$traceableBus, 'dispatch'], [$message]);
    }
}
