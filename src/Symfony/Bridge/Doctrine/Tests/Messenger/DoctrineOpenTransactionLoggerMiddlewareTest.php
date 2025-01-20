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
use Psr\Log\AbstractLogger;
use Symfony\Bridge\Doctrine\Messenger\DoctrineOpenTransactionLoggerMiddleware;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;

class DoctrineOpenTransactionLoggerMiddlewareTest extends MiddlewareTestCase
{
    private AbstractLogger $logger;
    private MockObject&Connection $connection;
    private MockObject&EntityManagerInterface $entityManager;
    private DoctrineOpenTransactionLoggerMiddleware $middleware;

    protected function setUp(): void
    {
        $this->logger = new class extends AbstractLogger {
            public array $logs = [];

            public function log($level, $message, $context = []): void
            {
                $this->logs[$level][] = $message;
            }
        };

        $this->connection = $this->createMock(Connection::class);

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entityManager->method('getConnection')->willReturn($this->connection);

        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $managerRegistry->method('getManager')->willReturn($this->entityManager);

        $this->middleware = new DoctrineOpenTransactionLoggerMiddleware($managerRegistry, null, $this->logger);
    }

    public function testMiddlewareWrapsInTransactionAndFlushes()
    {
        $this->connection->expects($this->exactly(2))
            ->method('getTransactionNestingLevel')
            ->willReturn(0, 1)
        ;

        $this->middleware->handle(new Envelope(new \stdClass()), $this->getStackMock());

        $this->assertSame(['error' => ['A handler opened a transaction but did not close it.']], $this->logger->logs);
    }
}
