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

    public function testMultipleTransportsListenOnAllConnections()
    {
        $conn1 = $this->createPostgreSqlConnection();
        $conn2 = $this->createPostgreSqlConnection();

        $listener = new PostgreSqlNotifyOnIdleListener();
        $listener->addConnection('high', $conn1);
        $listener->addConnection('low', $conn2);

        $listener->onWorkerStarted(new WorkerStartedEvent($this->createWorkerWithTransports(['high', 'low'])));

        $this->assertTrue($conn1->isListening());
        $this->assertTrue($conn2->isListening());
    }

    public function testGetSubscribedEvents()
    {
        $this->assertSame([
            WorkerStartedEvent::class => 'onWorkerStarted',
            WorkerRunningEvent::class => 'onWorkerRunning',
        ], PostgreSqlNotifyOnIdleListener::getSubscribedEvents());
    }
}
