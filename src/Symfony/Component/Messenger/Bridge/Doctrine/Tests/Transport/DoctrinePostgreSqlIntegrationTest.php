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

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\Doctrine\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\PostgreSqlConnection;

/**
 * @requires extension pdo_pgsql
 *
 * @group integration
 */
class DoctrinePostgreSqlIntegrationTest extends TestCase
{
    /** @var Connection */
    private $driverConnection;
    /** @var PostgreSqlConnection */
    private $connection;

    protected function setUp(): void
    {
        if (!$host = getenv('POSTGRES_HOST')) {
            $this->markTestSkipped('Missing POSTGRES_HOST env variable');
        }

        $this->driverConnection = DriverManager::getConnection(['url' => "pgsql://postgres:password@$host"]);
        $this->connection = new PostgreSqlConnection(['table_name' => 'queue_table'], $this->driverConnection);
        $this->connection->setup();
    }

    protected function tearDown(): void
    {
        $this->createSchemaManager()->dropTable('queue_table');
        $this->driverConnection->close();
    }

    public function testPostgreSqlConnectionSendAndGet()
    {
        $this->connection->send('{"message": "Hi"}', ['type' => DummyMessage::class]);

        $encoded = $this->connection->get();
        $this->assertEquals('{"message": "Hi"}', $encoded['body']);
        $this->assertEquals(['type' => DummyMessage::class], $encoded['headers']);

        $this->assertNull($this->connection->get());
    }

    private function createSchemaManager(): AbstractSchemaManager
    {
        return method_exists($this->driverConnection, 'createSchemaManager')
            ? $this->driverConnection->createSchemaManager()
            : $this->driverConnection->getSchemaManager();
    }
}
