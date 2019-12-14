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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Transport\ConnectionInterface;
use Symfony\Component\Scheduler\Transport\Dsn;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class DoctrineTransportTest extends TestCase
{
    public function testTransportCanReturnOptions(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);

        $transport = new DoctrineTransport(Dsn::fromString('doctrine://root@root?execution_mode=normal'), [], $connection);

        static::assertNotEmpty($transport->getOptions());
    }

    public function testTransportCanGetATask(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects(self::once())->method('get')->willReturn($task);

        $transport = new DoctrineTransport(Dsn::fromString('doctrine://root@root?execution_mode=normal'), [], $connection);

        static::assertInstanceOf(TaskInterface::class, $transport->get('foo'));
    }

    public function testTransportCanDeleteATask(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects(self::once())->method('delete');

        $transport = new DoctrineTransport(Dsn::fromString('doctrine://root@root?execution_mode=normal'), [], $connection);
        $transport->delete('foo');
    }
}
