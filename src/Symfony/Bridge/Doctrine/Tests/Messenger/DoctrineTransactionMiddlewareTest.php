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
use Symfony\Bridge\Doctrine\Messenger\DoctrineTransactionMiddleware;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;

class DoctrineTransactionMiddlewareTest extends MiddlewareTestCase
{
    private $connection;
    private $entityManager;
    private $middleware;

    protected function setUp(): void
    {
        $this->connection = self::createMock(Connection::class);

        $this->entityManager = self::createMock(EntityManagerInterface::class);
        $this->entityManager->method('getConnection')->willReturn($this->connection);

        $managerRegistry = self::createMock(ManagerRegistry::class);
        $managerRegistry->method('getManager')->willReturn($this->entityManager);

        $this->middleware = new DoctrineTransactionMiddleware($managerRegistry);
    }

    public function testMiddlewareWrapsInTransactionAndFlushes()
    {
        $this->connection->expects(self::once())
            ->method('beginTransaction')
        ;
        $this->connection->expects(self::once())
            ->method('commit')
        ;
        $this->entityManager->expects(self::once())
            ->method('flush')
        ;

        $this->middleware->handle(new Envelope(new \stdClass()), $this->getStackMock());
    }

    public function testTransactionIsRolledBackOnException()
    {
        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('Thrown from next middleware.');
        $this->connection->expects(self::once())
            ->method('beginTransaction')
        ;
        $this->connection->expects(self::once())
            ->method('rollBack')
        ;

        $this->middleware->handle(new Envelope(new \stdClass()), $this->getThrowingStackMock());
    }

    public function testInvalidEntityManagerThrowsException()
    {
        $managerRegistry = self::createMock(ManagerRegistry::class);
        $managerRegistry
            ->method('getManager')
            ->with('unknown_manager')
            ->will(self::throwException(new \InvalidArgumentException()));

        $middleware = new DoctrineTransactionMiddleware($managerRegistry, 'unknown_manager');

        self::expectException(UnrecoverableMessageHandlingException::class);

        $middleware->handle(new Envelope(new \stdClass()), $this->getStackMock(false));
    }
}
