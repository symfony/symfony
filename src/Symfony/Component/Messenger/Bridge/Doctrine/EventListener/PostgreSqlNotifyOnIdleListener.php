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

    public function __construct(
        private ?LoggerInterface $logger = null,
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

        foreach ($event->getWorker()->getMetadata()->getTransportNames() as $transportName) {
            if ($connection = $this->connections[$transportName] ?? null) {
                $connection->listen();
                $this->activeConnection ??= $connection;
            }
        }
    }

    public function onWorkerRunning(WorkerRunningEvent $event): void
    {
        if (!$event->isWorkerIdle() || !$this->activeConnection) {
            return;
        }

        $config = $this->activeConnection->getConfiguration();

        if (0 >= $timeout = $config['get_notify_timeout'] ?: $config['check_delayed_interval']) {
            return;
        }

        $this->logger?->debug('Worker waiting for PostgreSQL LISTEN/NOTIFY wake-up.');

        $this->activeConnection->waitForNotify($timeout);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerStartedEvent::class => 'onWorkerStarted',
            WorkerRunningEvent::class => 'onWorkerRunning',
        ];
    }
}
