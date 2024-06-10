<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Doctrine\Tests\Transport;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\PostgreSqlConnection;

/**
 * This test checks on a postgres connection whether the doctrine asset filter works as expected.
 *
 * @requires extension pdo_pgsql
 *
 * @group integration
 */
class DoctrinePostgreSqlFilterIntegrationTest extends TestCase
{
    private Connection $driverConnection;

    protected function setUp(): void
    {
        if (!$host = getenv('POSTGRES_HOST')) {
            $this->markTestSkipped('Missing POSTGRES_HOST env variable');
        }

        $url = "pdo-pgsql://postgres:password@$host";
        $params = (new DsnParser())->parse($url);
        $config = new Configuration();
        if (class_exists(DefaultSchemaManagerFactory::class)) {
            $config->setSchemaManagerFactory(new DefaultSchemaManagerFactory());
        }

        $this->driverConnection = DriverManager::getConnection($params, $config);

        $this->createAssets();
    }

    protected function tearDown(): void
    {
        $this->removeAssets();

        $this->driverConnection->close();
    }

    public function testFilterAssets()
    {
        $schemaManager = $this->driverConnection->createSchemaManager();

        $this->assertFalse($schemaManager->tablesExist(['queue_table']));
        $this->assertTrue($schemaManager->tablesExist(['app_table']));
        $this->assertTrue($this->hasSequence('app_table_id'));

        $connection = new PostgreSqlConnection(['table_name' => 'queue_table'], $this->driverConnection);
        $connection->setup();

        $schemaManager = $this->driverConnection->createSchemaManager();

        $this->assertTrue($schemaManager->tablesExist(['queue_table']));
        $this->assertTrue($schemaManager->tablesExist(['app_table']));
        $this->assertTrue($this->hasSequence('app_table_id'));
    }

    private function createAssets(): void
    {
        $this->removeAssets();

        $schemaManager = $this->driverConnection->createSchemaManager();
        $schemaManager->createTable(new Table('app_table', [new Column('id', Type::getType('integer'))]));
        $schemaManager->createSequence(new Sequence('app_table_id'));
    }

    private function removeAssets(): void
    {
        $schemaManager = $this->driverConnection->createSchemaManager();

        if ($schemaManager->tablesExist(['queue_table'])) {
            $schemaManager->dropTable('queue_table');
        }

        if ($schemaManager->tablesExist(['app_table'])) {
            $schemaManager->dropTable('app_table');
        }

        if ($this->hasSequence('app_table_id')) {
            $schemaManager->dropSequence('app_table_id');
        }
    }

    private function hasSequence(string $name): bool
    {
        $schemaManager = $this->driverConnection->createSchemaManager();

        $sequences = $schemaManager->listSequences();
        foreach ($sequences as $sequence) {
            if ($sequence->getName() === $name) {
                return true;
            }
        }

        return false;
    }
}
