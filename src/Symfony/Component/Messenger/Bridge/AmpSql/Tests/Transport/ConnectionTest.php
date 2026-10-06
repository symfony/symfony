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
use Amp\Sql\SqlQueryError;
use Amp\Sql\SqlResult;
use Amp\Sql\SqlTransaction;
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

    public function testSendBatchInsertsTheMessagesInOneTransaction()
    {
        $transaction = $this->createMock(SqlTransaction::class);
        $transaction->expects(self::once())->method('commit');
        $transaction->expects(self::never())->method('rollback');
        $connection = $this->createMock(SqlConnection::class);
        $connection->expects(self::once())->method('beginTransaction')->willReturn($transaction);
        $backend = $this->createMock(BackendInterface::class);
        $backend->expects(self::once())
            ->method('insertBatch')
            ->with($transaction, 'messages', [[base64_encode('body a'), '{"type":"A"}', 0], [base64_encode('body b'), '[]', 500]], 'queue')
            ->willReturn([15, '16']);

        $ids = (new Connection($connection, $backend, ['auto_setup' => false, 'table_name' => 'messages', 'queue_name' => 'queue']))->sendBatch(['a' => ['body a', ['type' => 'A'], 0], 'b' => ['body b', [], 500]]);

        self::assertSame(['a' => 15, 'b' => '16'], $ids);
    }

    public function testSendBatchSplitsLargeBatches()
    {
        $transaction = $this->createMock(SqlTransaction::class);
        $transaction->expects(self::once())->method('commit');
        $connection = $this->createStub(SqlConnection::class);
        $connection->method('beginTransaction')->willReturn($transaction);
        $rows = [];
        $backend = $this->createStub(BackendInterface::class);
        $backend->method('insertBatch')->willReturnCallback(static function (SqlTransaction $transaction, string $table, array $messages) use (&$rows): array {
            $rows[] = \count($messages);

            return [];
        });

        $messages = array_fill(0, 250, ['body', [], 0]);
        $messages[] = [str_repeat('a', 600000), [], 0];
        $messages[] = [str_repeat('b', 600000), [], 0];

        self::assertSame([], (new Connection($connection, $backend, ['auto_setup' => false]))->sendBatch($messages));
        self::assertSame([100, 100, 51, 1], $rows);
    }

    public function testSendBatchRollsBackWhenAStatementFails()
    {
        $error = new SqlQueryError('Deadlock found.', 'INSERT INTO messages');
        $transaction = $this->createMock(SqlTransaction::class);
        $transaction->method('isActive')->willReturn(true);
        $transaction->expects(self::never())->method('commit');
        $transaction->expects(self::once())->method('rollback');
        $connection = $this->createStub(SqlConnection::class);
        $connection->method('beginTransaction')->willReturn($transaction);
        $calls = 0;
        $backend = $this->createStub(BackendInterface::class);
        $backend->method('insertBatch')->willReturnCallback(static function () use (&$calls, $error): array {
            if (2 === ++$calls) {
                throw $error;
            }

            return [];
        });

        try {
            (new Connection($connection, $backend, ['auto_setup' => false]))->sendBatch(array_fill(0, 150, ['body', [], 0]));
            self::fail('Expected sending to fail.');
        } catch (TransportException $e) {
            self::assertSame('Could not send the messages to AMPHP SQL.', $e->getMessage());
            self::assertInstanceOf(SqlQueryError::class, $e->getPrevious());
            self::assertSame($error->getMessage(), $e->getPrevious()->getMessage());
            self::assertNotSame($error, $e->getPrevious());
        }
    }

    public function testMysqlInsertBatchUsesOneStatementWithoutIds()
    {
        $backend = new MysqlBackend();
        $now = $backend->getNowExpression();
        $transaction = $this->createMock(SqlTransaction::class);
        $transaction->expects(self::once())
            ->method('execute')
            ->with(\sprintf('INSERT INTO messages (body, headers, queue_name, created_at, available_at) VALUES (?, ?, ?, %1$s, %1$s + ?), (?, ?, ?, %1$s, %1$s + ?)', $now), ['body a', '{"type":"A"}', 'queue', 0, 'body b', '[]', 'queue', 500])
            ->willReturn($this->createStub(SqlResult::class));

        self::assertSame([], $backend->insertBatch($transaction, 'messages', [['body a', '{"type":"A"}', 0], ['body b', '[]', 500]], 'queue'));
    }
}
