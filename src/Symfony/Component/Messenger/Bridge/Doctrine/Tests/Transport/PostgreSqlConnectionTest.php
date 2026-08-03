<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Doctrine\Tests\Transport;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Result as DriverResult;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineReceiver;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\PostgreSqlConnection;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Worker;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class PostgreSqlConnectionTest extends TestCase
{
    public function testSerialize()
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Cannot serialize '.PostgreSqlConnection::class);

        $driverConnection = $this->createStub(Connection::class);
        $driverConnection->method('executeStatement')->willReturn(1);

        $connection = new PostgreSqlConnection([], $driverConnection);
        serialize($connection);
    }

    public function testUnserialize()
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Cannot unserialize '.PostgreSqlConnection::class);

        $driverConnection = $this->createStub(Connection::class);
        $driverConnection->method('executeStatement')->willReturn(1);

        $connection = new PostgreSqlConnection([], $driverConnection);
        $connection->__unserialize([]);
    }

    public function testListenOnConnection()
    {
        $driverConnection = $this->createStub(Connection::class);
        $driverConnection->method('executeStatement')->willReturn(1);

        $connection = new PostgreSqlConnection(['table_name' => 'queue_table'], $driverConnection);

        $this->assertFalse($connection->isListening());

        $connection->listen();

        $this->assertTrue($connection->isListening());

        $connection->__destruct();

        $this->assertFalse($connection->isListening());
    }

    public function testWaitForNotifyCallsListenAndGetNotify()
    {
        $driverConnection = $this->createMock(Connection::class);
        $driverConnection->method('executeStatement')->willReturn(1);

        $wrappedConnection = new class {
            public array $notifyTimeouts = [];

            public function getNotify(int $fetchMode = \PDO::FETCH_ASSOC, int|float $timeoutMs = 0)
            {
                $this->notifyTimeouts[] = $timeoutMs;

                return false;
            }
        };

        $driverConnection
            ->expects(self::once())
            ->method('getNativeConnection')
            ->willReturn($wrappedConnection);

        $connection = new PostgreSqlConnection(['table_name' => 'queue_table'], $driverConnection);

        $result = $connection->waitForNotify(60000);

        $this->assertFalse($result);
        $this->assertTrue($connection->isListening());
        $this->assertSame([60000], $wrappedConnection->notifyTimeouts);
    }

    public function testGetCollectsNotificationsWithoutWaitingWhenNoExternalListenerIsActive()
    {
        [$connection, $wrappedConnection] = $this->createConnectionWithNotifyTimeoutCapture([
            'table_name' => 'queue_table',
        ]);

        // first get(): queueEmptiedAt === null -> parent::get(), sets queueEmptiedAt
        $connection->get();
        // second/third get(): queueEmptiedAt !== null -> collects pending notifications
        $connection->get();
        $connection->get();

        $this->assertTrue($connection->isListening());
        $this->assertSame([0, 0], $wrappedConnection->notifyTimeouts);
    }

    public function testGetDoesNotWaitForNotifyWhenTimeoutIsSetToZero()
    {
        [$connection, $wrappedConnection] = $this->createConnectionWithNotifyTimeoutCapture([
            'table_name' => 'queue_table',
            'get_notify_timeout' => 0,
        ]);

        $connection->get();
        $connection->get();

        $this->assertSame([0], $wrappedConnection->notifyTimeouts);
    }

    public function testWorkerKeepsPollingItsOtherReceiversWhileThePostgreSqlQueueIsEmpty()
    {
        $clock = new MockClock();
        $startedAt = $clock->now()->getTimestamp();

        // a blocking getNotify() would move the clock forward
        [$connection] = $this->createConnectionWithNotifyTimeoutCapture(['table_name' => 'queue_table'], $clock);

        $otherReceiver = new class implements ReceiverInterface {
            public int $calls = 0;

            public function get(): iterable
            {
                ++$this->calls;

                return [];
            }

            public function ack(Envelope $envelope): void
            {
            }

            public function reject(Envelope $envelope): void
            {
            }
        };

        $worker = new Worker([
            'pgsql' => new DoctrineReceiver($connection),
            'other' => $otherReceiver,
        ], $this->createStub(MessageBusInterface::class), clock: $clock);

        $worker->run(['sleep' => 1000000, 'time_limit' => 5]);

        $this->assertSame(5, $otherReceiver->calls);
        $this->assertSame(5, $clock->now()->getTimestamp() - $startedAt);
    }

    private function createConnectionWithNotifyTimeoutCapture(array $configuration, ?MockClock $clock = null): array
    {
        $driverConnection = $this->createStub(Connection::class);
        $driverConnection->method('executeStatement')->willReturn(1);

        $driverConnection
            ->method('getDatabasePlatform')
            ->willReturn(new PostgreSQLPlatform());

        $driverConnection
            ->method('createQueryBuilder')
            ->willReturn(new QueryBuilder($driverConnection));

        $wrappedConnection = new class($clock) {
            public array $notifyTimeouts = [];

            public function __construct(private ?MockClock $clock)
            {
            }

            public function getNotify(int $fetchMode = \PDO::FETCH_ASSOC, int|float $timeoutMs = 0)
            {
                $this->notifyTimeouts[] = $timeoutMs;
                $this->clock?->sleep($timeoutMs / 1000);

                return false;
            }
        };

        $driverConnection
            ->method('getNativeConnection')
            ->willReturn($wrappedConnection);

        $driverResult = $this->createStub(DriverResult::class);
        $driverResult->method('fetchAssociative')
            ->willReturn(false);
        $driverConnection
            ->method('executeQuery')
            ->willReturn(new Result($driverResult, $driverConnection));

        return [new PostgreSqlConnection($configuration, $driverConnection), $wrappedConnection];
    }

    public function testGetSkipsBlockingWhenListenCalledExternally()
    {
        $driverConnection = $this->createMock(Connection::class);
        $driverConnection->method('executeStatement')->willReturn(1);

        $driverConnection
            ->method('getDatabasePlatform')
            ->willReturn(new PostgreSQLPlatform());

        $driverConnection
            ->method('createQueryBuilder')
            ->willReturn(new QueryBuilder($driverConnection));

        // getNativeConnection should never be called since blocking is skipped
        $driverConnection
            ->expects(self::never())
            ->method('getNativeConnection');

        $driverResult = $this->createStub(DriverResult::class);
        $driverResult->method('fetchAssociative')
            ->willReturn(false);
        $driverConnection
            ->method('executeQuery')
            ->willReturn(new Result($driverResult, $driverConnection));

        $connection = new PostgreSqlConnection(['table_name' => 'queue_table'], $driverConnection);

        // External listener calls listen()
        $connection->listen();

        // get() should always delegate to parent without blocking
        $connection->get();
        $connection->get();
        $connection->get();
    }

    public function testIsListeningReturnsFalseWhenGetHasNotBeenCalled()
    {
        $driverConnection = $this->createStub(Connection::class);
        $connection = new PostgreSqlConnection(['table_name' => 'queue_table'], $driverConnection);

        $this->assertFalse($connection->isListening());
    }

    public function testListenWithoutDatabaseRegistration()
    {
        $driverConnection = $this->createMock(Connection::class);
        // executeStatement should NOT be called when registerOnDatabase is false
        $driverConnection->expects(self::never())->method('executeStatement');

        $connection = new PostgreSqlConnection(['table_name' => 'queue_table'], $driverConnection);

        $connection->listen(registerOnDatabase: false);

        // notifyHandledExternally should still be set (get() skips blocking)
        $this->assertFalse($connection->isListening());
    }

    public function testListenWithDatabaseRegistrationIsDefault()
    {
        $driverConnection = $this->createMock(Connection::class);
        // executeStatement will be called for LISTEN and then UNLISTEN in __destruct
        $driverConnection->expects(self::exactly(2))->method('executeStatement')
            ->willReturn(1);

        $connection = new PostgreSqlConnection(['table_name' => 'queue_table'], $driverConnection);

        $connection->listen();

        $this->assertTrue($connection->isListening());
    }

    public function testGetSkipsBlockingWhenListenCalledWithoutDatabaseRegistration()
    {
        $driverConnection = $this->createMock(Connection::class);

        $driverConnection
            ->method('getDatabasePlatform')
            ->willReturn(new PostgreSQLPlatform());

        $driverConnection
            ->method('createQueryBuilder')
            ->willReturn(new QueryBuilder($driverConnection));

        // getNativeConnection should never be called since blocking is skipped
        $driverConnection
            ->expects(self::never())
            ->method('getNativeConnection');

        // Allow executeStatement for the base get() flow, but not for LISTEN
        $driverConnection->method('executeStatement')->willReturn(1);

        $driverResult = $this->createStub(DriverResult::class);
        $driverResult->method('fetchAssociative')
            ->willReturn(false);
        $driverConnection
            ->method('executeQuery')
            ->willReturn(new Result($driverResult, $driverConnection));

        $connection = new PostgreSqlConnection(['table_name' => 'queue_table'], $driverConnection);

        // External listener calls listen() without database registration
        $connection->listen(registerOnDatabase: false);

        // get() should always delegate to parent without blocking
        $connection->get();
        $connection->get();
    }
}
