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
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Result;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Messenger\DoctrinePingConnectionMiddleware;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;

class DoctrinePingConnectionMiddlewareTest extends MiddlewareTestCase
{
    private string $entityManagerName = 'default';

    public function testMiddlewarePingOk()
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('isConnected')->willReturn(true);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $managerRegistry = $this->createStub(ManagerRegistry::class);
        $managerRegistry->method('getManager')->willReturn($entityManager);

        $middleware = new DoctrinePingConnectionMiddleware($managerRegistry, $this->entityManagerName);

        $connection->method('getDatabasePlatform')
            ->willReturn($this->mockPlatform());

        $connection->expects($this->exactly(2))
            ->method('executeQuery')
            ->willReturnCallback(function () {
                static $counter = 0;

                if (1 === ++$counter) {
                    throw $this->createStub(DBALException::class);
                }

                return $this->createStub(Result::class);
            });

        $connection->expects($this->once())
            ->method('close')
        ;

        $envelope = new Envelope(new \stdClass(), [
            new ConsumedByWorkerStamp(),
        ]);
        $middleware->handle($envelope, $this->getStackMock());
    }

    public function testMiddlewarePingResetEntityManager()
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('isConnected')->willReturn(true);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $managerRegistry->method('getManager')->willReturn($entityManager);

        $middleware = new DoctrinePingConnectionMiddleware(
            $managerRegistry,
            $this->entityManagerName
        );

        $connection->method('getDatabasePlatform')
            ->willReturn($this->mockPlatform());
        $connection->method('executeQuery')->willReturn($this->createStub(Result::class));

        $entityManager->expects($this->once())
            ->method('isOpen')
            ->willReturn(false)
        ;
        $managerRegistry->expects($this->once())
            ->method('resetManager')
            ->with($this->entityManagerName)
        ;

        $envelope = new Envelope(new \stdClass(), [
            new ConsumedByWorkerStamp(),
        ]);
        $middleware->handle($envelope, $this->getStackMock());
    }

    public function testInvalidEntityManagerThrowsException()
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('getDatabasePlatform');
        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $managerRegistry
            ->expects($this->once())
            ->method('getManager')
            ->willThrowException(new \InvalidArgumentException());

        $middleware = new DoctrinePingConnectionMiddleware($managerRegistry, 'unknown_manager');

        $this->expectException(UnrecoverableMessageHandlingException::class);

        $middleware->handle(new Envelope(new \stdClass()), $this->getStackMock(false));
    }

    public function testMiddlewareNoPingInNonWorkerContext()
    {
        $connection = $this->createMock(Connection::class);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $managerRegistry = $this->createStub(ManagerRegistry::class);
        $managerRegistry->method('getManager')->willReturn($entityManager);

        $middleware = new DoctrinePingConnectionMiddleware(
            $managerRegistry,
            $this->entityManagerName
        );

        $connection->expects($this->never())
            ->method('close')
        ;

        $envelope = new Envelope(new \stdClass());
        $middleware->handle($envelope, $this->getStackMock());
    }

    public function testMiddlewarePingsAllConnectionsWhenEntityManagerNameIsNull()
    {
        $firstConnection = $this->connectionExpectingOnePing();
        $secondConnection = $this->connectionExpectingOnePing();

        $registry = $this->createRegistryForManagers([
            'first' => $this->createManagerWithConnection($firstConnection),
            'second' => $this->createManagerWithConnection($secondConnection),
        ]);

        $middleware = new DoctrinePingConnectionMiddleware($registry);

        $envelope = new Envelope(new \stdClass(), [
            new ConsumedByWorkerStamp(),
        ]);
        $middleware->handle($envelope, $this->getStackMock());
    }

    public function testMiddlewareResetsClosedManagersWhenEntityManagerNameIsNull()
    {
        $registry = $this->createRegistryForManagers([
            'open' => $this->createManagerWithConnection($this->connectionExpectingOnePing(), true),
            'closed' => $this->createManagerWithConnection($this->connectionExpectingOnePing(), false),
        ], true);
        $registry->expects($this->once())
            ->method('resetManager')
            ->with('closed')
        ;

        $middleware = new DoctrinePingConnectionMiddleware($registry);

        $envelope = new Envelope(new \stdClass(), [
            new ConsumedByWorkerStamp(),
        ]);
        $middleware->handle($envelope, $this->getStackMock());
    }

    private function mockPlatform(): AbstractPlatform
    {
        $platform = $this->createStub(AbstractPlatform::class);
        $platform->method('getDummySelectSQL')->willReturn('SELECT 1');

        return $platform;
    }

    private function connectionExpectingOnePing(): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('isConnected')->willReturn(true);
        $connection->method('getDatabasePlatform')->willReturn($this->mockPlatform());
        $connection->expects($this->once())->method('executeQuery');

        return $connection;
    }

    private function createManagerWithConnection(Connection $connection, ?bool $isOpen = null): EntityManagerInterface
    {
        $manager = null === $isOpen ? $this->createStub(EntityManagerInterface::class) : $this->createMock(EntityManagerInterface::class);
        $manager->method('getConnection')->willReturn($connection);

        if (null !== $isOpen) {
            $manager->expects($this->once())->method('isOpen')->willReturn($isOpen);
        }

        return $manager;
    }

    /**
     * @param array<string, EntityManagerInterface> $managers
     */
    private function createRegistryForManagers(array $managers, bool $withExpectations = false): ManagerRegistry
    {
        $defaultName = array_key_first($managers);

        $registry = $withExpectations ? $this->createMock(ManagerRegistry::class) : $this->createStub(ManagerRegistry::class);

        if ($withExpectations) {
            $registry->expects($this->any())->method('getManagerNames')->willReturn(array_combine(array_keys($managers), array_keys($managers)));
            $registry->expects($this->any())->method('getManager')->willReturnCallback(static fn (?string $name): ?EntityManagerInterface => $managers[$name ?? $defaultName] ?? null);
        } else {
            $registry->method('getManagerNames')->willReturn(array_combine(array_keys($managers), array_keys($managers)));
            $registry->method('getManager')->willReturnCallback(static fn (?string $name): ?EntityManagerInterface => $managers[$name ?? $defaultName] ?? null);
        }

        return $registry;
    }
}
