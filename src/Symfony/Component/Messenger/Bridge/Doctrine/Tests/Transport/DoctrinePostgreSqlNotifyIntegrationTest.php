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
use Doctrine\DBAL\Driver\PgSQL\Driver;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\PostgreSqlConnection;

/**
 * @requires extension pgsql
 *
 * @group integration
 */
class DoctrinePostgreSqlNotifyIntegrationTest extends TestCase
{
    private const NOTIFY_DELAY_IN_SECONDS = 0.3;
    private const LONG_TIMEOUT_IN_MS = 10000;
    private const MESSAGE_BODY = '{"message": "Hi"}';

    private Connection $driverConnection;
    private Connection $senderConnection;

    protected function setUp(): void
    {
        if (!$host = getenv('POSTGRES_HOST')) {
            $this->markTestSkipped('Missing POSTGRES_HOST env variable');
        }

        if (!class_exists(Driver::class)) {
            $this->markTestSkipped('The "pgsql" driver requires DBAL 3.6');
        }

        $config = new Configuration();
        $config->setSchemaManagerFactory(new DefaultSchemaManagerFactory());

        $params = (new DsnParser())->parse("pgsql://postgres:password@$host");
        $this->driverConnection = DriverManager::getConnection($params, $config);
        $this->senderConnection = DriverManager::getConnection($params, $config);
    }

    protected function tearDown(): void
    {
        if (!isset($this->senderConnection)) {
            return;
        }

        $sender = $this->senderConnection->getNativeConnection();
        while (pg_get_result($sender)) {
        }

        $this->senderConnection->createSchemaManager()->dropTable('queue_table');
        $this->senderConnection->close();
        $this->driverConnection->close();
    }

    public function testGetWakesUpOnNotify()
    {
        $connection = $this->createPostgreSqlConnection(self::LONG_TIMEOUT_IN_MS);
        $this->assertNull($connection->get());

        $startedAt = hrtime(true);
        pg_send_query($this->senderConnection->getNativeConnection(), \sprintf("SELECT pg_sleep(%F); %s; SELECT pg_notify('queue_table', 'default')", self::NOTIFY_DELAY_IN_SECONDS, $this->createInsertSql()));

        $this->assertSame(self::MESSAGE_BODY, $connection->get()['body']);
        $this->assertWaitedForTheDelay($startedAt);
    }

    public function testGetTimesOutWithoutNotify()
    {
        $connection = $this->createPostgreSqlConnection((int) (self::NOTIFY_DELAY_IN_SECONDS * 1000));
        $this->assertNull($connection->get());

        $startedAt = hrtime(true);

        $this->assertNull($connection->get());
        $this->assertWaitedForTheDelay($startedAt);
    }

    public function testGetDoesNotWaitForANotifyReceivedBeforeTheWait()
    {
        $connection = $this->createPostgreSqlConnection(self::LONG_TIMEOUT_IN_MS);
        $this->assertNull($connection->get());

        $this->driverConnection->executeStatement('LISTEN "queue_table"');
        $this->senderConnection->executeStatement($this->createInsertSql());
        $this->senderConnection->executeStatement("SELECT pg_notify('queue_table', 'default')");
        usleep(100000);

        $startedAt = hrtime(true);

        $this->assertSame(self::MESSAGE_BODY, $connection->get()['body']);
        $this->assertLessThan(self::LONG_TIMEOUT_IN_MS / 2000, $this->getElapsedSeconds($startedAt));
    }

    public function testGetReturnsNullWhenTheConnectionIsLostBeforeTheWait()
    {
        $driverConnection = new class($this->driverConnection->getParams(), new Driver(), $this->driverConnection->getConfiguration()) extends Connection {
            public \Closure $afterListen;

            public function executeStatement($sql, array $params = [], array $types = []): int
            {
                $result = parent::executeStatement($sql, $params, $types);

                if (str_starts_with($sql, 'LISTEN')) {
                    ($this->afterListen)();
                }

                return (int) $result;
            }
        };
        $driverConnection->afterListen = function () use ($driverConnection) {
            $nativeConnection = $driverConnection->getNativeConnection();
            $this->senderConnection->executeStatement(\sprintf('SELECT pg_terminate_backend(%d, 5000)', pg_get_pid($nativeConnection)));
            usleep(100000);
            // Takes the FATAL of the server out of the socket, so that the next read of libpq finds it closed
            pg_consume_input($nativeConnection);
        };

        $connection = new PostgreSqlConnection(['table_name' => 'queue_table', 'get_notify_timeout' => self::LONG_TIMEOUT_IN_MS], $driverConnection);
        $connection->setup();
        $this->assertNull($connection->get());

        $startedAt = hrtime(true);

        $this->assertNull($connection->get());
        $this->assertLessThan(self::LONG_TIMEOUT_IN_MS / 2000, $this->getElapsedSeconds($startedAt));
    }

    public function testDestructDoesNotThrowWhenTheNativeConnectionIsAlreadyClosed()
    {
        $connection = $this->createPostgreSqlConnection(1);
        $connection->get();
        $connection->get();
        $this->assertTrue($connection->isListening());

        pg_close($this->driverConnection->getNativeConnection());
        $connection->__destruct();

        $this->assertFalse($connection->isListening());

        try {
            // DBAL closes the native connection once more when it releases its driver connection
            $this->driverConnection->close();
        } catch (\Error) {
        }
    }

    private function createPostgreSqlConnection(int $getNotifyTimeout): PostgreSqlConnection
    {
        $connection = new PostgreSqlConnection(['table_name' => 'queue_table', 'get_notify_timeout' => $getNotifyTimeout], $this->driverConnection);
        $connection->setup();

        return $connection;
    }

    private function createInsertSql(): string
    {
        return \sprintf("INSERT INTO queue_table (body, headers, queue_name, created_at, available_at) VALUES ('%s', '[]', 'default', '2000-01-01', '2000-01-01')", self::MESSAGE_BODY);
    }

    private function assertWaitedForTheDelay(int|float $startedAt): void
    {
        $elapsed = $this->getElapsedSeconds($startedAt);

        $this->assertGreaterThanOrEqual(self::NOTIFY_DELAY_IN_SECONDS - 0.05, $elapsed);
        $this->assertLessThan(self::LONG_TIMEOUT_IN_MS / 2000, $elapsed);
    }

    private function getElapsedSeconds(int|float $startedAt): float
    {
        return (hrtime(true) - $startedAt) / 1e9;
    }
}
