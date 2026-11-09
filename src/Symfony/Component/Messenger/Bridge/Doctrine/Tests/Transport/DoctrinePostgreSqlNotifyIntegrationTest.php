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
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\PostgreSqlConnection;

#[RequiresPhpExtension('pgsql')]
#[Group('integration')]
class DoctrinePostgreSqlNotifyIntegrationTest extends TestCase
{
    private const NOTIFY_DELAY_IN_SECONDS = 0.3;
    private const LONG_TIMEOUT_IN_MS = 10000;

    private Connection $driverConnection;
    private Connection $senderConnection;

    protected function setUp(): void
    {
        if (!$host = getenv('POSTGRES_HOST')) {
            $this->markTestSkipped('Missing POSTGRES_HOST env variable');
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

    public function testWaitForNotifyWakesUpOnNotify()
    {
        $connection = $this->createPostgreSqlConnection(self::LONG_TIMEOUT_IN_MS);
        $connection->listen();

        $startedAt = hrtime(true);
        pg_send_query($this->senderConnection->getNativeConnection(), \sprintf("SELECT pg_sleep(%F); SELECT pg_notify('queue_table', 'default')", self::NOTIFY_DELAY_IN_SECONDS));

        $this->assertTrue($connection->waitForNotify(self::LONG_TIMEOUT_IN_MS));
        $this->assertWaitedForTheDelay($startedAt);
    }

    public function testWaitForNotifyTimesOutWithoutNotify()
    {
        $connection = $this->createPostgreSqlConnection(self::LONG_TIMEOUT_IN_MS);

        $startedAt = hrtime(true);

        $this->assertFalse($connection->waitForNotify((int) (self::NOTIFY_DELAY_IN_SECONDS * 1000)));
        $this->assertWaitedForTheDelay($startedAt);
    }

    public function testWaitForNotifyDoesNotWaitForANotifyReceivedBeforeTheWait()
    {
        $connection = $this->createPostgreSqlConnection(self::LONG_TIMEOUT_IN_MS);
        $connection->listen();

        $this->senderConnection->executeStatement("SELECT pg_notify('queue_table', 'default')");
        usleep(100000);

        $startedAt = hrtime(true);

        $this->assertTrue($connection->waitForNotify(self::LONG_TIMEOUT_IN_MS));
        $this->assertLessThan(self::LONG_TIMEOUT_IN_MS / 2000, $this->getElapsedSeconds($startedAt));
    }

    public function testWaitForNotifyReturnsFalseWhenTheConnectionIsLostBeforeTheWait()
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

        $startedAt = hrtime(true);

        $this->assertFalse($connection->waitForNotify(self::LONG_TIMEOUT_IN_MS));
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
