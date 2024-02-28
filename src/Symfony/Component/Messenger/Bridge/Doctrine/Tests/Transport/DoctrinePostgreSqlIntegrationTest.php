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
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Doctrine\DBAL\Tools\DsnParser;
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
    private Connection $driverConnection;
    private PostgreSqlConnection $connection;

    protected function setUp(): void
    {
        if (!$host = getenv('POSTGRES_HOST')) {
            $this->markTestSkipped('Missing POSTGRES_HOST env variable');
        }

        $url = "pdo-pgsql://postgres:password@$host";
        $params = class_exists(DsnParser::class) ? (new DsnParser())->parse($url) : ['url' => $url];
        $config = new Configuration();
        if (class_exists(DefaultSchemaManagerFactory::class)) {
            $config->setSchemaManagerFactory(new DefaultSchemaManagerFactory());
        }

        $this->driverConnection = DriverManager::getConnection($params, $config);
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

    public function testSkipLocked()
    {
        $connection = new PostgreSqlConnection(['table_name' => 'queue_table', 'skip_locked' => true], $this->driverConnection);

        $connection->send('{"message": "Hi"}', ['type' => DummyMessage::class]);

        $encoded = $connection->get();
        $this->assertEquals('{"message": "Hi"}', $encoded['body']);
        $this->assertEquals(['type' => DummyMessage::class], $encoded['headers']);

        $this->assertNull($connection->get());
    }

    private function createSchemaManager(): AbstractSchemaManager
    {
        return method_exists($this->driverConnection, 'createSchemaManager')
            ? $this->driverConnection->createSchemaManager()
            : $this->driverConnection->getSchemaManager();
    }
}
