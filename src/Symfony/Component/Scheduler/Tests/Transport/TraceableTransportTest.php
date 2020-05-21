<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Transport\TraceableTransport;
use Symfony\Component\Scheduler\Transport\TransportInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TraceableTransportTest extends TestCase
{
    public function testTransportCanCatchExceptionsDuringGet(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $transport = $this->createMock(TransportInterface::class);
        $transport->expects(self::once())->method('get')->willThrowException(new RuntimeException('An error occurred'));

        $traceableTransport = new TraceableTransport($transport, $logger);

        static::expectException(RuntimeException::class);
        $traceableTransport->get('foo');

        static::assertSame(1, $traceableTransport->getExceptionsCount());
    }

    public function testTransportCanCatchExceptionsDuringList(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $transport = $this->createMock(TransportInterface::class);
        $transport->expects(self::once())->method('list')->willThrowException(new RuntimeException('An error occurred'));

        $traceableTransport = new TraceableTransport($transport, $logger);

        static::expectException(RuntimeException::class);
        $traceableTransport->list();

        static::assertSame(1, $traceableTransport->getExceptionsCount());
    }

    public function testTransportCanCatchExceptionsDuringCreate(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $transport = $this->createMock(TransportInterface::class);
        $transport->expects(self::once())->method('create')->willThrowException(new RuntimeException('An error occurred'));

        $traceableTransport = new TraceableTransport($transport, $logger);

        static::expectException(RuntimeException::class);
        $traceableTransport->create($task);

        static::assertSame(1, $traceableTransport->getExceptionsCount());
    }

    public function testTransportCanCatchExceptionsDuringUpdate(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $transport = $this->createMock(TransportInterface::class);
        $transport->expects(self::once())->method('update')->willThrowException(new RuntimeException('An error occurred'));

        $traceableTransport = new TraceableTransport($transport, $logger);

        static::expectException(RuntimeException::class);
        $traceableTransport->update('foo', $task);

        static::assertSame(1, $traceableTransport->getExceptionsCount());
    }

    public function testTransportCanCatchExceptionsDuringDelete(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $transport = $this->createMock(TransportInterface::class);
        $transport->expects(self::once())->method('delete')->willThrowException(new RuntimeException('An error occurred'));

        $traceableTransport = new TraceableTransport($transport, $logger);

        static::expectException(RuntimeException::class);
        $traceableTransport->delete('foo');

        static::assertSame(1, $traceableTransport->getExceptionsCount());
    }

    public function testTransportCanCatchExceptionsDuringPause(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $transport = $this->createMock(TransportInterface::class);
        $transport->expects(self::once())->method('pause')->willThrowException(new RuntimeException('An error occurred'));

        $traceableTransport = new TraceableTransport($transport, $logger);

        static::expectException(RuntimeException::class);
        $traceableTransport->pause('foo');

        static::assertSame(1, $traceableTransport->getExceptionsCount());
    }

    public function testTransportCanCatchExceptionsDuringResume(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $transport = $this->createMock(TransportInterface::class);
        $transport->expects(self::once())->method('resume')->willThrowException(new RuntimeException('An error occurred'));

        $traceableTransport = new TraceableTransport($transport, $logger);

        static::expectException(RuntimeException::class);
        $traceableTransport->resume('foo');

        static::assertSame(1, $traceableTransport->getExceptionsCount());
    }

    public function testTransportCanCatchExceptionsDuringEmpty(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $transport = $this->createMock(TransportInterface::class);
        $transport->expects(self::once())->method('clear')->willThrowException(new RuntimeException('An error occurred'));

        $traceableTransport = new TraceableTransport($transport, $logger);

        static::expectException(RuntimeException::class);
        $traceableTransport->clear();

        static::assertSame(1, $traceableTransport->getExceptionsCount());
    }
}
