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

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\SchemaConfig;
use Doctrine\Persistence\ConnectionRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransportFactory;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\PostgreSqlConnection;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

// Doctrine DBAL 2 compatibility
class_exists(\Doctrine\DBAL\Platforms\PostgreSqlPlatform::class);

class DoctrineTransportFactoryTest extends TestCase
{
    public function testSupports()
    {
        $factory = new DoctrineTransportFactory(
            self::createMock(ConnectionRegistry::class)
        );

        self::assertTrue($factory->supports('doctrine://default', []));
        self::assertFalse($factory->supports('amqp://localhost', []));
    }

    public function testCreateTransport()
    {
        $driverConnection = self::createMock(\Doctrine\DBAL\Connection::class);
        $schemaManager = self::createMock(AbstractSchemaManager::class);
        $schemaConfig = self::createMock(SchemaConfig::class);
        $platform = self::createMock(AbstractPlatform::class);
        $schemaManager->method('createSchemaConfig')->willReturn($schemaConfig);
        $driverConnection->method('getSchemaManager')->willReturn($schemaManager);
        $driverConnection->method('getDatabasePlatform')->willReturn($platform);
        $registry = self::createMock(ConnectionRegistry::class);

        $registry->expects(self::once())
            ->method('getConnection')
            ->willReturn($driverConnection);

        $factory = new DoctrineTransportFactory($registry);
        $serializer = self::createMock(SerializerInterface::class);

        self::assertEquals(new DoctrineTransport(new Connection(PostgreSqlConnection::buildConfiguration('doctrine://default'), $driverConnection), $serializer), $factory->createTransport('doctrine://default', [], $serializer));
    }

    public function testCreateTransportNotifyWithPostgreSQLPlatform()
    {
        $driverConnection = self::createMock(\Doctrine\DBAL\Connection::class);
        $schemaManager = self::createMock(AbstractSchemaManager::class);
        $schemaConfig = self::createMock(SchemaConfig::class);
        $platform = self::createMock(PostgreSQLPlatform::class);
        $schemaManager->method('createSchemaConfig')->willReturn($schemaConfig);
        $driverConnection->method('getSchemaManager')->willReturn($schemaManager);
        $driverConnection->method('getDatabasePlatform')->willReturn($platform);
        $registry = self::createMock(ConnectionRegistry::class);

        $registry->expects(self::once())
            ->method('getConnection')
            ->willReturn($driverConnection);

        $factory = new DoctrineTransportFactory($registry);
        $serializer = self::createMock(SerializerInterface::class);

        self::assertEquals(new DoctrineTransport(new PostgreSqlConnection(PostgreSqlConnection::buildConfiguration('doctrine://default'), $driverConnection), $serializer), $factory->createTransport('doctrine://default', [], $serializer));
    }

    public function testCreateTransportMustThrowAnExceptionIfManagerIsNotFound()
    {
        self::expectException(TransportException::class);
        self::expectExceptionMessage('Could not find Doctrine connection from Messenger DSN "doctrine://default".');
        $registry = self::createMock(ConnectionRegistry::class);
        $registry->expects(self::once())
            ->method('getConnection')
            ->willReturnCallback(function () {
                throw new \InvalidArgumentException();
            });

        $factory = new DoctrineTransportFactory($registry);
        $factory->createTransport('doctrine://default', [], self::createMock(SerializerInterface::class));
    }
}
