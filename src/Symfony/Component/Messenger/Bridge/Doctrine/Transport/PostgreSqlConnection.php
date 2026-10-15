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

use Doctrine\DBAL\Exception as DBALException;

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

    /**
     * * check_delayed_interval: The interval to check for delayed messages, in milliseconds. Set to 0 to disable checks. Default: 60000 (1 minute)
     * * get_notify_timeout: The length of time to wait for a response when calling PDO::pgsqlGetNotify (or Pdo\Pgsql::getNotify on PHP 8.4+), in milliseconds. Default: 0.
     */
    protected const DEFAULT_OPTIONS = parent::DEFAULT_OPTIONS + [
        'check_delayed_interval' => 60000,
        'get_notify_timeout' => 0,
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
        try {
            $this->unlisten();
        } catch (DBALException) {
            // a broken connection does not keep its LISTEN
        }
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

    public function get(): ?array
    {
        if (null === $this->queueEmptiedAt) {
            return parent::get();
        }

        // This is secure because the table name must be a valid identifier:
        // https://www.postgresql.org/docs/current/sql-syntax-lexical.html#SQL-SYNTAX-IDENTIFIERS
        $this->executeStatement(\sprintf('LISTEN "%s"', $this->configuration['table_name']));

        $this->listening = true;

        // The condition should be removed once support for DBAL <3.3 is dropped
        if (method_exists($this->driverConnection, 'getNativeConnection')) {
            $wrappedConnection = $this->driverConnection->getNativeConnection();
        } else {
            $wrappedConnection = $this->driverConnection;
            while (method_exists($wrappedConnection, 'getWrappedConnection')) {
                $wrappedConnection = $wrappedConnection->getWrappedConnection();
            }
        }

        $notification = \PHP_VERSION_ID >= 80500
            ? $wrappedConnection->getNotify(\PDO::FETCH_ASSOC, $this->configuration['get_notify_timeout'])
            : $wrappedConnection->pgsqlGetNotify(\PDO::FETCH_ASSOC, $this->configuration['get_notify_timeout']);
        if (
            // no notifications, or for another table or queue
            (false === $notification || $notification['message'] !== $this->configuration['table_name'] || $notification['payload'] !== $this->configuration['queue_name'])
            // delayed messages
            && (microtime(true) * 1000 - $this->queueEmptiedAt < $this->configuration['check_delayed_interval'])
        ) {
            usleep(1000);

            return null;
        }

        return parent::get();
    }

    private function unlisten(): void
    {
        if (!$this->listening) {
            return;
        }

        $this->listening = false;

        // when the connection is closed, its LISTEN is gone too: do not reconnect just to UNLISTEN
        if ($this->driverConnection->isConnected()) {
            $this->executeStatement(\sprintf('UNLISTEN "%s"', $this->configuration['table_name']));
        }
    }
}
