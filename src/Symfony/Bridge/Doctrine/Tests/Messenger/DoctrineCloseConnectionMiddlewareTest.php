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
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Messenger\DoctrineCloseConnectionMiddleware;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;

class DoctrineCloseConnectionMiddlewareTest extends MiddlewareTestCase
{
    private $connection;
    private $entityManager;
    private $managerRegistry;
    private $middleware;
    private $entityManagerName = 'default';

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entityManager->method('getConnection')->willReturn($this->connection);

        $this->managerRegistry = $this->createMock(ManagerRegistry::class);
        $this->managerRegistry->method('getManager')->willReturn($this->entityManager);

        $this->middleware = new DoctrineCloseConnectionMiddleware(
            $this->managerRegistry,
            $this->entityManagerName
        );
    }

    public function testMiddlewareCloseConnection()
    {
        $this->connection->expects($this->once())
            ->method('close')
        ;

        $envelope = new Envelope(new \stdClass(), [
            new ConsumedByWorkerStamp(),
        ]);
        $this->middleware->handle($envelope, $this->getStackMock());
    }

    public function testInvalidEntityManagerThrowsException()
    {
        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $managerRegistry
            ->method('getManager')
            ->with('unknown_manager')
            ->willThrowException(new \InvalidArgumentException());

        $middleware = new DoctrineCloseConnectionMiddleware($managerRegistry, 'unknown_manager');

        $this->expectException(UnrecoverableMessageHandlingException::class);

        $middleware->handle(new Envelope(new \stdClass()), $this->getStackMock(false));
    }

    public function testMiddlewareNotCloseInNonWorkerContext()
    {
        $this->connection->expects($this->never())
            ->method('close')
        ;

        $envelope = new Envelope(new \stdClass());
        $this->middleware->handle($envelope, $this->getStackMock());
    }
}
