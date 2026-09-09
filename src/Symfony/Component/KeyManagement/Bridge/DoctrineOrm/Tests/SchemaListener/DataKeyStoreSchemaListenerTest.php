<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Tests\SchemaListener;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\KeyManagement\Bridge\DoctrineDbal\DataKeyStore;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\SchemaListener\DataKeyStoreSchemaListener;

/**
 * The store is final, so these run against a real one on an in-memory database, which exercises
 * its configureSchema() rather than a mock of it.
 */
#[RequiresPhpExtension('pdo_sqlite')]
class DataKeyStoreSchemaListenerTest extends TestCase
{
    public function testTheTableTheStoreNeedsJoinsTheSchema()
    {
        $connection = self::connection();
        $event = $this->event($connection, new Schema());

        (new DataKeyStoreSchemaListener([self::store($connection)]))->postGenerateSchema($event);

        $table = $event->getSchema()->getTable('key_management_data_keys');

        $this->assertSame(
            ['id', 'scope', 'key_material', 'master_key_id', 'client'],
            array_map(static fn ($column) => $column->getObjectName()->toString(), $table->getColumns()),
        );
    }

    public function testTheConfiguredTableNameIsTheOneAdded()
    {
        $connection = self::connection();
        $event = $this->event($connection, new Schema());

        (new DataKeyStoreSchemaListener([self::store($connection, 'app_data_keys')]))->postGenerateSchema($event);

        $this->assertTrue($event->getSchema()->hasTable('app_data_keys'));
        $this->assertFalse($event->getSchema()->hasTable('key_management_data_keys'));
    }

    /**
     * Anything else sharing the iterator is not this listener's business.
     */
    public function testAStoreOfAnotherKindIsIgnored()
    {
        $event = $this->event(self::connection(), new Schema());

        (new DataKeyStoreSchemaListener([new \stdClass()]))->postGenerateSchema($event);

        $this->assertSame([], $event->getSchema()->getTables());
    }

    /**
     * An application that told Doctrine to ignore that table meant it, even though the table is
     * added by a listener rather than by a mapping.
     */
    public function testPostGenerateSchemaRespectsSchemaFilter()
    {
        $connection = self::connection();
        $connection->getConfiguration()->setSchemaAssetsFilter(static fn (string $table) => 'key_management_data_keys' !== $table);

        $event = $this->event($connection, new Schema());

        (new DataKeyStoreSchemaListener([self::store($connection)]))->postGenerateSchema($event);

        $this->assertFalse($event->getSchema()->hasTable('key_management_data_keys'));
    }

    private static function connection(): Connection
    {
        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    }

    private static function store(Connection $connection, string $table = DataKeyStore::DEFAULT_TABLE): DataKeyStore
    {
        return new DataKeyStore($connection, new ServiceLocator([]), 'app', 'app-key', $table);
    }

    private function event(Connection $connection, Schema $schema): GenerateSchemaEventArgs
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        return new GenerateSchemaEventArgs($entityManager, $schema);
    }
}
