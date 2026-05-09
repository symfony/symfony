<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Doctrine\Transport;

use Doctrine\DBAL\Connection as DBALConnection;

/**
 * Uses PostgreSQL LISTEN/NOTIFY to push messages to workers.
 *
 * If you do not want to use the LISTEN mechanism, set the `use_notify` option to `false` when calling DoctrineTransportFactory::createTransport.
 *
 * @internal
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
final class PostgreSqlConnection extends Connection
{
    private bool $listening = false;
    private bool $notifyHandledExternally = false;

    /**
     * * check_delayed_interval: The interval to check for delayed messages, in milliseconds. Set to 0 to disable checks. Default: 60000 (1 minute)
     * * get_notify_timeout: The maximum time to wait for a NOTIFY, in milliseconds. Default: 60000 (1 minute).
     */
    protected const DEFAULT_OPTIONS = parent::DEFAULT_OPTIONS + [
        'check_delayed_interval' => 60000,
        'get_notify_timeout' => 60000,
    ];

    public function __serialize(): array
    {
        throw new \BadMethodCallException('Cannot serialize '.__CLASS__);
    }

    public function __unserialize(array $data): void
    {
        throw new \BadMethodCallException('Cannot unserialize '.__CLASS__);
    }

    public function __destruct()
    {
        $this->unlisten();
    }

    public function isListening(): bool
    {
        return $this->listening;
    }

    public function reset(): void
    {
        parent::reset();
        $this->unlisten();
    }

    public function get(int $fetchSize = 1): ?array
    {
        if ($this->notifyHandledExternally || null === $this->queueEmptiedAt) {
            return parent::get($fetchSize);
        }

        // Fallback: when no external listener handles LISTEN/NOTIFY,
        // block here until a notification arrives or timeout expires

        // This is secure because the table name must be a valid identifier:
        // https://www.postgresql.org/docs/current/sql-syntax-lexical.html#SQL-SYNTAX-IDENTIFIERS
        $this->executeStatement(\sprintf('LISTEN "%s"', $this->configuration['table_name']));

        $this->listening = true;

        /** @var \PDO $nativeConnection */
        $nativeConnection = $this->driverConnection->getNativeConnection();
        $timeout = $this->configuration['check_delayed_interval'] - (microtime(true) * 1000 - $this->queueEmptiedAt);
        $timeout = max(0, ceil(min($this->configuration['get_notify_timeout'] ?: $timeout, $timeout)));

        $notification = $nativeConnection->getNotify(\PDO::FETCH_ASSOC, $timeout);
        if (
            // no notifications, or for another table or queue
            (false === $notification || $notification['message'] !== $this->configuration['table_name'] || $notification['payload'] !== $this->configuration['queue_name'])
            // delayed messages
            && (microtime(true) * 1000 - $this->queueEmptiedAt < $this->configuration['check_delayed_interval'])
        ) {
            return null;
        }

        return parent::get($fetchSize);
    }

    /**
     * Registers a LISTEN on the PostgreSQL connection for the configured table.
     *
     * When called, also disables the internal LISTEN/NOTIFY blocking in get(),
     * assuming an external listener (e.g. PostgreSqlNotifyOnIdleListener) handles it.
     *
     * Safe to call multiple times; PostgreSQL ignores duplicate LISTEN for the same channel.
     *
     * @param bool $registerOnDatabase Whether to execute the SQL LISTEN command.
     *                                 When false, only marks get() as externally handled
     *                                 without registering on the database. This avoids
     *                                 accumulating unread notifications on connections
     *                                 that will never call waitForNotify().
     */
    public function listen(bool $registerOnDatabase = true): void
    {
        if ($registerOnDatabase) {
            // This is secure because the table name must be a valid identifier:
            // https://www.postgresql.org/docs/current/sql-syntax-lexical.html#SQL-SYNTAX-IDENTIFIERS
            $this->executeStatement(\sprintf('LISTEN "%s"', $this->configuration['table_name']));
            $this->listening = true;
        }
        $this->notifyHandledExternally = true;
    }

    public function getDriverConnection(): DBALConnection
    {
        return $this->driverConnection;
    }

    /**
     * Blocks until a PostgreSQL NOTIFY is received or the timeout expires.
     *
     * Automatically registers a LISTEN before waiting to handle reconnections.
     *
     * @param int $timeoutMs The maximum time to wait in milliseconds
     *
     * @return bool True if a notification was received, false on timeout
     */
    public function waitForNotify(int $timeoutMs): bool
    {
        $this->listen();

        /** @var \PDO $nativeConnection */
        $nativeConnection = $this->driverConnection->getNativeConnection();

        return false !== $nativeConnection->getNotify(\PDO::FETCH_ASSOC, $timeoutMs);
    }

    private function unlisten(): void
    {
        if (!$this->listening) {
            return;
        }

        $this->executeStatement(\sprintf('UNLISTEN "%s"', $this->configuration['table_name']));
        $this->listening = false;
    }
}
