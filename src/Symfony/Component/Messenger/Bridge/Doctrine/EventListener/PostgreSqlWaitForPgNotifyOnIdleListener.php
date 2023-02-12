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
use RuntimeException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\PostgreSqlConnection;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Event\WorkerStartedEvent;
use Symfony\Component\Messenger\Worker;

/**
 * Operational only when DoctrineTransport, PostgreSQL and pg_notify is used. On worker's idle event, the worker is
 * going to be put to sleep until woken up by pg_notify.
 */
class PostgreSqlWaitForPgNotifyOnIdleListener implements EventSubscriberInterface
{
    private ?LoggerInterface $logger;
    private ?PostgreSqlConnection $leaderConnection;

    /** @var array<string, PostgreSqlConnection> Keyed by Messenger's transport names */
    private array $postgreSqlConnectionsMapping;

    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    public function registerPostgreSqlConnectionCandidate(string $messengerTransportName, PostgreSqlConnection $connection): void
    {
        // @todo-PR The registration this way is needed because there's no way to retrieve DoctrineReceiver's Connection
        // otherwise. I would prefer to add a DoctrineReceiver::getConnection() and DoctrineTransport::getConnection(),
        // as this would reduce the "magic" here. As things currently stand, this event listener is a noop if
        // DoctrineTransportFactory is not used to instantiate transports.
        $this->postgreSqlConnectionsMapping[$messengerTransportName] = $connection;
    }

    public function onWorkerStarted(WorkerStartedEvent $event): void
    {
        $this->leaderConnection = $this->electLeaderPostgreSqlConnection($event->getWorker());

        if ($this->leaderConnection) {
            // It's important to start pg listening on the very start of the worker, so that no pg events are missed
            // between the first get() and "idleWait()"
            $this->leaderConnection->registerPgNotifyListener();
        }
    }

    public function onWorkerRunning(WorkerRunningEvent $event): void
    {
        if (!$this->leaderConnection || !$event->isWorkerIdle()) {
            return;
        }

        $this->logger?->debug('Worker going into sleep until PostgreSQL LISTEN/NOTIFY wakeup');

        // Note that this sync-wait is meant to "wake up" on anything potentially interesting. It means that it
        // will sometimes wake up for irrelevant reasons, which is fine.
        $this->leaderConnection->sleepUntilPgNotify($this->choosePgNotifyTimeout($event->getWorker()));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerStartedEvent::class => 'onWorkerStarted',
            WorkerRunningEvent::class => 'onWorkerRunning',
        ];
    }

    private function electLeaderPostgreSqlConnection(Worker $worker): ?PostgreSqlConnection
    {
        /** @var PostgreSqlConnection[] $usedPostgreSqlConnections */
        $usedPostgreSqlConnections = [];
        $nonPostgreSqlConnectionIsUsed = false;
        foreach ($worker->getMetadata()->getTransportNames() as $transportName) {
            $postgreSqlConnection = $this->postgreSqlConnectionsMapping[$transportName] ?? null;

            if (!$postgreSqlConnection) {
                $nonPostgreSqlConnectionIsUsed = true;
            }

            $usedPostgreSqlConnections[] = $postgreSqlConnection;
        }

        if (!$usedPostgreSqlConnections) {
            return null;
        }

        // elect the leader
        $leaderPostgreSqlConnection = null;
        foreach ($usedPostgreSqlConnections as $usedPostgreSqlConnection) {
            $connectionConfiguration = $usedPostgreSqlConnection->getConfiguration();

            if (!$connectionConfiguration['use_notify'] || $connectionConfiguration['get_notify_timeout'] < 1) {
                continue;
            }

            $leaderPostgreSqlConnection = $usedPostgreSqlConnection;
            break;
        }

        if (!$leaderPostgreSqlConnection) {
            return null;
        }

        if ($nonPostgreSqlConnectionIsUsed) {
            throw new \RuntimeException('Cannot start Messenger Worker with a mix of PostgreSQL queues that use the pg_notify feature, and with other queues. Please either start a Worker with only pg_notify PostgreSQL queues or disable the pg_notify feature on those queues.');
        }

        // validate that all used connection have common configuration
        $leaderConnectionConfiguration = $leaderPostgreSqlConnection->getConfiguration();
        $requiredCommonConnectionConfigurationKeys = [
            'connection',
            'table_name',
            'get_notify_timeout',
        ];
        foreach ($usedPostgreSqlConnections as $usedPostgreSqlConnection) {
            $connectionConfiguration = $usedPostgreSqlConnection->getConfiguration();

            foreach ($requiredCommonConnectionConfigurationKeys as $configurationKey) {
                if ($leaderConnectionConfiguration[$configurationKey] === $connectionConfiguration[$configurationKey]) {
                    continue;
                }

                throw new RuntimeException("Cannot start Messenger Worker with a set of PostgreSQL queues that do not have common configuration. Conflict configuration key: \"{$configurationKey}\". Expected value: \"{$leaderConnectionConfiguration[$configurationKey]}\", actual value: \"{$connectionConfiguration[$configurationKey]}\"");
            }
        }

        return $leaderPostgreSqlConnection;
    }

    private function choosePgNotifyTimeout(Worker $worker): ?int
    {
        // @todo Used to choose a sleep timeout as explained in https://github.com/symfony/symfony/issues/46862, proposition 2.
        // Note: don't forget to consider 'redeliver_timeout' option in the used pg connections' configuration.

        return null;
    }
}
