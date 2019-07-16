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
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\Doctrine\Connection;
use Symfony\Component\Messenger\Transport\Doctrine\DoctrineTransport;
use Symfony\Component\Messenger\Transport\Doctrine\DoctrineTransportFactory;
use Symfony\Component\Messenger\Transport\Dsn;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class DoctrineTransportFactoryTest extends TestCase
{
    /**
     * @dataProvider supportsProvider
     */
    public function testSupports(Dsn $dsn, bool $supports): void
    {
        $factory = new DoctrineTransportFactory(
            $this->createMock(ConnectionRegistry::class)
        );

        $this->assertSame($supports, $factory->supports($dsn));
    }

    public function supportsProvider(): iterable
    {
        yield [Dsn::fromString('doctrine://localhost'), true];

        yield [Dsn::fromString('foo://localhost'), false];
    }

    public function testCreate(): void
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

        $dsn = Dsn::fromString('doctrine://default');
        $connection = new Connection(Connection::buildConfigurationFromDsnObject($dsn), $driverConnection);
        $expectedTransport = new DoctrineTransport($connection, $serializer);

        $this->assertEquals($expectedTransport, $factory->createTransport($dsn, $serializer, 'doctrine'));
    }

    public function testCreateTransportMustThrowAnExceptionIfManagerIsNotFound(): void
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Could not find Doctrine connection from Messenger DSN "doctrine://default".');
        $registry = $this->createMock(ConnectionRegistry::class);
        $registry->expects($this->once())
            ->method('getConnection')
            ->willReturnCallback(function () {
                throw new \InvalidArgumentException();
            });

        $factory = new DoctrineTransportFactory($registry);
        $dsn = Dsn::fromString('doctrine://default');
        $factory->createTransport($dsn, $this->createMock(SerializerInterface::class), 'doctrine');
    }
}
