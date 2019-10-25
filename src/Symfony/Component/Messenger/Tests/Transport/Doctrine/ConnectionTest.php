<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\Doctrine;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\SchemaConfig;
use Doctrine\DBAL\Schema\Synchronizer\SchemaSynchronizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\Doctrine\Connection;

class ConnectionTest extends TestCase
{
    public function testGetAMessageWillChangeItsStatus()
    {
        $queryBuilder = $this->getQueryBuilderMock();
        $driverConnection = $this->getDBALConnectionMock();
        $schemaSynchronizer = $this->getSchemaSynchronizerMock();
        $stmt = $this->getStatementMock([
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

        $connection = new Connection([], $driverConnection, $schemaSynchronizer);
        $doctrineEnvelope = $connection->get();
        $this->assertEquals(1, $doctrineEnvelope['id']);
        $this->assertEquals('{"message":"Hi"}', $doctrineEnvelope['body']);
        $this->assertEquals(['type' => DummyMessage::class], $doctrineEnvelope['headers']);
    }

    public function testGetWithNoPendingMessageWillReturnNull()
    {
        $queryBuilder = $this->getQueryBuilderMock();
        $driverConnection = $this->getDBALConnectionMock();
        $schemaSynchronizer = $this->getSchemaSynchronizerMock();
        $stmt = $this->getStatementMock(false);

        $queryBuilder
            ->method('getParameters')
            ->willReturn([]);
        $queryBuilder
            ->method('getParameterTypes')
            ->willReturn([]);
        $driverConnection->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);
        $driverConnection->expects($this->never())
            ->method('update');
        $driverConnection
            ->method('executeQuery')
            ->willReturn($stmt);

        $connection = new Connection([], $driverConnection, $schemaSynchronizer);
        $doctrineEnvelope = $connection->get();
        $this->assertNull($doctrineEnvelope);
    }

    public function testItThrowsATransportExceptionIfItCannotAcknowledgeMessage()
    {
        $this->expectException('Symfony\Component\Messenger\Exception\TransportException');
        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->method('delete')->willThrowException(new DBALException());

        $connection = new Connection([], $driverConnection);
        $connection->ack('dummy_id');
    }

    public function testItThrowsATransportExceptionIfItCannotRejectMessage()
    {
        $this->expectException('Symfony\Component\Messenger\Exception\TransportException');
        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->method('delete')->willThrowException(new DBALException());

        $connection = new Connection([], $driverConnection);
        $connection->reject('dummy_id');
    }

    private function getDBALConnectionMock()
    {
        $driverConnection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $platform = $this->createMock(AbstractPlatform::class);
        $platform->method('getWriteLockSQL')->willReturn('FOR UPDATE');
        $configuration = $this->createMock(\Doctrine\DBAL\Configuration::class);
        $driverConnection->method('getDatabasePlatform')->willReturn($platform);
        $driverConnection->method('getConfiguration')->willReturn($configuration);

        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaConfig = $this->createMock(SchemaConfig::class);
        $schemaConfig->method('getMaxIdentifierLength')->willReturn(63);
        $schemaConfig->method('getDefaultTableOptions')->willReturn([]);
        $schemaManager->method('createSchemaConfig')->willReturn($schemaConfig);
        $driverConnection->method('getSchemaManager')->willReturn($schemaManager);

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

    private function getStatementMock($expectedResult): Statement
    {
        $stmt = $this->createMock(Statement::class);
        $stmt->expects($this->once())
            ->method('fetch')
            ->willReturn($expectedResult);

        return $stmt;
    }

    private function getSchemaSynchronizerMock(): SchemaSynchronizer
    {
        return $this->createMock(SchemaSynchronizer::class);
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

    public function buildConfigurationProvider(): iterable
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

        yield  'test options array' => [
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

        yield 'options from options array wins over options from dsn' => [
            'dsn' => 'doctrine://default?table_name=name_from_dsn&redeliver_timeout=1200&queue_name=normal&auto_setup=true',
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
        $this->expectException('Symfony\Component\Messenger\Exception\InvalidArgumentException');
        Connection::buildConfiguration('doctrine://default', ['new_option' => 'woops']);
    }

    public function testItThrowsAnExceptionIfAnExtraOptionsInDefinedInDSN()
    {
        $this->expectException('Symfony\Component\Messenger\Exception\InvalidArgumentException');
        Connection::buildConfiguration('doctrine://default?new_option=woops');
    }

    public function testFind()
    {
        $queryBuilder = $this->getQueryBuilderMock();
        $driverConnection = $this->getDBALConnectionMock();
        $schemaSynchronizer = $this->getSchemaSynchronizerMock();
        $id = 1;
        $stmt = $this->getStatementMock([
            'id' => $id,
            'body' => '{"message":"Hi"}',
            'headers' => json_encode(['type' => DummyMessage::class]),
        ]);

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
        $driverConnection
            ->method('executeQuery')
            ->willReturn($stmt);

        $connection = new Connection([], $driverConnection, $schemaSynchronizer);
        $doctrineEnvelope = $connection->find($id);
        $this->assertEquals(1, $doctrineEnvelope['id']);
        $this->assertEquals('{"message":"Hi"}', $doctrineEnvelope['body']);
        $this->assertEquals(['type' => DummyMessage::class], $doctrineEnvelope['headers']);
    }

    public function testFindAll()
    {
        $queryBuilder = $this->getQueryBuilderMock();
        $driverConnection = $this->getDBALConnectionMock();
        $schemaSynchronizer = $this->getSchemaSynchronizerMock();
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

        $stmt = $this->createMock(Statement::class);
        $stmt->expects($this->once())
            ->method('fetchAll')
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

        $connection = new Connection([], $driverConnection, $schemaSynchronizer);
        $doctrineEnvelopes = $connection->findAll();

        $this->assertEquals(1, $doctrineEnvelopes[0]['id']);
        $this->assertEquals('{"message":"Hi"}', $doctrineEnvelopes[0]['body']);
        $this->assertEquals(['type' => DummyMessage::class], $doctrineEnvelopes[0]['headers']);

        $this->assertEquals(2, $doctrineEnvelopes[1]['id']);
        $this->assertEquals('{"message":"Hi again"}', $doctrineEnvelopes[1]['body']);
        $this->assertEquals(['type' => DummyMessage::class], $doctrineEnvelopes[1]['headers']);
    }
}
