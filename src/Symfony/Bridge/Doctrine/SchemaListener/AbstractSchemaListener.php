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
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\DatabaseObjectExistsException;
use Doctrine\DBAL\Exception\DatabaseObjectNotFoundException;
use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

abstract class AbstractSchemaListener
{
    abstract public function postGenerateSchema(GenerateSchemaEventArgs $event): void;

    /**
     * @return \Closure(\Closure(string): mixed): bool
     */
    protected function getIsSameDatabaseChecker(Connection $connection): \Closure
    {
        return static function (\Closure $exec) use ($connection): bool {
            $schemaManager = method_exists($connection, 'createSchemaManager') ? $connection->createSchemaManager() : $connection->getSchemaManager();
            $key = bin2hex(random_bytes(7));
            $table = new Table('_schema_subscriber_check');
            $table->addColumn('id', Types::INTEGER)
                ->setAutoincrement(true)
                ->setNotnull(true);
            $table->addColumn('random_key', Types::STRING)
                ->setLength(14)
                ->setNotNull(true)
            ;

            if (class_exists(PrimaryKeyConstraint::class)) {
                $table->addPrimaryKeyConstraint(new PrimaryKeyConstraint(null, [new UnqualifiedName(Identifier::unquoted('id'))], true));
            } else {
                $table->setPrimaryKey(['id']);
            }

            try {
                $schemaManager->createTable($table);
            } catch (DatabaseObjectExistsException) {
            }

            $connection->executeStatement('INSERT INTO _schema_subscriber_check (random_key) VALUES (:key)', ['key' => $key], ['key' => Types::STRING]);

            try {
                $exec(\sprintf('DELETE FROM _schema_subscriber_check WHERE random_key = %s', $connection->getDatabasePlatform()->quoteStringLiteral($key)));
            } catch (DatabaseObjectNotFoundException|ConnectionException) {
            }

            try {
                return !$connection->executeStatement('DELETE FROM _schema_subscriber_check WHERE random_key = :key', ['key' => $key], ['key' => Types::STRING]);
            } finally {
                if (!$connection->executeQuery('SELECT count(id) FROM _schema_subscriber_check')->fetchOne()) {
                    try {
                        $schemaManager->dropTable('_schema_subscriber_check');
                    } catch (DatabaseObjectNotFoundException) {
                    }
                }
            }
        };
    }
}
