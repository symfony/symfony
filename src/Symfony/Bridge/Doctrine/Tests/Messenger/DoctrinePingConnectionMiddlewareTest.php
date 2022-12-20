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
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Messenger\DoctrinePingConnectionMiddleware;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;

class DoctrinePingConnectionMiddlewareTest extends MiddlewareTestCase
{
    private $connection;
    private $entityManager;
    private $managerRegistry;
    private $middleware;
    private $entityManagerName = 'default';

    protected function setUp(): void
    {
        $this->connection = self::createMock(Connection::class);

        $this->entityManager = self::createMock(EntityManagerInterface::class);
        $this->entityManager->method('getConnection')->willReturn($this->connection);

        $this->managerRegistry = self::createMock(ManagerRegistry::class);
        $this->managerRegistry->method('getManager')->willReturn($this->entityManager);

        $this->middleware = new DoctrinePingConnectionMiddleware(
            $this->managerRegistry,
            $this->entityManagerName
        );
    }

    public function testMiddlewarePingOk()
    {
        $this->connection->expects(self::once())
            ->method('getDatabasePlatform')
            ->will(self::throwException(new DBALException()));

        $this->connection->expects(self::once())
            ->method('close')
        ;
        $this->connection->expects(self::once())
            ->method('connect')
        ;

        $envelope = new Envelope(new \stdClass(), [
            new ConsumedByWorkerStamp(),
        ]);
        $this->middleware->handle($envelope, $this->getStackMock());
    }

    public function testMiddlewarePingResetEntityManager()
    {
        $this->connection->expects(self::once())
            ->method('getDatabasePlatform')
            ->will(self::throwException(new DBALException()));

        $this->entityManager->expects(self::once())
            ->method('isOpen')
            ->willReturn(false)
        ;
        $this->managerRegistry->expects(self::once())
            ->method('resetManager')
            ->with($this->entityManagerName)
        ;

        $envelope = new Envelope(new \stdClass(), [
            new ConsumedByWorkerStamp(),
        ]);
        $this->middleware->handle($envelope, $this->getStackMock());
    }

    public function testInvalidEntityManagerThrowsException()
    {
        $managerRegistry = self::createMock(ManagerRegistry::class);
        $managerRegistry
            ->method('getManager')
            ->with('unknown_manager')
            ->will(self::throwException(new \InvalidArgumentException()));

        $middleware = new DoctrinePingConnectionMiddleware($managerRegistry, 'unknown_manager');

        self::expectException(UnrecoverableMessageHandlingException::class);

        $middleware->handle(new Envelope(new \stdClass()), $this->getStackMock(false));
    }

    public function testMiddlewareNoPingInNonWorkerContext()
    {
        // This method has been removed in DBAL 3.0
        if (method_exists(Connection::class, 'ping')) {
            $this->connection->expects(self::never())
                ->method('ping')
                ->willReturn(false);
        }

        $this->connection->expects(self::never())
            ->method('close')
        ;
        $this->connection->expects(self::never())
            ->method('connect')
        ;

        $envelope = new Envelope(new \stdClass());
        $this->middleware->handle($envelope, $this->getStackMock());
    }
}
