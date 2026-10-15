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
use Doctrine\DBAL\Driver\Result as DriverResult;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\MockObject\Stub;
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

        $driverConnection = $this->createStub(Connection::class);
        $driverConnection->method('executeStatement')->willReturn(1);

        $connection = new PostgreSqlConnection([], $driverConnection);
        serialize($connection);
    }

    public function testUnserialize()
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Cannot unserialize '.PostgreSqlConnection::class);

        $driverConnection = $this->createStub(Connection::class);
        $driverConnection->method('executeStatement')->willReturn(1);

        $connection = new PostgreSqlConnection([], $driverConnection);
        $connection->__unserialize([]);
    }

    public function testListenOnConnection()
    {
        $driverConnection = $this->createMock(Connection::class);
        $driverConnection->method('executeStatement')->willReturn(1);

        $driverConnection
            ->method('getDatabasePlatform')
            ->willReturn(new PostgreSQLPlatform());

        $driverConnection
            ->method('createQueryBuilder')
            ->willReturn(new QueryBuilder($driverConnection));

        $wrappedConnection = new class {
            private int $notifyCalls = 0;

            public function pgsqlGetNotify()
            {
                ++$this->notifyCalls;

                return false;
            }

            public function getNotify()
            {
                ++$this->notifyCalls;

                return false;
            }

            public function countNotifyCalls()
            {
                return $this->notifyCalls;
            }
        };

        $driverConnection
            ->expects(self::exactly(2))
            ->method('getNativeConnection')
            ->willReturn($wrappedConnection);

        $driverResult = $this->createStub(DriverResult::class);
        $driverResult->method('fetchAssociative')
            ->willReturn(false);
        $driverConnection
            ->method('executeQuery')
            ->willReturn(new Result($driverResult, $driverConnection));

        $connection = new PostgreSqlConnection(['table_name' => 'queue_table'], $driverConnection);

        $connection->get(); // first time we have queueEmptiedAt === null, fallback on the parent implementation
        $connection->get();
        $connection->get();

        $this->assertTrue($connection->isListening());

        $this->assertSame(2, $wrappedConnection->countNotifyCalls());

        $connection->__destruct();

        $this->assertFalse($connection->isListening());
    }

    public function testDestructDoesNotThrowWhenTheConnectionIsLost()
    {
        $driverConnection = $this->createStub(Connection::class);
        $driverConnection->method('isConnected')->willReturn(true);
        $driverConnection->method('executeStatement')->willReturnCallback(function (string $sql) {
            if (str_starts_with($sql, 'UNLISTEN')) {
                throw $this->createStub(DBALException::class);
            }

            return 0;
        });

        $connection = $this->createListeningConnection($driverConnection);
        $connection->__destruct();

        $this->assertFalse($connection->isListening());
    }

    public function testResetDoesNotReconnectToUnlistenWhenTheConnectionIsClosed()
    {
        $statements = [];
        $driverConnection = $this->createStub(Connection::class);
        $driverConnection->method('isConnected')->willReturn(false);
        $driverConnection->method('executeStatement')->willReturnCallback(static function (string $sql) use (&$statements) {
            $statements[] = $sql;

            return 0;
        });

        $connection = $this->createListeningConnection($driverConnection);
        $connection->reset();

        $this->assertFalse($connection->isListening());
        $this->assertSame(['LISTEN "queue_table"'], $statements);
    }

    public function testGetExtraSetupSqlWrongTable()
    {
        $driverConnection = $this->createStub(Connection::class);
        $driverConnection->method('executeStatement')->willReturn(1);
        $connection = new PostgreSqlConnection(['table_name' => 'queue_table'], $driverConnection);

        $table = new Table('queue_table');
        // don't set the _symfony_messenger_table_name option
        $this->assertSame([], $connection->getExtraSetupSqlForTable($table));
    }

    public function testIsListeningReturnsFalseWhenGetHasNotBeenCalled()
    {
        $driverConnection = $this->createStub(Connection::class);
        $connection = new PostgreSqlConnection(['table_name' => 'queue_table'], $driverConnection);

        $this->assertFalse($connection->isListening());
    }

    private function createListeningConnection(Connection&Stub $driverConnection): PostgreSqlConnection
    {
        $driverConnection->method('getDatabasePlatform')->willReturn(new PostgreSQLPlatform());
        $driverConnection->method('createQueryBuilder')->willReturn(new QueryBuilder($driverConnection));

        $wrappedConnection = new class {
            public function pgsqlGetNotify()
            {
                return false;
            }

            public function getNotify()
            {
                return false;
            }
        };

        $driverConnection->method('getNativeConnection')->willReturn($wrappedConnection);
        $driverResult = $this->createStub(DriverResult::class);
        $driverResult->method('fetchAssociative')->willReturn(false);
        $driverConnection->method('executeQuery')->willReturn(new Result($driverResult, $driverConnection));

        $connection = new PostgreSqlConnection(['table_name' => 'queue_table'], $driverConnection);
        $connection->get();
        $connection->get();
        $this->assertTrue($connection->isListening());

        return $connection;
    }
}
