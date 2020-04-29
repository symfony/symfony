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

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

final class Migration
{
    private $tableName;

    public function __construct(string $tableName = 'messenger_messages')
    {
        $this->tableName = $tableName;
    }

    public static function up(Schema $schema, Connection $connection): void
    {
        $useDeprecatedConstants = !class_exists(Types::class);
        $table = $schema->createTable($this->tableName);
        $table->addColumn('id', $useDeprecatedConstants ? Type::BIGINT : Types::BIGINT)->setAutoincrement(true)->setNotnull(true);
        $table->addColumn('body', $useDeprecatedConstants ? Type::TEXT : Types::TEXT)->setNotnull(true);
        $table->addColumn('headers', $useDeprecatedConstants ? Type::TEXT : Types::TEXT)->setNotnull(true);
        $table->addColumn('queue_name', $useDeprecatedConstants ? Type::STRING : Types::STRING)->setNotnull(true);
        $table->addColumn('created_at', $useDeprecatedConstants ? Type::DATETIME : Types::DATETIME_MUTABLE)->setNotnull(true);
        $table->addColumn('available_at', $useDeprecatedConstants ? Type::DATETIME : Types::DATETIME_MUTABLE)->setNotnull(true);
        $table->addColumn('delivered_at', $useDeprecatedConstants ? Type::DATETIME : Types::DATETIME_MUTABLE)->setNotnull(false);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['queue_name']);
        $table->addIndex(['available_at']);
        $table->addIndex(['delivered_at']);

        if ('postgresql' === $connection->getDatabasePlatform()->getName()) {
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
            , $this->tableName);

            $connection->exec($sql);
        }
    }
}
