<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\CallableWrapper;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\CallableWrapper\Transactional;
use Symfony\Bridge\Doctrine\CallableWrapper\TransactionalCallableWrapper;
use Symfony\Component\CallableWrapper\CallableWrapper;
use Symfony\Component\CallableWrapper\Resolver\CallableWrapperResolver;

class TransactionalCallableWrapperTest extends TestCase
{
    private ManagerRegistry $managerRegistry;
    private Connection $connection;
    private EntityManagerInterface $entityManager;
    private CallableWrapper $wrapper;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entityManager->method('getConnection')->willReturn($this->connection);

        $this->managerRegistry = $this->createMock(ManagerRegistry::class);
        $this->managerRegistry->method('getManager')->willReturn($this->entityManager);

        $this->wrapper = new CallableWrapper(new CallableWrapperResolver([
            TransactionalCallableWrapper::class => fn () => new TransactionalCallableWrapper($this->managerRegistry),
        ]));
    }

    public function testWrapInTransactionAndFlushes()
    {
        $handler = new TestHandler();

        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('commit');
        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->wrapper->call($handler->handle(...));
        $this->assertSame('success', $result);
    }

    public function testTransactionIsRolledBackOnException()
    {
        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('rollBack');

        $handler = new TestHandler();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('A runtime error.');

        $this->wrapper->call($handler->handleWithError(...));
    }

    public function testInvalidEntityManagerThrowsException()
    {
        $this->managerRegistry
            ->method('getManager')
            ->with('unknown_manager')
            ->willThrowException(new \InvalidArgumentException());

        $handler = new TestHandler();

        $this->expectException(\InvalidArgumentException::class);

        $this->wrapper->call($handler->handleWithUnknownManager(...));
    }
}

class TestHandler
{
    #[Transactional]
    public function handle(): string
    {
        return 'success';
    }

    #[Transactional]
    public function handleWithError(): void
    {
        throw new \RuntimeException('A runtime error.');
    }

    #[Transactional('unknown_manager')]
    public function handleWithUnknownManager(): void
    {
    }
}
