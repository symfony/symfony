<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Doctrine\Tests\EventListener;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Result as DriverResult;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\Doctrine\EventListener\PostgreSqlNotifyOnIdleListener;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\PostgreSqlConnection;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Event\WorkerStartedEvent;
use Symfony\Component\Messenger\Worker;
use Symfony\Component\Messenger\WorkerMetadata;

class PostgreSqlNotifyOnIdleListenerTest extends TestCase
{
    private function createPostgreSqlConnection(array $additionalConfig = []): PostgreSqlConnection
    {
        $driverConnection = $this->createStub(Connection::class);
        $driverConnection->method('executeStatement')->willReturn(1);

        return new PostgreSqlConnection($additionalConfig + ['table_name' => 'queue_table'], $driverConnection);
    }

    private function createWorkerWithTransports(array $transportNames): Worker
    {
        $worker = $this->createStub(Worker::class);
        $worker->method('getMetadata')->willReturn(new WorkerMetadata(['transportNames' => $transportNames]));

        return $worker;
    }

    public function testListenIsCalledOnWorkerStarted()
    {
        $connection = $this->createPostgreSqlConnection();

        $listener = new PostgreSqlNotifyOnIdleListener();
        $listener->addConnection('async', $connection);

        $this->assertFalse($connection->isListening());

        $listener->onWorkerStarted(new WorkerStartedEvent($this->createWorkerWithTransports(['async'])));

        $this->assertTrue($connection->isListening());
    }

    public function testListenIsNotCalledForUnknownTransport()
    {
        $connection = $this->createPostgreSqlConnection();

        $listener = new PostgreSqlNotifyOnIdleListener();
        $listener->addConnection('async', $connection);

        $listener->onWorkerStarted(new WorkerStartedEvent($this->createWorkerWithTransports(['other'])));

        $this->assertFalse($connection->isListening());
    }

    public function testNoWaitWhenWorkerIsNotIdle()
    {
        $connection = $this->createPostgreSqlConnection();

        $listener = new PostgreSqlNotifyOnIdleListener();
        $listener->addConnection('async', $connection);

        $worker = $this->createWorkerWithTransports(['async']);
        $listener->onWorkerStarted(new WorkerStartedEvent($worker));

        // isIdle=false, should not try to wait
        $listener->onWorkerRunning(new WorkerRunningEvent($worker, false));

        $this->assertTrue(true);
    }

    public function testNoWaitWhenNoPostgreSqlConnection()
    {
        $listener = new PostgreSqlNotifyOnIdleListener();
        $worker = $this->createWorkerWithTransports(['other']);

        $listener->onWorkerStarted(new WorkerStartedEvent($worker));
        $listener->onWorkerRunning(new WorkerRunningEvent($worker, true));

        $this->assertTrue(true);
    }

    public function testNoWaitWhenTimeoutsAreZero()
    {
        $connection = $this->createPostgreSqlConnection([
            'get_notify_timeout' => 0,
            'check_delayed_interval' => 0,
        ]);

        $listener = new PostgreSqlNotifyOnIdleListener();
        $listener->addConnection('async', $connection);

        $worker = $this->createWorkerWithTransports(['async']);
        $listener->onWorkerStarted(new WorkerStartedEvent($worker));

        // Both timeouts are 0: should return immediately without blocking
        $listener->onWorkerRunning(new WorkerRunningEvent($worker, true));

        $this->assertTrue(true);
    }

    public function testMultipleTransportsOnlyFirstGetsDbListen()
    {
        $driverConnection = $this->createStub(Connection::class);
        $driverConnection->method('executeStatement')->willReturn(1);

        $conn1 = new PostgreSqlConnection(['table_name' => 'queue_table'], $driverConnection);
        $conn2 = new PostgreSqlConnection(['table_name' => 'queue_table'], $driverConnection);

        $listener = new PostgreSqlNotifyOnIdleListener();
        $listener->addConnection('high', $conn1);
        $listener->addConnection('low', $conn2);

        $listener->onWorkerStarted(new WorkerStartedEvent($this->createWorkerWithTransports(['high', 'low'])));

        // Only the active (first) connection should have LISTEN registered on DB
        $this->assertTrue($conn1->isListening());
        // The second connection should NOT have LISTEN registered on DB
        $this->assertFalse($conn2->isListening());
    }

    public function testMismatchedTableNamesThrows()
    {
        $driverConnection = $this->createStub(Connection::class);
        $driverConnection->method('executeStatement')->willReturn(1);

        $conn1 = new PostgreSqlConnection(['table_name' => 'table_a'], $driverConnection);
        $conn2 = new PostgreSqlConnection(['table_name' => 'table_b'], $driverConnection);

        $listener = new PostgreSqlNotifyOnIdleListener();
        $listener->addConnection('high', $conn1);
        $listener->addConnection('low', $conn2);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('table_name');

        $listener->onWorkerStarted(new WorkerStartedEvent($this->createWorkerWithTransports(['high', 'low'])));
    }

    public function testMismatchedDriverConnectionsThrows()
    {
        $driverConnection1 = $this->createStub(Connection::class);
        $driverConnection1->method('executeStatement')->willReturn(1);
        $driverConnection2 = $this->createStub(Connection::class);
        $driverConnection2->method('executeStatement')->willReturn(1);

        $conn1 = new PostgreSqlConnection(['table_name' => 'queue_table'], $driverConnection1);
        $conn2 = new PostgreSqlConnection(['table_name' => 'queue_table'], $driverConnection2);

        $listener = new PostgreSqlNotifyOnIdleListener();
        $listener->addConnection('high', $conn1);
        $listener->addConnection('low', $conn2);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('DBAL connection');

        $listener->onWorkerStarted(new WorkerStartedEvent($this->createWorkerWithTransports(['high', 'low'])));
    }

    public function testSingleTransportDoesNotThrowValidation()
    {
        $connection = $this->createPostgreSqlConnection();

        $listener = new PostgreSqlNotifyOnIdleListener();
        $listener->addConnection('async', $connection);

        $listener->onWorkerStarted(new WorkerStartedEvent($this->createWorkerWithTransports(['async'])));

        $this->assertTrue($connection->isListening());
    }

    public function testGetSubscribedEvents()
    {
        $this->assertSame([
            WorkerStartedEvent::class => 'onWorkerStarted',
            WorkerRunningEvent::class => 'onWorkerRunning',
        ], PostgreSqlNotifyOnIdleListener::getSubscribedEvents());
    }

    private function createConnectionWithGetNotifyCapture(array $config, ?string $delayedAvailableAt = null): array
    {
        $driverConnection = $this->createStub(Connection::class);
        $driverConnection->method('executeStatement')->willReturn(1);
        $driverConnection->method('getDatabasePlatform')->willReturn(new PostgreSQLPlatform());
        $driverConnection->method('createQueryBuilder')->willReturn(new QueryBuilder($driverConnection));

        // For getEarliestDelayedMessageTime query
        $delayedResult = $this->createStub(DriverResult::class);
        $delayedResult->method('fetchOne')->willReturn($delayedAvailableAt ?? false);

        $driverConnection->method('executeQuery')
            ->willReturn(new Result($delayedResult, $driverConnection));

        // Use ArrayObject so object identity survives array destructuring
        // (plain array references are lost when using [$a, $b] = ...)
        $capturedTimeouts = new \ArrayObject();
        $wrappedConnection = new class($capturedTimeouts) {
            public function __construct(private \ArrayObject $captured)
            {
            }

            public function getNotify(int $fetchMode, int $timeout): false
            {
                $this->captured[] = $timeout;

                return false;
            }
        };

        $driverConnection->method('getNativeConnection')->willReturn($wrappedConnection);

        $connection = new PostgreSqlConnection($config + ['table_name' => 'queue_table', 'queue_name' => 'default'], $driverConnection);

        return [$connection, $capturedTimeouts, $driverConnection];
    }

    public function testTimeoutCappedByDeadline()
    {
        [$connection, $capturedTimeouts] = $this->createConnectionWithGetNotifyCapture([
            'get_notify_timeout' => 60000,
        ]);

        $listener = new PostgreSqlNotifyOnIdleListener();
        $listener->addConnection('async', $connection);

        $worker = $this->createWorkerWithTransports(['async']);
        $listener->onWorkerStarted(new WorkerStartedEvent($worker, microtime(true) + 2));
        $listener->onWorkerRunning(new WorkerRunningEvent($worker, true));

        $this->assertCount(1, $capturedTimeouts);
        $this->assertGreaterThan(0, $capturedTimeouts[0]);
        $this->assertLessThanOrEqual(2500, $capturedTimeouts[0]);
    }

    public function testTimeoutCappedByDelayedMessage()
    {
        $future = new \DateTimeImmutable('+3 seconds', new \DateTimeZone('UTC'));

        [$connection, $capturedTimeouts] = $this->createConnectionWithGetNotifyCapture(
            ['get_notify_timeout' => 60000],
            $future->format('Y-m-d H:i:s.u'),
        );

        $listener = new PostgreSqlNotifyOnIdleListener();
        $listener->addConnection('async', $connection);

        $worker = $this->createWorkerWithTransports(['async']);
        $listener->onWorkerStarted(new WorkerStartedEvent($worker));
        $listener->onWorkerRunning(new WorkerRunningEvent($worker, true));

        $this->assertCount(1, $capturedTimeouts);
        $this->assertGreaterThan(0, $capturedTimeouts[0]);
        $this->assertLessThanOrEqual(4000, $capturedTimeouts[0]);
    }

    public function testTimeoutUsesMinOfAllConstraints()
    {
        // Delayed message in 10 seconds, deadline in 5 seconds, config 60s
        $future = new \DateTimeImmutable('+10 seconds', new \DateTimeZone('UTC'));

        [$connection, $capturedTimeouts] = $this->createConnectionWithGetNotifyCapture(
            ['get_notify_timeout' => 60000],
            $future->format('Y-m-d H:i:s.u'),
        );

        $listener = new PostgreSqlNotifyOnIdleListener();
        $listener->addConnection('async', $connection);

        $worker = $this->createWorkerWithTransports(['async']);
        $listener->onWorkerStarted(new WorkerStartedEvent($worker, microtime(true) + 5));
        $listener->onWorkerRunning(new WorkerRunningEvent($worker, true));

        // Should pick deadline (5s) as minimum over delayed (10s) and config (60s)
        $this->assertCount(1, $capturedTimeouts);
        $this->assertGreaterThan(0, $capturedTimeouts[0]);
        $this->assertLessThanOrEqual(5500, $capturedTimeouts[0]);
    }

    public function testNoWaitWhenDeadlineAlreadyPassed()
    {
        [$connection, $capturedTimeouts] = $this->createConnectionWithGetNotifyCapture([
            'get_notify_timeout' => 60000,
        ]);

        $listener = new PostgreSqlNotifyOnIdleListener();
        $listener->addConnection('async', $connection);

        $worker = $this->createWorkerWithTransports(['async']);
        // Use a deadline already in the past
        $listener->onWorkerStarted(new WorkerStartedEvent($worker, microtime(true) - 1));
        $listener->onWorkerRunning(new WorkerRunningEvent($worker, true));

        // Should not call waitForNotify when deadline has passed
        $this->assertCount(0, $capturedTimeouts);
    }

    public function testTimeoutCappedBySleepWhenNonPgTransportsPresent()
    {
        [$connection, $capturedTimeouts] = $this->createConnectionWithGetNotifyCapture([
            'get_notify_timeout' => 60000,
        ]);

        $listener = new PostgreSqlNotifyOnIdleListener();
        $listener->addConnection('pg_transport', $connection);

        // Worker consumes from both a PG and a non-PG transport
        $worker = $this->createWorkerWithTransports(['pg_transport', 'redis_transport']);
        // sleep = 2_000_000 microseconds = 2s → cap should be 2000ms
        $listener->onWorkerStarted(new WorkerStartedEvent($worker, null, 2000000));
        $listener->onWorkerRunning(new WorkerRunningEvent($worker, true));

        $this->assertCount(1, $capturedTimeouts);
        $this->assertLessThanOrEqual(2000, $capturedTimeouts[0]);
        $this->assertGreaterThan(0, $capturedTimeouts[0]);
    }

    public function testTimeoutNotCappedBySleepWhenAllTransportsArePg()
    {
        $driverConnection = $this->createStub(Connection::class);
        $driverConnection->method('executeStatement')->willReturn(1);
        $driverConnection->method('getDatabasePlatform')->willReturn(new PostgreSQLPlatform());
        $driverConnection->method('createQueryBuilder')->willReturn(new QueryBuilder($driverConnection));

        $delayedResult = $this->createStub(DriverResult::class);
        $delayedResult->method('fetchOne')->willReturn(false);
        $driverConnection->method('executeQuery')
            ->willReturn(new Result($delayedResult, $driverConnection));

        $capturedTimeouts = new \ArrayObject();
        $wrappedConnection = new class($capturedTimeouts) {
            public function __construct(private \ArrayObject $captured)
            {
            }

            public function getNotify(int $fetchMode, int $timeout): false
            {
                $this->captured[] = $timeout;

                return false;
            }
        };
        $driverConnection->method('getNativeConnection')->willReturn($wrappedConnection);

        $conn1 = new PostgreSqlConnection(['table_name' => 'queue_table', 'queue_name' => 'default', 'get_notify_timeout' => 60000], $driverConnection);
        $conn2 = new PostgreSqlConnection(['table_name' => 'queue_table', 'queue_name' => 'other', 'get_notify_timeout' => 60000], $driverConnection);

        $listener = new PostgreSqlNotifyOnIdleListener();
        $listener->addConnection('high', $conn1);
        $listener->addConnection('low', $conn2);

        // All transports are PG, idleTimeout = 1s → should NOT cap to 1000ms
        $worker = $this->createWorkerWithTransports(['high', 'low']);
        $listener->onWorkerStarted(new WorkerStartedEvent($worker, null, 1000000));
        $listener->onWorkerRunning(new WorkerRunningEvent($worker, true));

        $this->assertCount(1, $capturedTimeouts);
        // Without sleep cap, the full 60000ms config timeout should be used
        $this->assertSame(60000, $capturedTimeouts[0]);
    }
}
