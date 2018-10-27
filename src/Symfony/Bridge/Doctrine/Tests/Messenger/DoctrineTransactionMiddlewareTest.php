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

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Messenger\DoctrineTransactionMiddleware;
use Symfony\Bridge\Doctrine\Tests\Fixtures\Messenger\DummyMiddleware;
use Symfony\Bridge\Doctrine\Tests\Fixtures\Messenger\ThrowingMiddleware;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\StackInterface;

class DoctrineTransactionMiddlewareTest extends TestCase
{
    private $connection;
    private $entityManager;
    private $middleware;
    private $stack;

    public function setUp()
    {
        $this->connection = $this->createMock(Connection::class);

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entityManager->method('getConnection')->willReturn($this->connection);

        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $managerRegistry->method('getManager')->willReturn($this->entityManager);

        $this->middleware = new DoctrineTransactionMiddleware($managerRegistry, null);

        $this->stack = $this->createMock(StackInterface::class);
    }

    public function testMiddlewareWrapsInTransactionAndFlushes()
    {
        $this->connection->expects($this->once())
            ->method('beginTransaction')
        ;
        $this->connection->expects($this->once())
            ->method('commit')
        ;
        $this->entityManager->expects($this->once())
            ->method('flush')
        ;
        $this->stack
            ->expects($this->once())
            ->method('next')
            ->willReturn(new DummyMiddleware())
        ;

        $this->middleware->handle(new Envelope(new \stdClass()), $this->stack);
    }

    public function testTransactionIsRolledBackOnException()
    {
        $this->connection->expects($this->once())
            ->method('beginTransaction')
        ;
        $this->connection->expects($this->once())
            ->method('rollBack')
        ;
        $this->stack
            ->expects($this->once())
            ->method('next')
            ->willReturn(new ThrowingMiddleware())
        ;
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Thrown from middleware.');

        $this->middleware->handle(new Envelope(new \stdClass()), $this->stack);
    }
}
