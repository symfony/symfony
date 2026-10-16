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
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bridge\Doctrine\Messenger\DoctrineTransactionMiddleware;
use Symfony\Bridge\Doctrine\Tests\DoctrineTestHelper;
use Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdEntity;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackMiddleware;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;

class DoctrineTransactionMiddlewareTest extends MiddlewareTestCase
{
    private MockObject&Connection $connection;
    private EntityManagerInterface $entityManager;
    private DoctrineTransactionMiddleware $middleware;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);

        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->entityManager->method('getConnection')->willReturn($this->connection);

        $managerRegistry = $this->createStub(ManagerRegistry::class);
        $managerRegistry->method('getManager')->willReturn($this->entityManager);

        $this->middleware = new DoctrineTransactionMiddleware($managerRegistry);
    }

    public function testMiddlewareWrapsInTransactionAndFlushes()
    {
        $this->connection->expects($this->once())
            ->method('beginTransaction')
        ;
        $this->connection->expects($this->once())
            ->method('commit')
        ;

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($this->connection);
        $entityManager->expects($this->once())
            ->method('flush')
        ;

        $managerRegistry = $this->createStub(ManagerRegistry::class);
        $managerRegistry->method('getManager')->willReturn($entityManager);

        $middleware = new DoctrineTransactionMiddleware($managerRegistry);
        $middleware->handle(new Envelope(new \stdClass()), $this->getStackMock());
    }

    public function testFailedHandlerDoesNotLeaveEntitiesForLaterTransactions()
    {
        $entityManager = DoctrineTestHelper::createTestEntityManager();
        $connection = $entityManager->getConnection();
        (new SchemaTool($entityManager))->createSchema([$entityManager->getClassMetadata(SingleIntIdEntity::class)]);
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManager')->willReturn($entityManager);
        $next = $this->createMock(MiddlewareInterface::class);
        $failure = new \RuntimeException('Failed handler.');
        $next->method('handle')->willReturnCallback(static function () use ($entityManager, $failure) {
            $entityManager->persist(new SingleIntIdEntity(1, 'failed'));

            throw $failure;
        });

        try {
            (new DoctrineTransactionMiddleware($registry))->handle(new Envelope(new \stdClass()), new StackMiddleware($next));
            self::fail('The handler should fail.');
        } catch (\RuntimeException $e) {
            self::assertSame($failure, $e);
        }

        self::assertFalse($entityManager->isOpen());
        self::assertSame([], $entityManager->getUnitOfWork()->getScheduledEntityInsertions());

        $nextManager = new EntityManager($connection, $entityManager->getConfiguration());
        $nextManager->persist(new SingleIntIdEntity(2, 'successful'));
        $nextManager->flush();

        self::assertSame([['id' => 2]], $connection->fetchAllAssociative('SELECT id FROM SingleIntIdEntity'));
    }

    public function testManagerIsClosedWhenTheTransactionIsAlreadyRolledBack()
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($this->connection);
        $entityManager->expects($this->once())->method('close');
        $this->connection->method('isTransactionActive')->willReturn(false);
        $this->connection->expects($this->never())->method('rollBack');
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManager')->willReturn($entityManager);

        $this->expectException(\RuntimeException::class);

        (new DoctrineTransactionMiddleware($registry))->handle(new Envelope(new \stdClass()), $this->getThrowingStackMock());
    }

    public function testTransactionIsRolledBackOnException()
    {
        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('isTransactionActive')->willReturn(true);
        $this->connection->expects($this->once())->method('rollBack');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Thrown from next middleware.');

        $this->middleware->handle(new Envelope(new \stdClass()), $this->getThrowingStackMock());
    }

    public function testExceptionInRollBackDoesNotHidePreviousException()
    {
        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('isTransactionActive')->willReturn(true);
        $this->connection->expects($this->once())->method('rollBack')->willThrowException(new \RuntimeException('Thrown from rollBack.'));

        try {
            $this->middleware->handle(new Envelope(new \stdClass()), $this->getThrowingStackMock());
        } catch (\Throwable $exception) {
        }

        self::assertNotNull($exception);
        self::assertInstanceOf(\RuntimeException::class, $exception);
        self::assertSame('Thrown from rollBack.', $exception->getMessage());

        $previous = $exception->getPrevious();
        self::assertNotNull($previous);
        self::assertInstanceOf(\RuntimeException::class, $previous);
        self::assertSame('Thrown from next middleware.', $previous->getMessage());
    }

    public function testInvalidEntityManagerThrowsException()
    {
        $this->connection->expects($this->never())->method('getDatabasePlatform');
        $managerRegistry = $this->createStub(ManagerRegistry::class);
        $managerRegistry
            ->method('getManager')
            ->with('unknown_manager')
            ->willThrowException(new \InvalidArgumentException());

        $middleware = new DoctrineTransactionMiddleware($managerRegistry, 'unknown_manager');

        $this->expectException(UnrecoverableMessageHandlingException::class);

        $middleware->handle(new Envelope(new \stdClass()), $this->getStackMock(false));
    }
}
