<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\SchemaListener;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

abstract class AbstractSchemaListener
{
    abstract public function postGenerateSchema(GenerateSchemaEventArgs $event): void;

    protected function getIsSameDatabaseChecker(Connection $connection): \Closure
    {
        return static function (\Closure $exec) use ($connection): bool {
            $schemaManager = method_exists($connection, 'createSchemaManager') ? $connection->createSchemaManager() : $connection->getSchemaManager();
            $checkTable = 'schema_subscriber_check_'.bin2hex(random_bytes(7));
            $table = new Table($checkTable);
            $table->addColumn('id', Types::INTEGER)
                ->setAutoincrement(true)
                ->setNotnull(true);
            $table->setPrimaryKey(['id']);

            $schemaManager->createTable($table);

            try {
                $exec(\sprintf('DROP TABLE %s', $checkTable));
            } catch (\Exception) {
                // ignore
            }

            try {
                $schemaManager->dropTable($checkTable);

                return false;
            } catch (TableNotFoundException) {
                return true;
            }
        };
    }
}
