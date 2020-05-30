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

use Doctrine\DBAL\Schema\Table;

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
     * * check_delayed_interval: The interval to check for delayed messages, in milliseconds. Set to 0 to disable checks. Default: 60000 (1 minute)
     * * get_notify_timeout: The length of time to wait for a response when calling PDO::pgsqlGetNotify, in milliseconds. Default: 0.
     */
    protected const DEFAULT_OPTIONS = parent::DEFAULT_OPTIONS + [
        'check_delayed_interval' => 60000,
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
            usleep(1000);

            return null;
        }

        return parent::get();
    }

    public function setup(): void
    {
        parent::setup();

        $this->driverConnection->exec(implode("\n", $this->getTriggerSql()));
    }

    /**
     * @return string[]
     */
    public function getExtraSetupSqlForTable(Table $createdTable): array
    {
        if (!$createdTable->hasOption(self::TABLE_OPTION_NAME)) {
            return [];
        }

        if ($createdTable->getOption(self::TABLE_OPTION_NAME) !== $this->configuration['table_name']) {
            return [];
        }

        return $this->getTriggerSql();
    }

    private function getTriggerSql(): array
    {
        return [
            sprintf('LOCK TABLE %s;', $this->configuration['table_name']),
            // create trigger function
            sprintf(<<<'SQL'
CREATE OR REPLACE FUNCTION notify_%1$s() RETURNS TRIGGER AS $$
    BEGIN
        PERFORM pg_notify('%1$s', NEW.queue_name::text);
        RETURN NEW;
    END;
$$ LANGUAGE plpgsql;
SQL
            , $this->configuration['table_name']),
            // register trigger
            sprintf('DROP TRIGGER IF EXISTS notify_trigger ON %s;', $this->configuration['table_name']),
            sprintf('CREATE TRIGGER notify_trigger AFTER INSERT ON %1$s FOR EACH ROW EXECUTE PROCEDURE notify_%1$s();', $this->configuration['table_name']),
        ];
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
