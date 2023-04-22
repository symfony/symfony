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

use Doctrine\DBAL\Cache\ArrayResult;
use Doctrine\DBAL\Cache\ArrayStatement;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\PostgreSqlConnection;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class PostgreSqlConnectionTest extends TestCase
{
    public function testSerialize()
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Cannot serialize '.PostgreSqlConnection::class);

        $driverConnection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $driverConnection->method('executeStatement')->willReturn(1);

        $connection = new PostgreSqlConnection([], $driverConnection);
        serialize($connection);
    }

    public function testUnserialize()
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Cannot unserialize '.PostgreSqlConnection::class);

        $driverConnection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $driverConnection->method('executeStatement')->willReturn(1);

        $connection = new PostgreSqlConnection([], $driverConnection);
        $connection->__wakeup();
    }

    public function testListenOnConnection()
    {
        $driverConnection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $driverConnection->method('executeStatement')->willReturn(1);

        $driverConnection
            ->expects(self::any())
            ->method('getDatabasePlatform')
            ->willReturn(new PostgreSQLPlatform());

        $driverConnection
            ->expects(self::any())
            ->method('createQueryBuilder')
            ->willReturn(new QueryBuilder($driverConnection));

        $wrappedConnection = new class() {
            private $notifyCalls = 0;

            public function pgsqlGetNotify()
            {
                ++$this->notifyCalls;

                return false;
            }

            public function countNotifyCalls()
            {
                return $this->notifyCalls;
            }
        };

        // dbal 2.x
        if (interface_exists(Result::class)) {
            $driverConnection
                ->expects(self::exactly(2))
                ->method('getWrappedConnection')
                ->willReturn($wrappedConnection);

            $driverConnection
                ->expects(self::any())
                ->method('executeQuery')
                ->willReturn(new ArrayStatement([]));
        } else {
            // dbal 3.x
            $driverConnection
                ->expects(self::exactly(2))
                ->method('getNativeConnection')
                ->willReturn($wrappedConnection);

            $driverConnection
                ->expects(self::any())
                ->method('executeQuery')
                ->willReturn(new Result(new ArrayResult([]), $driverConnection));
        }
        $connection = new PostgreSqlConnection(['table_name' => 'queue_table'], $driverConnection);

        $connection->get(); // first time we have queueEmptiedAt === null, fallback on the parent implementation
        $connection->get();
        $connection->get();

        $this->assertSame(2, $wrappedConnection->countNotifyCalls());
    }

    public function testGetExtraSetupSql()
    {
        $driverConnection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $driverConnection->method('executeStatement')->willReturn(1);
        $connection = new PostgreSqlConnection(['table_name' => 'queue_table'], $driverConnection);

        $table = new Table('queue_table');
        $table->addOption('_symfony_messenger_table_name', 'queue_table');
        $sql = implode("\n", $connection->getExtraSetupSqlForTable($table));

        $this->assertStringContainsString('CREATE TRIGGER', $sql);

        // We MUST NOT use transaction, that will mess with the PDO in PHP 8
        $this->assertStringNotContainsString('BEGIN;', $sql);
        $this->assertStringNotContainsString('COMMIT;', $sql);
    }

    public function testTransformTableNameWithSchemaToValidProcedureName()
    {
        $driverConnection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $driverConnection->method('executeStatement')->willReturn(1);
        $connection = new PostgreSqlConnection(['table_name' => 'schema.queue_table'], $driverConnection);

        $table = new Table('schema.queue_table');
        $table->addOption('_symfony_messenger_table_name', 'schema.queue_table');
        $sql = implode("\n", $connection->getExtraSetupSqlForTable($table));

        $this->assertStringContainsString('CREATE OR REPLACE FUNCTION schema.notify_queue_table', $sql);
        $this->assertStringContainsString('FOR EACH ROW EXECUTE PROCEDURE schema.notify_queue_table()', $sql);
    }

    public function testGetExtraSetupSqlWrongTable()
    {
        $driverConnection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $driverConnection->method('executeStatement')->willReturn(1);
        $connection = new PostgreSqlConnection(['table_name' => 'queue_table'], $driverConnection);

        $table = new Table('queue_table');
        // don't set the _symfony_messenger_table_name option
        $this->assertSame([], $connection->getExtraSetupSqlForTable($table));
    }
}
