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
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bridge\Doctrine\Messenger\DoctrineTransactionMiddleware;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;

class DoctrineTransactionMiddlewareTest extends MiddlewareTestCase
{
    private MockObject&Connection $connection;
    private MockObject&EntityManagerInterface $entityManager;
    private DoctrineTransactionMiddleware $middleware;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entityManager->method('getConnection')->willReturn($this->connection);

        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $managerRegistry->method('getManager')->willReturn($this->entityManager);

        $this->middleware = new DoctrineTransactionMiddleware($managerRegistry);
    }

    public function testMiddlewareNotStartTransactionWhenMessageSent()
    {
        $this->connection->expects($this->never())
            ->method('beginTransaction')
        ;
        $this->connection->expects($this->never())
            ->method('commit')
        ;
        $this->entityManager->expects($this->never())
            ->method('flush')
        ;

        $this->middleware->handle($this->createSentMessage(), $this->getStackMock());
    }

    public function testMiddlewareWrapsInTransactionAndFlushesWhenMessageReceived()
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

        $this->middleware->handle($this->createReceivedMessage(), $this->getStackMock());
    }

    public function testNoTransactionIsRolledBackOnExceptionWhenMessageSent()
    {
        $this->connection->expects($this->never())
            ->method('beginTransaction')
        ;
        $this->connection->expects($this->never())
            ->method('rollBack')
        ;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Thrown from next middleware.');

        $this->middleware->handle($this->createSentMessage(), $this->getThrowingStackMock());
    }

    public function testTransactionIsRolledBackOnExceptionWhenMessageIsReceived()
    {
        $this->connection->expects($this->once())
            ->method('beginTransaction')
        ;
        $this->connection->expects($this->once())
            ->method('rollBack')
        ;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Thrown from next middleware.');

        $this->middleware->handle($this->createReceivedMessage(), $this->getThrowingStackMock());
    }

    /**
     * @dataProvider messagesProvider
     */
    public function testInvalidEntityManagerThrowsExceptionIfMessageProduced(Envelope $envelope)
    {
        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $managerRegistry
            ->method('getManager')
            ->with('unknown_manager')
            ->willThrowException(new \InvalidArgumentException());

        $middleware = new DoctrineTransactionMiddleware($managerRegistry, 'unknown_manager');

        $this->expectException(UnrecoverableMessageHandlingException::class);

        $middleware->handle($envelope, $this->getStackMock(false));
    }

    public function messagesProvider()
    {
        yield 'sent message' => [$this->createSentMessage()];
        yield 'received message' => [$this->createReceivedMessage()];
    }

    private function createSentMessage()
    {
        return new Envelope(new \stdClass());
    }

    private function createReceivedMessage()
    {
        return new Envelope(new \stdClass(), [new ReceivedStamp('transport.name')]);
    }
}
