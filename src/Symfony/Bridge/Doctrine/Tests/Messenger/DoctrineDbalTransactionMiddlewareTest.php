<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Messenger;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bridge\Doctrine\Messenger\DoctrineDbalTransactionMiddleware;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;

class DoctrineDbalTransactionMiddlewareTest extends MiddlewareTestCase
{
    private MockObject&Connection $connection;
    private DoctrineDbalTransactionMiddleware $middleware;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);

        $connectionRegistry = $this->createStub(ConnectionRegistry::class);
        $connectionRegistry->method('getConnection')->willReturn($this->connection);

        $this->middleware = new DoctrineDbalTransactionMiddleware($connectionRegistry);
    }

    public function testMiddlewareWrapsInTransaction()
    {
        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('commit');

        $this->middleware->handle(new Envelope(new \stdClass()), $this->getStackMock());
    }

    public function testTransactionIsRolledBackOnException()
    {
        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('isTransactionActive')->willReturn(true);
        $this->connection->expects($this->once())->method('rollBack');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Thrown from next middleware.');

        $this->middleware->handle(new Envelope(new \stdClass()), $this->getThrowingStackMock());
    }

    public function testExceptionInRollBackDoesNotHidePreviousException()
    {
        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('isTransactionActive')->willReturn(true);
        $this->connection->expects($this->once())->method('rollBack')->willThrowException(new \RuntimeException('Thrown from rollBack.'));

        try {
            $this->middleware->handle(new Envelope(new \stdClass()), $this->getThrowingStackMock());
        } catch (\Throwable $exception) {
        }

        self::assertNotNull($exception);
        self::assertInstanceOf(\RuntimeException::class, $exception);
        self::assertSame('Thrown from rollBack.', $exception->getMessage());

        $previous = $exception->getPrevious();
        self::assertNotNull($previous);
        self::assertInstanceOf(\RuntimeException::class, $previous);
        self::assertSame('Thrown from next middleware.', $previous->getMessage());
    }

    public function testHandledStampsAreRemovedOnHandlerFailure()
    {
        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('isTransactionActive')->willReturn(true);
        $this->connection->expects($this->once())->method('rollBack');

        $envelope = new Envelope(new \stdClass(), [new HandledStamp('done', 'first_handler')]);
        $wrappedException = new \RuntimeException('Thrown from a handler.');

        try {
            $this->middleware->handle($envelope, $this->getThrowingStackMock(new HandlerFailedException($envelope, ['second_handler' => $wrappedException])));

            $this->fail('HandlerFailedException was not thrown.');
        } catch (HandlerFailedException $exception) {
            self::assertSame([], $exception->getEnvelope()->all(HandledStamp::class));
            self::assertSame(['second_handler' => $wrappedException], $exception->getWrappedExceptions());
        }
    }

    public function testUnknownConnectionThrowsUnrecoverableException()
    {
        $this->connection->expects($this->never())->method('beginTransaction');
        $connectionRegistry = $this->createMock(ConnectionRegistry::class);
        $connectionRegistry
            ->expects($this->once())
            ->method('getConnection')
            ->with('unknown_connection')
            ->willThrowException(new \InvalidArgumentException());

        $middleware = new DoctrineDbalTransactionMiddleware($connectionRegistry, 'unknown_connection');

        $this->expectException(UnrecoverableMessageHandlingException::class);

        $middleware->handle(new Envelope(new \stdClass()), $this->getStackMock(false));
    }
}
