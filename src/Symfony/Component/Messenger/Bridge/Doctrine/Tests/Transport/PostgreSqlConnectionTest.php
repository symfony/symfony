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
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\PostgreSqlConnection;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class PostgreSqlConnectionTest extends TestCase
{
    public function testSerialize(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Cannot serialize '.PostgreSqlConnection::class);

        $driverConnection = $this->createStub(Connection::class);
        $driverConnection->method('executeStatement')->willReturn(1);

        $connection = new PostgreSqlConnection([], $driverConnection);
        serialize($connection);
    }

    public function testUnserialize(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Cannot unserialize '.PostgreSqlConnection::class);

        $driverConnection = $this->createStub(Connection::class);
        $driverConnection->method('executeStatement')->willReturn(1);

        $connection = new PostgreSqlConnection([], $driverConnection);
        $connection->__unserialize([]);
    }

    public function testListenOnConnection(): void
    {
        $driverConnection = $this->createMock(Connection::class);
        $driverConnection->method('executeStatement')->willReturn(1);

        $driverConnection
            ->expects(self::any())
            ->method('getDatabasePlatform')
            ->willReturn(new PostgreSQLPlatform());

        $driverConnection
            ->expects(self::any())
            ->method('createQueryBuilder')
            ->willReturn(new QueryBuilder($driverConnection));

        $wrappedConnection = new class {
            private int $notifyCalls = 0;

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
            ->expects(self::any())
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

    public function testIsListeningReturnsFalseWhenGetHasNotBeenCalled(): void
    {
        $driverConnection = $this->createStub(\Doctrine\DBAL\Connection::class);
        $connection = new PostgreSqlConnection(['table_name' => 'queue_table'], $driverConnection);

        $this->assertFalse($connection->isListening());
    }
}
