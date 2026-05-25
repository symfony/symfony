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
use Doctrine\DBAL\Driver\Exception as DBALDriverException;
use Doctrine\DBAL\Exception\DatabaseObjectExistsException;
use Doctrine\DBAL\Exception\DatabaseObjectNotFoundException;
use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\NamedObject;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

abstract class AbstractSchemaListener
{
    abstract public function postGenerateSchema(GenerateSchemaEventArgs $event): void;

    /**
     * @param-immediately-invoked-callable $configurator
     */
    protected function filterSchemaChanges(Schema $schema, Connection $connection, callable $configurator): void
    {
        $filter = $connection->getConfiguration()->getSchemaAssetsFilter();

        if (null === $filter) {
            $configurator();

            return;
        }

        $getNames = static fn ($array) => array_map(static fn ($object) => $object instanceof NamedObject ? $object->getObjectName()->toString() : $object->getName(), $array);
        $previousTableNames = $getNames($schema->getTables());
        $previousSequenceNames = $getNames($schema->getSequences());

        $configurator();

        foreach (array_diff($getNames($schema->getTables()), $previousTableNames) as $addedTable) {
            if (!$filter($addedTable)) {
                $schema->dropTable($addedTable);
            }
        }

        foreach (array_diff($getNames($schema->getSequences()), $previousSequenceNames) as $addedSequence) {
            if (!$filter($addedSequence)) {
                $schema->dropSequence($addedSequence);
            }
        }
    }

    /**
     * @return \Closure(\Closure(string): mixed): bool
     */
    protected function getIsSameDatabaseChecker(Connection $connection): \Closure
    {
        return static function (\Closure $exec) use ($connection): bool {
            $schemaManager = $connection->createSchemaManager();
            $key = bin2hex(random_bytes(7));
            $table = new Table('schema_subscriber_check_');
            $table->addColumn('id', Types::INTEGER)
                ->setAutoincrement(true)
                ->setNotnull(true);
            $table->addColumn('random_key', Types::STRING)
                ->setLength(14)
                ->setNotNull(true)
            ;

            $table->addPrimaryKeyConstraint(new PrimaryKeyConstraint(null, [new UnqualifiedName(Identifier::unquoted('id'))], true));

            try {
                $schemaManager->createTable($table);
            } catch (DatabaseObjectExistsException) {
            }

            $connection->executeStatement('INSERT INTO schema_subscriber_check_ (random_key) VALUES (:key)', ['key' => $key], ['key' => Types::STRING]);

            try {
                $exec(\sprintf('DELETE FROM schema_subscriber_check_ WHERE random_key = %s', $connection->getDatabasePlatform()->quoteStringLiteral($key)));
            } catch (DBALDriverException|\PDOException) {
            }

            try {
                return !$connection->executeStatement('DELETE FROM schema_subscriber_check_ WHERE random_key = :key', ['key' => $key], ['key' => Types::STRING]);
            } finally {
                if (!$connection->executeQuery('SELECT count(id) FROM schema_subscriber_check_')->fetchOne()) {
                    try {
                        $schemaManager->dropTable('schema_subscriber_check_');
                    } catch (DatabaseObjectNotFoundException) {
                    }
                }
            }
        };
    }
}
