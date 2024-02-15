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
 * If you do not want to use the LISTEN mechanism, set the `use_notify` option to `false` when calling DoctrineTransportFactory::createTransport.
 *
 * @internal
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
final class PostgreSqlConnection extends Connection
{
    /**
     * * check_delayed_interval: The interval to check for delayed messages, in milliseconds. Set to 0 to disable checks. Default: 60000 (1 minute)
     * * get_notify_timeout: The length of time to wait for a response when calling PDO::pgsqlGetNotify, in milliseconds. Default: 0.
     */
    protected const DEFAULT_OPTIONS = parent::DEFAULT_OPTIONS + [
        'check_delayed_interval' => 60000,
        'get_notify_timeout' => 0,
    ];

    public function __sleep(): array
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

        // This is secure because the table name must be a valid identifier:
        // https://www.postgresql.org/docs/current/sql-syntax-lexical.html#SQL-SYNTAX-IDENTIFIERS
        $this->executeStatement(sprintf('LISTEN "%s"', $this->configuration['table_name']));

        // The condition should be removed once support for DBAL <3.3 is dropped
        if (method_exists($this->driverConnection, 'getNativeConnection')) {
            $wrappedConnection = $this->driverConnection->getNativeConnection();
        } else {
            $wrappedConnection = $this->driverConnection;
            while (method_exists($wrappedConnection, 'getWrappedConnection')) {
                $wrappedConnection = $wrappedConnection->getWrappedConnection();
            }
        }

        $notification = $wrappedConnection->pgsqlGetNotify(\PDO::FETCH_ASSOC, $this->configuration['get_notify_timeout']);
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

        $this->executeStatement(implode("\n", $this->getTriggerSql()));
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
        $functionName = $this->createTriggerFunctionName();

        return [
            // create trigger function
            sprintf(<<<'SQL'
CREATE OR REPLACE FUNCTION %1$s() RETURNS TRIGGER AS $$
    BEGIN
        PERFORM pg_notify('%2$s', NEW.queue_name::text);
        RETURN NEW;
    END;
$$ LANGUAGE plpgsql;
SQL
                , $functionName, $this->configuration['table_name']),
            // register trigger
            sprintf('DROP TRIGGER IF EXISTS notify_trigger ON %s;', $this->configuration['table_name']),
            sprintf('CREATE TRIGGER notify_trigger AFTER INSERT OR UPDATE ON %1$s FOR EACH ROW EXECUTE PROCEDURE %2$s();', $this->configuration['table_name'], $functionName),
        ];
    }

    private function createTriggerFunctionName(): string
    {
        $tableConfig = explode('.', $this->configuration['table_name']);

        if (1 === \count($tableConfig)) {
            return sprintf('notify_%1$s', $tableConfig[0]);
        }

        return sprintf('%1$s.notify_%2$s', $tableConfig[0], $tableConfig[1]);
    }

    private function unlisten()
    {
        $this->executeStatement(sprintf('UNLISTEN "%s"', $this->configuration['table_name']));
    }
}
