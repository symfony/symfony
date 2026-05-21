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
use Doctrine\DBAL\Exception\DatabaseObjectNotFoundException;
use Doctrine\DBAL\Schema\NamedObject;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

abstract class AbstractSchemaListener
{
    abstract public function postGenerateSchema(GenerateSchemaEventArgs $event): void;

    protected function filterSchemaChanges(Schema $schema, Connection $connection, callable $configurator): void
    {
        $filter = $connection->getConfiguration()->getSchemaAssetsFilter();

        if (null === $filter) {
            $configurator();

            return;
        }

        $getName = static fn ($object) => $object instanceof NamedObject ? $object->getObjectName()->toString() : $object->getName();
        $getNames = static fn ($array) => array_map($getName, $array);
        $previousTableNames = $getNames($schema->getTables());
        $previousSequenceNames = $getNames($schema->getSequences());

        $configurator();

        $useEditor = method_exists($schema, 'edit');

        foreach (array_diff($getNames($schema->getTables()), $previousTableNames) as $addedTable) {
            if (!$filter($addedTable)) {
                $useEditor ? $this->removeFromSchema($schema, '_tables', $addedTable, $getName) : $schema->dropTable($addedTable);
            }
        }

        foreach (array_diff($getNames($schema->getSequences()), $previousSequenceNames) as $addedSequence) {
            if (!$filter($addedSequence)) {
                $useEditor ? $this->removeFromSchema($schema, '_sequences', $addedSequence, $getName) : $schema->dropSequence($addedSequence);
            }
        }
    }

    /**
     * Drops a Table or Sequence from the Schema in place, avoiding the deprecated
     * Schema::dropTable() / dropSequence() on DBAL 4.5+.
     */
    private function removeFromSchema(Schema $schema, string $property, string $name, callable $getName): void
    {
        $items = $schema->{'_tables' === $property ? 'getTables' : 'getSequences'}();
        foreach ($items as $key => $item) {
            if ($getName($item) === $name) {
                $prop = new \ReflectionProperty(Schema::class, $property);
                $values = $prop->getValue($schema);
                unset($values[$key]);
                $prop->setValue($schema, $values);

                return;
            }
        }
    }

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
            } catch (DatabaseObjectNotFoundException) {
                return true;
            }
        };
    }
}
