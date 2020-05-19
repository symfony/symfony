<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Bridge\Doctrine\Tests\Transport;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\ManagerRegistry;
use Symfony\Component\Scheduler\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Scheduler\Bridge\Doctrine\Transport\DoctrineTransportFactory;
use Symfony\Component\Scheduler\Task\TaskFactoryInterface;
use Symfony\Component\Scheduler\Transport\Dsn;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class DoctrineTransportFactoryTest extends TestCase
{
    public function testTransportCanSupport(): void
    {
        $taskFactory = $this->createMock(TaskFactoryInterface::class);
        $registry = $this->createMock(ManagerRegistry::class);

        $factory = new DoctrineTransportFactory($registry, $taskFactory);

        static::assertFalse($factory->support('test://'));
        static::assertTrue($factory->support('doctrine://'));
    }

    public function testFactoryReturnTransport(): void
    {
        $taskFactory = $this->createMock(TaskFactoryInterface::class);
        $connection = $this->createMock(Connection::class);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getConnection')->willReturn($connection);

        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new DoctrineTransportFactory($registry, $taskFactory);
        static::assertInstanceOf(DoctrineTransport::class, $factory->createTransport(Dsn::fromString('doctrine://root@root'), [], $serializer));
    }
}
