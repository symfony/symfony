<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Decorator;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Decorator\Transactional;
use Symfony\Bridge\Doctrine\Decorator\TransactionalDecorator;
use Symfony\Component\Decorator\CallableDecorator;
use Symfony\Component\Decorator\Resolver\DecoratorResolver;

class DoctrineTransactionDecoratorTest extends TestCase
{
    private ManagerRegistry $managerRegistry;
    private Connection $connection;
    private EntityManagerInterface $entityManager;
    private CallableDecorator $decorator;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entityManager->method('getConnection')->willReturn($this->connection);

        $this->managerRegistry = $this->createMock(ManagerRegistry::class);
        $this->managerRegistry->method('getManager')->willReturn($this->entityManager);

        $this->decorator = new CallableDecorator(new DecoratorResolver([
            TransactionalDecorator::class => fn () => new TransactionalDecorator($this->managerRegistry),
        ]));
    }

    public function testDecoratorWrapsInTransactionAndFlushes()
    {
        $handler = new TestHandler();

        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('commit');
        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->decorator->call($handler->handle(...));
        $this->assertSame('success', $result);
    }

    public function testTransactionIsRolledBackOnException()
    {
        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('rollBack');

        $handler = new TestHandler();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('A runtime error.');

        $this->decorator->call($handler->handleWithError(...));
    }

    public function testInvalidEntityManagerThrowsException()
    {
        $this->managerRegistry
            ->method('getManager')
            ->with('unknown_manager')
            ->willThrowException(new \InvalidArgumentException());

        $handler = new TestHandler();

        $this->expectException(\InvalidArgumentException::class);

        $this->decorator->call($handler->handleWithUnknownManager(...));
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
