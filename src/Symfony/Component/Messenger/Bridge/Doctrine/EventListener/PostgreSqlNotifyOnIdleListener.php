<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Doctrine\EventListener;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Types\Types;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\PostgreSqlConnection;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Event\WorkerStartedEvent;

/**
 * When the worker is idle, blocks on PostgreSQL LISTEN/NOTIFY instead of
 * polling. This allows instant wake-up when a new message arrives while
 * properly supporting workers that consume from multiple queues.
 *
 * @author d-ph <dph03292@gmail.com>
 */
class PostgreSqlNotifyOnIdleListener implements EventSubscriberInterface
{
    /** @var array<string, PostgreSqlConnection> */
    private array $connections = [];
    private ?PostgreSqlConnection $activeConnection = null;
    private ?float $deadline = null;
    private ?int $sleepCapMs = null;
    /** @var list<string> */
    private array $queueNames = [];

    public function __construct(
        private ?LoggerInterface $logger = null,
        private ?ClockInterface $clock = null,
    ) {
    }

    /**
     * Registers a PostgreSQL connection candidate for LISTEN/NOTIFY.
     *
     * Called by {@see \Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransportFactory}
     * during transport creation.
     */
    public function addConnection(string $transportName, PostgreSqlConnection $connection): void
    {
        $this->connections[$transportName] = $connection;
    }

    public function onWorkerStarted(WorkerStartedEvent $event): void
    {
        $this->activeConnection = null;
        $this->deadline = $event->getDeadline();

        $allTransportNames = $event->getWorker()->getMetadata()->getTransportNames();

        $matched = [];
        foreach ($allTransportNames as $transportName) {
            if ($connection = $this->connections[$transportName] ?? null) {
                $matched[$transportName] = $connection;
                $this->queueNames[] = $connection->getConfiguration()['queue_name'];
            }
        }

        // When non-PostgreSQL transports are also consumed, cap the NOTIFY wait to
        // the worker's sleep duration so those transports are still polled regularly.
        $this->sleepCapMs = \count($matched) < \count($allTransportNames) ? (int) ($event->getIdleTimeout() / 1000) : null;

        if (\count($matched) > 1) {
            $this->validateConnections($matched);
        }

        foreach ($matched as $connection) {
            // Only the first (active) connection executes LISTEN on the database; the others just mark get() as externally
            // handled to avoid accumulating unread notifications on connections that never call waitForNotify().
            $connection->listen(null === $this->activeConnection);
            $this->activeConnection ??= $connection;
        }
    }

    public function onWorkerRunning(WorkerRunningEvent $event): void
    {
        if (!$event->isWorkerIdle() || !$this->activeConnection) {
            return;
        }

        $config = $this->activeConnection->getConfiguration();

        if (0 >= $timeout = $config['get_notify_timeout']) {
            return;
        }

        $now = $this->clock?->now()->format('U.u') ?? microtime(true);

        // Cap by worker deadline (--time-limit) — checked first to avoid a SQL query
        // in getEarliestDelayedMessageTime() when the worker is already past its deadline.
        if (null !== $this->deadline) {
            $deadline = ($this->deadline - $now) * 1000;
            if (0 >= $timeout = min($timeout, $deadline)) {
                return;
            }
        }

        // Cap by earliest delayed message across all PG queues: wake up in time to process it
        if (null !== $earliest = $this->getEarliestDelayedMessageTime()) {
            $msUntilEarliest = ($earliest->format('U.u') - $now) * 1000;
            if (0 >= $timeout = min($timeout, $msUntilEarliest)) {
                return;
            }
        }

        // Cap by sleep duration when non-PG transports are present to ensure they are still polled regularly
        if (0 >= $timeout = (int) min($timeout, $this->sleepCapMs ?? $timeout)) {
            return;
        }

        $this->logger?->debug('Worker waiting for PostgreSQL LISTEN/NOTIFY wake-up.', ['timeout_ms' => $timeout]);

        $this->activeConnection->waitForNotify($timeout);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerStartedEvent::class => 'onWorkerStarted',
            WorkerRunningEvent::class => 'onWorkerRunning',
        ];
    }

    /**
     * @param array<string, PostgreSqlConnection> $connections
     */
    private function validateConnections(array $connections): void
    {
        $referenceConfig = null;
        $referenceDriver = null;
        $referenceName = null;

        foreach ($connections as $transportName => $connection) {
            $config = $connection->getConfiguration();
            $driver = $connection->getDriverConnection();

            if (null === $referenceConfig) {
                $referenceConfig = $config;
                $referenceDriver = $driver;
                $referenceName = $transportName;
                continue;
            }

            if ($driver !== $referenceDriver) {
                throw new \LogicException(\sprintf('PostgreSQL transports "%s" and "%s" use different DBAL connections. When consuming from multiple PostgreSQL queues in one worker, all transports must share the same DBAL connection.', $referenceName, $transportName));
            }

            if ($config['table_name'] !== $referenceConfig['table_name']) {
                throw new \LogicException(\sprintf('PostgreSQL transports "%s" and "%s" use different table_name values ("%s" vs "%s"). When consuming from multiple PostgreSQL queues in one worker, all transports must use the same table.', $referenceName, $transportName, $referenceConfig['table_name'], $config['table_name']));
            }
        }
    }

    /**
     * Returns the earliest available_at for delayed messages across all tracked PG queues, or null if there are none.
     */
    private function getEarliestDelayedMessageTime(): ?\DateTimeImmutable
    {
        $config = $this->activeConnection->getConfiguration();
        $dbal = $this->activeConnection->getDriverConnection();

        $sql = \sprintf('SELECT MIN(available_at) FROM %s WHERE queue_name IN (?) AND available_at > ? AND delivered_at IS NULL', $config['table_name']);
        $result = $dbal->executeQuery($sql, [
            $this->queueNames,
            $this->clock?->now() ?? new \DateTimeImmutable(),
        ], [
            ArrayParameterType::STRING,
            Types::DATETIME_IMMUTABLE,
        ])->fetchOne();

        if (!$result) {
            return null;
        }

        return new \DateTimeImmutable($result, new \DateTimeZone('UTC'));
    }
}
