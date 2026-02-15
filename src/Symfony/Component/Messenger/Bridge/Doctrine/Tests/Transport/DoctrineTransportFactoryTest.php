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
use Doctrine\Persistence\ConnectionRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\Doctrine\EventListener\PostgreSqlNotifyOnIdleListener;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransportFactory;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\PostgreSqlConnection;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class DoctrineTransportFactoryTest extends TestCase
{
    public function testSupports()
    {
        $factory = new DoctrineTransportFactory(
            $this->createStub(ConnectionRegistry::class)
        );

        $this->assertTrue($factory->supports('doctrine://default', []));
        $this->assertFalse($factory->supports('amqp://localhost', []));
    }

    public function testCreateTransport()
    {
        $driverConnection = $this->createStub(\Doctrine\DBAL\Connection::class);
        $platform = $this->createStub(AbstractPlatform::class);
        $driverConnection->method('getDatabasePlatform')->willReturn($platform);
        $registry = $this->createMock(ConnectionRegistry::class);

        $registry->expects($this->once())
            ->method('getConnection')
            ->willReturn($driverConnection);

        $factory = new DoctrineTransportFactory($registry);
        $serializer = $this->createStub(SerializerInterface::class);

        $this->assertEquals(
            new DoctrineTransport(new Connection(PostgreSqlConnection::buildConfiguration('doctrine://default'), $driverConnection), $serializer),
            $factory->createTransport('doctrine://default', [], $serializer)
        );
    }

    public function testCreateTransportNotifyWithPostgreSQLPlatform()
    {
        $driverConnection = $this->createStub(\Doctrine\DBAL\Connection::class);
        $platform = $this->createStub(PostgreSQLPlatform::class);
        $driverConnection->method('getDatabasePlatform')->willReturn($platform);
        $driverConnection->method('executeStatement')->willReturn(1);
        $registry = $this->createMock(ConnectionRegistry::class);

        $registry->expects($this->once())
            ->method('getConnection')
            ->willReturn($driverConnection);

        $factory = new DoctrineTransportFactory($registry);
        $serializer = $this->createStub(SerializerInterface::class);

        $this->assertEquals(
            new DoctrineTransport(new PostgreSqlConnection(PostgreSqlConnection::buildConfiguration('doctrine://default'), $driverConnection), $serializer),
            $factory->createTransport('doctrine://default', [], $serializer)
        );
    }

    public function testCreateTransportMustThrowAnExceptionIfManagerIsNotFound()
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Could not find Doctrine connection from Messenger DSN.');
        $registry = $this->createMock(ConnectionRegistry::class);
        $registry->expects($this->once())
            ->method('getConnection')
            ->willReturnCallback(static function () {
                throw new \InvalidArgumentException();
            });

        $factory = new DoctrineTransportFactory($registry);
        $factory->createTransport('doctrine://default', [], $this->createStub(SerializerInterface::class));
    }

    public function testCreateTransportRegistersConnectionWithListener()
    {
        $driverConnection = $this->createStub(\Doctrine\DBAL\Connection::class);
        $platform = $this->createStub(PostgreSQLPlatform::class);
        $driverConnection->method('getDatabasePlatform')->willReturn($platform);
        $driverConnection->method('executeStatement')->willReturn(1);

        $registry = $this->createStub(ConnectionRegistry::class);
        $registry->method('getConnection')->willReturn($driverConnection);

        $listener = $this->createMock(PostgreSqlNotifyOnIdleListener::class);
        $listener->expects($this->once())
            ->method('addConnection')
            ->with('my_transport', $this->isInstanceOf(PostgreSqlConnection::class));

        $factory = new DoctrineTransportFactory($registry, $listener);
        $serializer = $this->createStub(SerializerInterface::class);

        $factory->createTransport('doctrine://default', ['transport_name' => 'my_transport'], $serializer);
    }

    public function testCreateTransportDoesNotRegisterWithoutTransportName()
    {
        $driverConnection = $this->createStub(\Doctrine\DBAL\Connection::class);
        $platform = $this->createStub(PostgreSQLPlatform::class);
        $driverConnection->method('getDatabasePlatform')->willReturn($platform);
        $driverConnection->method('executeStatement')->willReturn(1);

        $registry = $this->createStub(ConnectionRegistry::class);
        $registry->method('getConnection')->willReturn($driverConnection);

        $listener = $this->createMock(PostgreSqlNotifyOnIdleListener::class);
        $listener->expects($this->never())->method('addConnection');

        $factory = new DoctrineTransportFactory($registry, $listener);
        $serializer = $this->createStub(SerializerInterface::class);

        $factory->createTransport('doctrine://default', [], $serializer);
    }

    public function testCreateTransportWorksWithoutListener()
    {
        $driverConnection = $this->createStub(\Doctrine\DBAL\Connection::class);
        $platform = $this->createStub(PostgreSQLPlatform::class);
        $driverConnection->method('getDatabasePlatform')->willReturn($platform);
        $driverConnection->method('executeStatement')->willReturn(1);

        $registry = $this->createStub(ConnectionRegistry::class);
        $registry->method('getConnection')->willReturn($driverConnection);

        $factory = new DoctrineTransportFactory($registry);
        $serializer = $this->createStub(SerializerInterface::class);

        $transport = $factory->createTransport('doctrine://default', ['transport_name' => 'my_transport'], $serializer);
        $this->assertInstanceOf(DoctrineTransport::class, $transport);
    }
}
