<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\AmpSql\Tests\Transport;

use Amp\Sql\SqlConnection;
use Amp\Sql\SqlConnectionException;
use Amp\Sql\SqlResult;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\AmpSql\Transport\Backend\BackendInterface;
use Symfony\Component\Messenger\Bridge\AmpSql\Transport\Backend\MysqlBackend;
use Symfony\Component\Messenger\Bridge\AmpSql\Transport\Backend\PostgresBackend;
use Symfony\Component\Messenger\Bridge\AmpSql\Transport\Backend\SqliteBackend;
use Symfony\Component\Messenger\Bridge\AmpSql\Transport\Connection;
use Symfony\Component\Messenger\Exception\TransportException;

final class ConnectionTest extends TestCase
{
    public function testUnsupportedServerVersionKeepsActionableError()
    {
        $result = $this->createStub(SqlResult::class);
        $result->method('fetchRow')->willReturn(['version' => '3.40.0']);
        $connection = $this->createStub(SqlConnection::class);
        $connection->method('query')->willReturn($result);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('AMPHP SQL requires SQLite 3.42 or newer.');

        (new Connection($connection, new SqliteBackend()))->setup();
    }

    #[DataProvider('provideBackendWithInvalidVersion')]
    public function testInvalidServerVersionHasAccurateError(BackendInterface $backend, string $message)
    {
        $result = $this->createStub(SqlResult::class);
        $result->method('fetchRow')->willReturn(['version' => null]);
        $connection = $this->createStub(SqlConnection::class);
        $connection->method('query')->willReturn($result);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage($message);

        $backend->validateVersion($connection);
    }

    public static function provideBackendWithInvalidVersion(): iterable
    {
        yield 'SQLite' => [new SqliteBackend(), 'Could not determine the SQLite version.'];
        yield 'MySQL' => [new MysqlBackend(), 'Could not determine the MySQL server version.'];
        yield 'PostgreSQL' => [new PostgresBackend(), 'Could not determine the PostgreSQL server version.'];
    }

    public function testServerVersionQueryErrorIsWrapped()
    {
        $error = new SqlConnectionException('Connection failed.');
        $connection = $this->createStub(SqlConnection::class);
        $connection->method('query')->willThrowException($error);

        try {
            (new Connection($connection, new SqliteBackend(), ['auto_setup' => false]))->getMessageCount();
            self::fail('Expected version validation to fail.');
        } catch (TransportException $e) {
            self::assertInstanceOf(SqlConnectionException::class, $e->getPrevious());
            self::assertSame($error->getMessage(), $e->getPrevious()->getMessage());
            self::assertNotSame($error, $e->getPrevious());
        }
    }

    public function testUnsupportedMariaDbVersionIsRejected()
    {
        $result = $this->createStub(SqlResult::class);
        $result->method('fetchRow')->willReturn(['version' => '5.5.5-10.5.29-MariaDB']);
        $connection = $this->createStub(SqlConnection::class);
        $connection->method('query')->willReturn($result);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('AMPHP SQL requires MariaDB 10.6 or newer.');

        (new MysqlBackend())->validateVersion($connection);
    }

    public function testMariaDbVersionIsAccepted()
    {
        $result = $this->createStub(SqlResult::class);
        $result->method('fetchRow')->willReturn(['version' => '5.5.5-10.11.6-MariaDB']);
        $connection = $this->createStub(SqlConnection::class);
        $connection->method('query')->willReturn($result);

        (new MysqlBackend())->validateVersion($connection);

        self::addToAssertionCount(1);
    }

    public function testMysqlSetupUsesInnoDb()
    {
        $result = $this->createStub(SqlResult::class);
        $connection = $this->createMock(SqlConnection::class);
        $connection->expects(self::once())
            ->method('query')
            ->with(self::stringContains('ENGINE=InnoDB'))
            ->willReturn($result);

        (new MysqlBackend())->setup($connection, 'messages');
    }

    public function testMysqlSetupUsesCaseSensitiveQueueNames()
    {
        $result = $this->createStub(SqlResult::class);
        $connection = $this->createMock(SqlConnection::class);
        $connection->expects(self::once())
            ->method('query')
            ->with(self::stringContains('queue_name VARBINARY(190)'))
            ->willReturn($result);

        (new MysqlBackend())->setup($connection, 'messages');
    }
}
