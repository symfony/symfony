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
     * @param callable(): Schema $configurator returns the (possibly new) schema with the table added
     *
     * @param-immediately-invoked-callable $configurator
     */
    protected function filterSchemaChanges(Schema $schema, Connection $connection, callable $configurator): Schema
    {
        $filter = $connection->getConfiguration()->getSchemaAssetsFilter();
        $getName = static fn ($object) => $object instanceof NamedObject ? $object->getObjectName()->toString() : $object->getName();

        if (null !== $filter) {
            $previousTableNames = array_map($getName, $schema->getTables());
            $previousSequenceNames = array_map($getName, $schema->getSequences());
        }

        $newSchema = $configurator() ?? $schema;

        if (null !== $filter) {
            $tablesToFilter = [];
            foreach ($newSchema->getTables() as $table) {
                $name = $getName($table);
                if (!\in_array($name, $previousTableNames, true) && !$filter($name)) {
                    $tablesToFilter[] = $table;
                }
            }

            $sequencesToFilter = [];
            foreach ($newSchema->getSequences() as $sequence) {
                $name = $getName($sequence);
                if (!\in_array($name, $previousSequenceNames, true) && !$filter($name)) {
                    $sequencesToFilter[] = $sequence;
                }
            }

            if ($tablesToFilter || $sequencesToFilter) {
                // When doctrine/dbal minimum is bumped to ^4.5, the `else` branch
                // (and the same fallback in every configureSchema() implementation)
                // can be removed: Schema::edit() is then always available.
                if (method_exists($newSchema, 'edit')) {
                    $editor = $newSchema->edit();
                    foreach ($tablesToFilter as $table) {
                        $editor->dropTable($table->getObjectName());
                    }
                    foreach ($sequencesToFilter as $sequence) {
                        $editor->dropSequence($sequence->getObjectName());
                    }

                    $newSchema = $editor->create();
                } else {
                    foreach ($tablesToFilter as $table) {
                        $newSchema->dropTable($getName($table));
                    }
                    foreach ($sequencesToFilter as $sequence) {
                        $newSchema->dropSequence($getName($sequence));
                    }
                }
            }
        }

        // Backfill the original $schema with new tables/sequences from $newSchema so that callers holding
        // a reference to it (e.g. ORM's GenerateSchemaEventArgs prior to setSchema() being available) still
        // see the changes. Goes through the protected _addTable/_addSequence to bypass the deprecated
        // public createTable/createSequence.
        //
        // When doctrine/orm minimum is bumped to a version providing
        // GenerateSchemaEventArgs::setSchema() (>= 3.6.8), this whole block can be dropped
        // and the `if (method_exists($event, 'setSchema'))` guards in each *SchemaListener
        // subclass can become unconditional `$event->setSchema($schema);` calls.
        if ($newSchema !== $schema) {
            \Closure::bind(static function (Schema $schema) use ($newSchema, $getName): void {
                foreach ($newSchema->getTables() as $table) {
                    if (!$schema->hasTable($getName($table))) {
                        $schema->_addTable($table);
                    }
                }
                foreach ($newSchema->getSequences() as $sequence) {
                    if (!$schema->hasSequence($getName($sequence))) {
                        $schema->_addSequence($sequence);
                    }
                }
            }, null, Schema::class)($schema);
        }

        return $newSchema;
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
            $table->addColumn('id', Types::INTEGER, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('random_key', Types::STRING, ['length' => 14, 'notnull' => true]);

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
