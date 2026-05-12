<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Messenger;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Messenger\DoctrineCloseConnectionMiddleware;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;

class DoctrineCloseConnectionMiddlewareTest extends MiddlewareTestCase
{
    public function testMiddlewareCloseConnection()
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('close');

        $managerRegistry = $this->createStub(ManagerRegistry::class);
        $managerRegistry->method('getConnection')->willReturn($connection);

        new DoctrineCloseConnectionMiddleware($managerRegistry, connectionName: 'connection')->handle(
            new Envelope(new \stdClass(), [new ConsumedByWorkerStamp()]),
            $this->getStackMock(),
        );
    }

    public function testMiddlewareDoesNotCloseConnectionInNonWorkerContext()
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('close');

        $managerRegistry = $this->createStub(ManagerRegistry::class);
        $managerRegistry->method('getConnection')->willReturn($connection);

        new DoctrineCloseConnectionMiddleware($managerRegistry, connectionName: 'connection')->handle(
            new Envelope(new \stdClass()),
            $this->getStackMock(),
        );
    }
}
