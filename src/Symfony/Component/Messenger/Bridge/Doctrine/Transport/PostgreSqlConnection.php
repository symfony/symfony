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

/**
 * Uses PostgreSQL LISTEN/NOTIFY to push messages to workers.
 *
 * @internal
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
final class PostgreSqlConnection extends Connection
{
    /**
     * * use_notify: Set to false to disable the use of LISTEN/NOTIFY. Default: true
     * * check_delayed_interval: The interval to check for delayed messages, in milliseconds. Set to 0 to disable checks. Default: 1000
     * * get_notify_timeout: The length of time to wait for a response when calling PDO::pgsqlGetNotify, in milliseconds. Default: 0.
     */
    protected const DEFAULT_OPTIONS = parent::DEFAULT_OPTIONS + [
        'check_delayed_interval' => 1000,
        'get_notify_timeout' => 0,
    ];

    private $listening = false;

    public function __sleep()
    {
        throw new \BadMethodCallException('Cannot serialize '.__CLASS__);
    }

    public function __wakeup()
    {
        throw new \BadMethodCallException('Cannot unserialize '.__CLASS__);
    }

    public function __destruct()
    {
        $this->unlisten();
    }

    public function reset()
    {
        parent::reset();
        $this->unlisten();
    }

    public function get(): ?array
    {
        if (null === $this->queueEmptiedAt) {
            return parent::get();
        }

        if (!$this->listening) {
            // This is secure because the table name must be a valid identifier:
            // https://www.postgresql.org/docs/current/sql-syntax-lexical.html#SQL-SYNTAX-IDENTIFIERS
            $this->driverConnection->exec(sprintf('LISTEN "%s"', $this->configuration['table_name']));
            $this->listening = true;
        }

        $notification = $this->driverConnection->getWrappedConnection()->pgsqlGetNotify(\PDO::FETCH_ASSOC, $this->configuration['get_notify_timeout']);
        if (
            // no notifications, or for another table or queue
            (false === $notification || $notification['message'] !== $this->configuration['table_name'] || $notification['payload'] !== $this->configuration['queue_name']) &&
            // delayed messages
            (microtime(true) * 1000 - $this->queueEmptiedAt < $this->configuration['check_delayed_interval'])
        ) {
            return null;
        }

        return parent::get();
    }

    public function setup(): void
    {
        parent::setup();

        $sql = sprintf(<<<'SQL'
LOCK TABLE %1$s;
-- create trigger function
CREATE OR REPLACE FUNCTION notify_%1$s() RETURNS TRIGGER AS $$
	BEGIN
		PERFORM pg_notify('%1$s', NEW.queue_name::text);
		RETURN NEW;
    END;
$$ LANGUAGE plpgsql;

-- register trigger
DROP TRIGGER IF EXISTS notify_trigger ON %1$s;

CREATE TRIGGER notify_trigger
AFTER INSERT
ON %1$s
FOR EACH ROW EXECUTE PROCEDURE notify_%1$s();
SQL
            , $this->configuration['table_name']);
        $this->driverConnection->exec($sql);
    }

    private function unlisten()
    {
        if (!$this->listening) {
            return;
        }

        $this->driverConnection->exec(sprintf('UNLISTEN "%s"', $this->configuration['table_name']));
        $this->listening = false;
    }
}
