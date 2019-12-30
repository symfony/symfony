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

use Doctrine\Common\Persistence\ConnectionRegistry;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\SchemaConfig;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Transport\Doctrine\Connection;
use Symfony\Component\Messenger\Transport\Doctrine\DoctrineTransport;
use Symfony\Component\Messenger\Transport\Doctrine\DoctrineTransportFactory;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class DoctrineTransportFactoryTest extends TestCase
{
    public function testSupports()
    {
        $factory = new DoctrineTransportFactory(
            $this->createMock(ConnectionRegistry::class)
        );

        $this->assertTrue($factory->supports('doctrine://default', []));
        $this->assertFalse($factory->supports('amqp://localhost', []));
    }

    public function testCreateTransport()
    {
        $driverConnection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaConfig = $this->createMock(SchemaConfig::class);
        $schemaManager->method('createSchemaConfig')->willReturn($schemaConfig);
        $driverConnection->method('getSchemaManager')->willReturn($schemaManager);
        $registry = $this->createMock(ConnectionRegistry::class);

        $registry->expects($this->once())
            ->method('getConnection')
            ->willReturn($driverConnection);

        $factory = new DoctrineTransportFactory($registry);
        $serializer = $this->createMock(SerializerInterface::class);

        $this->assertEquals(
            new DoctrineTransport(new Connection(Connection::buildConfiguration('doctrine://default'), $driverConnection), $serializer),
            $factory->createTransport('doctrine://default', [], $serializer)
        );
    }

    public function testCreateTransportMustThrowAnExceptionIfManagerIsNotFound()
    {
        $this->expectException('Symfony\Component\Messenger\Exception\TransportException');
        $this->expectExceptionMessage('Could not find Doctrine connection from Messenger DSN "doctrine://default".');
        $registry = $this->createMock(ConnectionRegistry::class);
        $registry->expects($this->once())
            ->method('getConnection')
            ->willReturnCallback(function () {
                throw new \InvalidArgumentException();
            });

        $factory = new DoctrineTransportFactory($registry);
        $factory->createTransport('doctrine://default', [], $this->createMock(SerializerInterface::class));
    }
}
