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
use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDb1060Platform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQL57Platform;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQL100Platform;
use Doctrine\DBAL\Platforms\PostgreSQL94Platform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLServer2012Platform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Query\ForUpdate\ConflictResolutionMode;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\Doctrine\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Exception\TransportException;

class ConnectionTest extends TestCase
{
    public function testGetAMessageWillChangeItsStatus()
    {
        $queryBuilder = $this->getQueryBuilderMock();
        $driverConnection = $this->getDBALConnectionMock();
        $stmt = $this->getResultMock([
            'id' => 1,
            'body' => '{"message":"Hi"}',
            'headers' => json_encode(['type' => DummyMessage::class]),
        ]);

        $driverConnection
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);
        $queryBuilder
            ->method('getSQL')
            ->willReturn('');
        $queryBuilder
            ->method('getParameters')
            ->willReturn([]);
        $queryBuilder
            ->method('getParameterTypes')
            ->willReturn([]);
        $driverConnection
            ->method('executeQuery')
            ->willReturn($stmt);
        $driverConnection
            ->method('executeStatement')
            ->willReturn(1);

        $connection = new Connection([], $driverConnection);
        $doctrineEnvelope = $connection->get();
        $this->assertEquals(1, $doctrineEnvelope['id']);
        $this->assertEquals('{"message":"Hi"}', $doctrineEnvelope['body']);
        $this->assertEquals(['type' => DummyMessage::class], $doctrineEnvelope['headers']);
    }

    public function testGetWithNoPendingMessageWillReturnNull()
    {
        $queryBuilder = $this->getQueryBuilderMock();
        $driverConnection = $this->getDBALConnectionMock();
        $stmt = $this->getResultMock(false);

        $queryBuilder
            ->method('getParameters')
            ->willReturn([]);
        $queryBuilder
            ->method('getParameterTypes')
            ->willReturn([]);
        $queryBuilder
            ->method('getSQL')
            ->willReturn('SELECT FOR UPDATE');
        $driverConnection->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);
        $driverConnection->expects($this->never())
            ->method('update');
        $driverConnection
            ->method('executeQuery')
            ->willReturn($stmt);

        $connection = new Connection([], $driverConnection);
        $doctrineEnvelope = $connection->get();
        $this->assertNull($doctrineEnvelope);
    }

    public function testGetWithSkipLockedWithForUpdateMethod()
    {
        if (!method_exists(QueryBuilder::class, 'forUpdate')) {
            $this->markTestSkipped('This test is for when forUpdate method exists.');
        }

        $queryBuilder = $this->getQueryBuilderMock();
        $driverConnection = $this->getDBALConnectionMock();
        $stmt = $this->getResultMock(false);

        $queryBuilder
            ->method('getParameters')
            ->willReturn([]);
        $queryBuilder
            ->method('getParameterTypes')
            ->willReturn([]);
        $queryBuilder
            ->method('forUpdate')
            ->with(ConflictResolutionMode::SKIP_LOCKED)
            ->willReturn($queryBuilder);
        $queryBuilder
            ->method('getSQL')
            ->willReturn('SELECT FOR UPDATE SKIP LOCKED');
        $driverConnection->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);
        $driverConnection->expects($this->never())
            ->method('update');
        $driverConnection
            ->method('executeQuery')
            ->with($this->callback(function ($sql) {
                return str_contains($sql, 'SKIP LOCKED');
            }))
            ->willReturn($stmt);

        $connection = new Connection(['skip_locked' => true], $driverConnection);
        $doctrineEnvelope = $connection->get();
        $this->assertNull($doctrineEnvelope);
    }

    public function testGetWithSkipLockedWithoutForUpdateMethod()
    {
        if (method_exists(QueryBuilder::class, 'forUpdate')) {
            $this->markTestSkipped('This test is for when forUpdate method does not exist.');
        }

        $queryBuilder = $this->getQueryBuilderMock();
        $driverConnection = $this->getDBALConnectionMock();
        $stmt = $this->getResultMock(false);

        $queryBuilder
            ->method('getParameters')
            ->willReturn([]);
        $queryBuilder
            ->method('getParameterTypes')
            ->willReturn([]);
        $queryBuilder
            ->method('getSQL')
            ->willReturn('SELECT');
        $driverConnection->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);
        $driverConnection->expects($this->never())
            ->method('update');
        $driverConnection
            ->method('executeQuery')
            ->with($this->callback(function ($sql) {
                return str_contains($sql, 'SKIP LOCKED');
            }))
            ->willReturn($stmt);

        $connection = new Connection(['skip_locked' => true], $driverConnection);
        $doctrineEnvelope = $connection->get();
        $this->assertNull($doctrineEnvelope);
    }

    public function testItThrowsATransportExceptionIfItCannotAcknowledgeMessage()
    {
        $this->expectException(TransportException::class);
        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->method('delete')->willThrowException($this->createStub(DBALException::class));

        $connection = new Connection([], $driverConnection);
        $connection->ack('dummy_id');
    }

    public function testItThrowsATransportExceptionIfItCannotRejectMessage()
    {
        $this->expectException(TransportException::class);
        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->method('delete')->willThrowException($this->createStub(DBALException::class));

        $connection = new Connection([], $driverConnection);
        $connection->reject('dummy_id');
    }

    public function testSend()
    {
        $queryBuilder = $this->getQueryBuilderMock();
        $driverConnection = $this->getDBALConnectionMock();

        $driverConnection->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder->expects($this->once())
            ->method('insert')
            ->willReturn($queryBuilder);

        $queryBuilder->expects($this->once())
            ->method('values')
            ->with([
                'body' => '?',
                'headers' => '?',
                'queue_name' => '?',
                'created_at' => '?',
                'available_at' => '?',
            ])
            ->willReturn($queryBuilder);

        $queryBuilder->expects($this->once())
            ->method('getSQL')
            ->willReturn('INSERT');

        $driverConnection->expects($this->once())
            ->method('beginTransaction');

        $driverConnection->expects($this->once())
            ->method('executeStatement')
            ->with('INSERT')
            ->willReturn(1);

        $driverConnection->expects($this->once())
            ->method('lastInsertId')
            ->willReturn('1');

        $driverConnection->expects($this->once())
            ->method('commit');

        $connection = new Connection([], $driverConnection);
        $id = $connection->send('test', []);

        self::assertSame('1', $id);
    }

    public function testSendLastInsertIdReturnsInteger()
    {
        $queryBuilder = $this->getQueryBuilderMock();
        $driverConnection = $this->getDBALConnectionMock();

        $driverConnection->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder->expects($this->once())
            ->method('insert')
            ->willReturn($queryBuilder);

        $queryBuilder->expects($this->once())
            ->method('values')
            ->with([
                'body' => '?',
                'headers' => '?',
                'queue_name' => '?',
                'created_at' => '?',
                'available_at' => '?',
            ])
            ->willReturn($queryBuilder);

        $queryBuilder->expects($this->once())
            ->method('getSQL')
            ->willReturn('INSERT');

        $driverConnection->expects($this->once())
            ->method('beginTransaction');

        $driverConnection->expects($this->once())
            ->method('executeStatement')
            ->with('INSERT')
            ->willReturn(1);

        $driverConnection->expects($this->once())
            ->method('lastInsertId')
            ->willReturn(1);

        $driverConnection->expects($this->once())
            ->method('commit');

        $connection = new Connection([], $driverConnection);
        $id = $connection->send('test', []);

        self::assertSame('1', $id);
    }

    private function getDBALConnectionMock()
    {
        $driverConnection = $this->createMock(DBALConnection::class);
        $platform = $this->createMock(AbstractPlatform::class);

        if (!method_exists(QueryBuilder::class, 'forUpdate')) {
            $platform->method('getWriteLockSQL')->willReturn('FOR UPDATE SKIP LOCKED');
        }

        $configuration = $this->createMock(Configuration::class);
        $driverConnection->method('getDatabasePlatform')->willReturn($platform);
        $driverConnection->method('getConfiguration')->willReturn($configuration);

        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaConfig = $this->createMock(SchemaConfig::class);
        $schemaConfig->method('getMaxIdentifierLength')->willReturn(63);
        $schemaConfig->method('getDefaultTableOptions')->willReturn([]);
        $schemaManager->method('createSchemaConfig')->willReturn($schemaConfig);
        $driverConnection->method('createSchemaManager')->willReturn($schemaManager);

        return $driverConnection;
    }

    private function getQueryBuilderMock()
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);

        $queryBuilder->method('select')->willReturn($queryBuilder);
        $queryBuilder->method('update')->willReturn($queryBuilder);
        $queryBuilder->method('from')->willReturn($queryBuilder);
        $queryBuilder->method('set')->willReturn($queryBuilder);
        $queryBuilder->method('where')->willReturn($queryBuilder);
        $queryBuilder->method('andWhere')->willReturn($queryBuilder);
        $queryBuilder->method('orderBy')->willReturn($queryBuilder);
        $queryBuilder->method('setMaxResults')->willReturn($queryBuilder);
        $queryBuilder->method('setParameter')->willReturn($queryBuilder);
        $queryBuilder->method('setParameters')->willReturn($queryBuilder);

        return $queryBuilder;
    }

    private function getResultMock($expectedResult): Result&MockObject
    {
        $stmt = $this->createMock(Result::class);

        $stmt->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn($expectedResult);

        return $stmt;
    }

    /**
     * @dataProvider buildConfigurationProvider
     */
    public function testBuildConfiguration(string $dsn, array $options, string $expectedConnection, string $expectedTableName, int $expectedRedeliverTimeout, string $expectedQueue, bool $expectedAutoSetup)
    {
        $config = Connection::buildConfiguration($dsn, $options);
        $this->assertEquals($expectedConnection, $config['connection']);
        $this->assertEquals($expectedTableName, $config['table_name']);
        $this->assertEquals($expectedRedeliverTimeout, $config['redeliver_timeout']);
        $this->assertEquals($expectedQueue, $config['queue_name']);
        $this->assertEquals($expectedAutoSetup, $config['auto_setup']);
    }

    public static function buildConfigurationProvider(): iterable
    {
        yield 'no options' => [
            'dsn' => 'doctrine://default',
            'options' => [],
            'expectedConnection' => 'default',
            'expectedTableName' => 'messenger_messages',
            'expectedRedeliverTimeout' => 3600,
            'expectedQueue' => 'default',
            'expectedAutoSetup' => true,
        ];

        yield 'test options array' => [
            'dsn' => 'doctrine://default',
            'options' => [
                'table_name' => 'name_from_options',
                'redeliver_timeout' => 1800,
                'queue_name' => 'important',
                'auto_setup' => false,
            ],
            'expectedConnection' => 'default',
            'expectedTableName' => 'name_from_options',
            'expectedRedeliverTimeout' => 1800,
            'expectedQueue' => 'important',
            'expectedAutoSetup' => false,
        ];

        yield 'options from dsn' => [
            'dsn' => 'doctrine://default?table_name=name_from_dsn&redeliver_timeout=1200&queue_name=normal&auto_setup=false',
            'options' => [],
            'expectedConnection' => 'default',
            'expectedTableName' => 'name_from_dsn',
            'expectedRedeliverTimeout' => 1200,
            'expectedQueue' => 'normal',
            'expectedAutoSetup' => false,
        ];

        yield 'options from dsn array wins over options from options' => [
            'dsn' => 'doctrine://default?table_name=name_from_dsn&redeliver_timeout=1200&queue_name=normal&auto_setup=true',
            'options' => [
                'table_name' => 'name_from_options',
                'redeliver_timeout' => 1800,
                'queue_name' => 'important',
                'auto_setup' => false,
            ],
            'expectedConnection' => 'default',
            'expectedTableName' => 'name_from_dsn',
            'expectedRedeliverTimeout' => 1200,
            'expectedQueue' => 'normal',
            'expectedAutoSetup' => true,
        ];

        yield 'options from dsn with falsey boolean' => [
            'dsn' => 'doctrine://default?auto_setup=0',
            'options' => [],
            'expectedConnection' => 'default',
            'expectedTableName' => 'messenger_messages',
            'expectedRedeliverTimeout' => 3600,
            'expectedQueue' => 'default',
            'expectedAutoSetup' => false,
        ];

        yield 'options from dsn with thruthy boolean' => [
            'dsn' => 'doctrine://default?auto_setup=1',
            'options' => [],
            'expectedConnection' => 'default',
            'expectedTableName' => 'messenger_messages',
            'expectedRedeliverTimeout' => 3600,
            'expectedQueue' => 'default',
            'expectedAutoSetup' => true,
        ];
    }

    public function testItThrowsAnExceptionIfAnExtraOptionsInDefined()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown option found: [new_option]. Allowed options are [table_name, queue_name, redeliver_timeout, auto_setup]');
        Connection::buildConfiguration('doctrine://default', ['new_option' => 'woops']);
    }

    public function testItThrowsAnExceptionIfAnExtraOptionsInDefinedInDSN()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown option found in DSN: [new_option]. Allowed options are [table_name, queue_name, redeliver_timeout, auto_setup]');
        Connection::buildConfiguration('doctrine://default?new_option=woops');
    }

    public function testFind()
    {
        $queryBuilder = $this->getQueryBuilderMock();
        $driverConnection = $this->getDBALConnectionMock();
        $id = 1;
        $stmt = $this->getResultMock([
            'id' => $id,
            'body' => '{"message":"Hi"}',
            'headers' => json_encode(['type' => DummyMessage::class]),
        ]);

        $driverConnection
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);
        $queryBuilder
            ->method('where')
            ->with('m.id = ? and m.queue_name = ?')
            ->willReturn($queryBuilder);
        $queryBuilder
            ->method('getSQL')
            ->willReturn('');
        $queryBuilder
            ->method('getParameters')
            ->willReturn([]);
        $driverConnection
            ->method('executeQuery')
            ->willReturn($stmt);

        $connection = new Connection([], $driverConnection);
        $doctrineEnvelope = $connection->find($id);
        $this->assertEquals(1, $doctrineEnvelope['id']);
        $this->assertEquals('{"message":"Hi"}', $doctrineEnvelope['body']);
        $this->assertEquals(['type' => DummyMessage::class], $doctrineEnvelope['headers']);
    }

    public function testFindAll()
    {
        $queryBuilder = $this->getQueryBuilderMock();
        $driverConnection = $this->getDBALConnectionMock();
        $message1 = [
            'id' => 1,
            'body' => '{"message":"Hi"}',
            'headers' => json_encode(['type' => DummyMessage::class]),
        ];
        $message2 = [
            'id' => 2,
            'body' => '{"message":"Hi again"}',
            'headers' => json_encode(['type' => DummyMessage::class]),
        ];

        $stmt = $this->createMock(Result::class);
        $stmt->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([$message1, $message2]);

        $driverConnection
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);
        $queryBuilder
            ->method('where')
            ->willReturn($queryBuilder);
        $queryBuilder
            ->method('getSQL')
            ->willReturn('');
        $queryBuilder
            ->method('getParameters')
            ->willReturn([]);
        $queryBuilder
            ->method('getParameterTypes')
            ->willReturn([]);
        $driverConnection
            ->method('executeQuery')
            ->willReturn($stmt);

        $connection = new Connection([], $driverConnection);
        $doctrineEnvelopes = $connection->findAll();

        $this->assertEquals(1, $doctrineEnvelopes[0]['id']);
        $this->assertEquals('{"message":"Hi"}', $doctrineEnvelopes[0]['body']);
        $this->assertEquals(['type' => DummyMessage::class], $doctrineEnvelopes[0]['headers']);

        $this->assertEquals(2, $doctrineEnvelopes[1]['id']);
        $this->assertEquals('{"message":"Hi again"}', $doctrineEnvelopes[1]['body']);
        $this->assertEquals(['type' => DummyMessage::class], $doctrineEnvelopes[1]['headers']);
    }

    /**
     * @dataProvider providePlatformSql
     */
    public function testGeneratedSql(AbstractPlatform $platform, string $expectedSql)
    {
        $driverConnection = $this->createMock(DBALConnection::class);
        $driverConnection->method('getDatabasePlatform')->willReturn($platform);
        $driverConnection->method('createQueryBuilder')->willReturnCallback(fn () => new QueryBuilder($driverConnection));

        $result = $this->createMock(Result::class);
        $result->method('fetchAssociative')->willReturn(false);

        $driverConnection->expects($this->once())->method('beginTransaction');
        $driverConnection
            ->expects($this->once())
            ->method('executeQuery')
            ->with($this->callback(function ($sql) use ($expectedSql) {
                return trim($expectedSql) === trim($sql);
            }))
            ->willReturn($result)
        ;
        $driverConnection->expects($this->once())->method('commit');

        $connection = new Connection([], $driverConnection);
        $connection->get();
    }

    public static function providePlatformSql(): iterable
    {
        yield 'MySQL' => [
            class_exists(MySQLPlatform::class) ? new MySQLPlatform() : new MySQL57Platform(),
            'SELECT m.* FROM messenger_messages m WHERE (m.queue_name = ?) AND (m.delivered_at is null OR m.delivered_at < ?) AND (m.available_at <= ?) ORDER BY available_at ASC LIMIT 1 FOR UPDATE',
        ];

        if (class_exists(MySQL80Platform::class) && !method_exists(QueryBuilder::class, 'forUpdate')) {
            yield 'MySQL8 & DBAL<3.8' => [
                new MySQL80Platform(),
                'SELECT m.* FROM messenger_messages m WHERE (m.queue_name = ?) AND (m.delivered_at is null OR m.delivered_at < ?) AND (m.available_at <= ?) ORDER BY available_at ASC LIMIT 1 FOR UPDATE',
            ];
        }

        if (class_exists(MySQL80Platform::class) && method_exists(QueryBuilder::class, 'forUpdate')) {
            yield 'MySQL8 & DBAL>=3.8' => [
                new MySQL80Platform(),
                'SELECT m.* FROM messenger_messages m WHERE (m.queue_name = ?) AND (m.delivered_at is null OR m.delivered_at < ?) AND (m.available_at <= ?) ORDER BY available_at ASC LIMIT 1 FOR UPDATE SKIP LOCKED',
            ];
        }

        yield 'MariaDB' => [
            new MariaDBPlatform(),
            'SELECT m.* FROM messenger_messages m WHERE (m.queue_name = ?) AND (m.delivered_at is null OR m.delivered_at < ?) AND (m.available_at <= ?) ORDER BY available_at ASC LIMIT 1 FOR UPDATE',
        ];

        if (class_exists(MariaDb1060Platform::class)) {
            yield 'MariaDB106' => [
                new MariaDb1060Platform(),
                'SELECT m.* FROM messenger_messages m WHERE (m.queue_name = ?) AND (m.delivered_at is null OR m.delivered_at < ?) AND (m.available_at <= ?) ORDER BY available_at ASC LIMIT 1 FOR UPDATE SKIP LOCKED',
            ];
        }

        if (class_exists(MySQL57Platform::class)) {
            yield 'Postgres & DBAL<4' => [
                new PostgreSQLPlatform(),
                'SELECT m.* FROM messenger_messages m WHERE (m.queue_name = ?) AND (m.delivered_at is null OR m.delivered_at < ?) AND (m.available_at <= ?) ORDER BY available_at ASC LIMIT 1 FOR UPDATE',
            ];
        } else {
            yield 'Postgres & DBAL>=4' => [
                new PostgreSQLPlatform(),
                'SELECT m.* FROM messenger_messages m WHERE (m.queue_name = ?) AND (m.delivered_at is null OR m.delivered_at < ?) AND (m.available_at <= ?) ORDER BY available_at ASC LIMIT 1 FOR UPDATE SKIP LOCKED',
            ];
        }

        if (class_exists(PostgreSQL94Platform::class)) {
            yield 'Postgres94' => [
                new PostgreSQL94Platform(),
                'SELECT m.* FROM messenger_messages m WHERE (m.queue_name = ?) AND (m.delivered_at is null OR m.delivered_at < ?) AND (m.available_at <= ?) ORDER BY available_at ASC LIMIT 1 FOR UPDATE',
            ];
        }

        if (class_exists(PostgreSQL100Platform::class) && !method_exists(QueryBuilder::class, 'forUpdate')) {
            yield 'Postgres10 & DBAL<3.8' => [
                new PostgreSQL100Platform(),
                'SELECT m.* FROM messenger_messages m WHERE (m.queue_name = ?) AND (m.delivered_at is null OR m.delivered_at < ?) AND (m.available_at <= ?) ORDER BY available_at ASC LIMIT 1 FOR UPDATE',
            ];
        }

        if (class_exists(PostgreSQL100Platform::class) && method_exists(QueryBuilder::class, 'forUpdate')) {
            yield 'Postgres10 & DBAL>=3.8' => [
                new PostgreSQL100Platform(),
                'SELECT m.* FROM messenger_messages m WHERE (m.queue_name = ?) AND (m.delivered_at is null OR m.delivered_at < ?) AND (m.available_at <= ?) ORDER BY available_at ASC LIMIT 1 FOR UPDATE SKIP LOCKED',
            ];
        }

        if (!method_exists(QueryBuilder::class, 'forUpdate')) {
            yield 'SQL Server & DBAL<3.8' => [
                class_exists(SQLServerPlatform::class) && !class_exists(SQLServer2012Platform::class) ? new SQLServerPlatform() : new SQLServer2012Platform(),
                'SELECT m.* FROM messenger_messages m WITH (UPDLOCK, ROWLOCK) WHERE (m.queue_name = ?) AND (m.delivered_at is null OR m.delivered_at < ?) AND (m.available_at <= ?) ORDER BY available_at ASC OFFSET 0 ROWS FETCH NEXT 1 ROWS ONLY  ',
            ];
        }

        if (method_exists(QueryBuilder::class, 'forUpdate')) {
            yield 'SQL Server & DBAL>=3.8' => [
                class_exists(SQLServerPlatform::class) && !class_exists(SQLServer2012Platform::class) ? new SQLServerPlatform() : new SQLServer2012Platform(),
                'SELECT m.* FROM messenger_messages m WITH (UPDLOCK, ROWLOCK, READPAST) WHERE (m.queue_name = ?) AND (m.delivered_at is null OR m.delivered_at < ?) AND (m.available_at <= ?) ORDER BY available_at ASC OFFSET 0 ROWS FETCH NEXT 1 ROWS ONLY  ',
            ];
        }

        if (!method_exists(QueryBuilder::class, 'forUpdate')) {
            yield 'Oracle & DBAL<3.8' => [
                new OraclePlatform(),
                \sprintf('SELECT w.id AS "id", w.body AS "body", w.headers AS "headers", w.queue_name AS "queue_name", w.created_at AS "created_at", w.available_at AS "available_at", w.delivered_at AS "delivered_at" FROM messenger_messages w WHERE w.id IN (SELECT a.id FROM (SELECT m.id FROM messenger_messages m WHERE (m.queue_name = ?) AND (m.delivered_at is null OR m.delivered_at < ?) AND (m.available_at <= ?) ORDER BY available_at ASC) a WHERE ROWNUM <= 1) FOR UPDATE%s', method_exists(QueryBuilder::class, 'forUpdate') ? ' SKIP LOCKED' : ''),
            ];
        } elseif (class_exists(MySQL57Platform::class)) {
            yield 'Oracle & 3.8<=DBAL<4' => [
                new OraclePlatform(),
                'SELECT w.id AS "id", w.body AS "body", w.headers AS "headers", w.queue_name AS "queue_name", w.created_at AS "created_at", w.available_at AS "available_at", w.delivered_at AS "delivered_at" FROM messenger_messages w WHERE w.id IN (SELECT a.id FROM (SELECT m.id FROM messenger_messages m WHERE (m.queue_name = ?) AND (m.delivered_at is null OR m.delivered_at < ?) AND (m.available_at <= ?) ORDER BY available_at ASC) a WHERE ROWNUM <= 1) FOR UPDATE SKIP LOCKED',
            ];
        } else {
            yield 'Oracle & DBAL>=4' => [
                new OraclePlatform(),
                'SELECT w.id AS "id", w.body AS "body", w.headers AS "headers", w.queue_name AS "queue_name", w.created_at AS "created_at", w.available_at AS "available_at", w.delivered_at AS "delivered_at" FROM messenger_messages w WHERE w.id IN (SELECT m.id FROM messenger_messages m WHERE (m.queue_name = ?) AND (m.delivered_at is null OR m.delivered_at < ?) AND (m.available_at <= ?) ORDER BY available_at ASC FETCH NEXT 1 ROWS ONLY) FOR UPDATE SKIP LOCKED',
            ];
        }
    }

    public function testConfigureSchema()
    {
        $driverConnection = $this->getDBALConnectionMock();
        $schema = new Schema();

        $connection = new Connection(['table_name' => 'queue_table'], $driverConnection);
        $connection->configureSchema($schema, $driverConnection, fn () => true);
        $this->assertTrue($schema->hasTable('queue_table'));
    }

    public function testConfigureSchemaDifferentDbalConnection()
    {
        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection2 = $this->getDBALConnectionMock();
        $schema = new Schema();

        $connection = new Connection([], $driverConnection);
        $connection->configureSchema($schema, $driverConnection2, fn () => false);
        $this->assertFalse($schema->hasTable('messenger_messages'));
    }

    public function testConfigureSchemaTableExists()
    {
        $driverConnection = $this->getDBALConnectionMock();
        $schema = new Schema();
        $schema->createTable('messenger_messages');

        $connection = new Connection([], $driverConnection);
        $connection->configureSchema($schema, $driverConnection, fn () => true);
        $table = $schema->getTable('messenger_messages');
        $this->assertEmpty($table->getColumns(), 'The table was not overwritten');
    }

    /**
     * @dataProvider provideFindAllSqlGeneratedByPlatform
     */
    public function testFindAllSqlGenerated(AbstractPlatform $platform, string $expectedSql)
    {
        $driverConnection = $this->createMock(DBALConnection::class);
        $driverConnection->method('getDatabasePlatform')->willReturn($platform);
        $driverConnection->method('createQueryBuilder')->willReturnCallback(function () use ($driverConnection) {
            return new QueryBuilder($driverConnection);
        });

        $result = $this->createMock(Result::class);
        $result->method('fetchAllAssociative')->willReturn([]);

        $driverConnection
            ->expects($this->once())
            ->method('executeQuery')
            ->with($expectedSql)
            ->willReturn($result)
        ;

        $connection = new Connection([], $driverConnection);
        $connection->findAll(50);
    }

    public static function provideFindAllSqlGeneratedByPlatform(): iterable
    {
        yield 'MySQL' => [
            class_exists(MySQLPlatform::class) ? new MySQLPlatform() : new MySQL57Platform(),
            'SELECT m.* FROM messenger_messages m WHERE (m.queue_name = ?) AND (m.delivered_at is null OR m.delivered_at < ?) AND (m.available_at <= ?) LIMIT 50',
        ];

        yield 'MariaDB' => [
            new MariaDBPlatform(),
            'SELECT m.* FROM messenger_messages m WHERE (m.queue_name = ?) AND (m.delivered_at is null OR m.delivered_at < ?) AND (m.available_at <= ?) LIMIT 50',
        ];

        yield 'SQL Server' => [
            class_exists(SQLServerPlatform::class) && !class_exists(SQLServer2012Platform::class) ? new SQLServerPlatform() : new SQLServer2012Platform(),
            'SELECT m.* FROM messenger_messages m WHERE (m.queue_name = ?) AND (m.delivered_at is null OR m.delivered_at < ?) AND (m.available_at <= ?) ORDER BY (SELECT 0) OFFSET 0 ROWS FETCH NEXT 50 ROWS ONLY',
        ];

        if (!class_exists(MySQL57Platform::class)) {
            // DBAL >= 4
            yield 'Oracle' => [
                new OraclePlatform(),
                'SELECT m.id AS "id", m.body AS "body", m.headers AS "headers", m.queue_name AS "queue_name", m.created_at AS "created_at", m.available_at AS "available_at", m.delivered_at AS "delivered_at" FROM messenger_messages m WHERE (m.queue_name = ?) AND (m.delivered_at is null OR m.delivered_at < ?) AND (m.available_at <= ?) FETCH NEXT 50 ROWS ONLY',
            ];
        } else {
            // DBAL < 4
            yield 'Oracle' => [
                new OraclePlatform(),
                'SELECT a.* FROM (SELECT m.id AS "id", m.body AS "body", m.headers AS "headers", m.queue_name AS "queue_name", m.created_at AS "created_at", m.available_at AS "available_at", m.delivered_at AS "delivered_at" FROM messenger_messages m WHERE (m.queue_name = ?) AND (m.delivered_at is null OR m.delivered_at < ?) AND (m.available_at <= ?)) a WHERE ROWNUM <= 50',
            ];
        }
    }
}
