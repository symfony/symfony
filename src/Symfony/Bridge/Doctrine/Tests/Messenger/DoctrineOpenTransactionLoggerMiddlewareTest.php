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
use Psr\Log\AbstractLogger;
use Symfony\Bridge\Doctrine\Messenger\DoctrineOpenTransactionLoggerMiddleware;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;

class DoctrineOpenTransactionLoggerMiddlewareTest extends MiddlewareTestCase
{
    private $logger;
    private $connection;
    private $entityManager;
    private $middleware;

    protected function setUp(): void
    {
        $this->logger = new class() extends AbstractLogger {
            public $logs = [];

            public function log($level, $message, $context = []): void
            {
                $this->logs[$level][] = $message;
            }
        };

        $this->connection = self::createMock(Connection::class);

        $this->entityManager = self::createMock(EntityManagerInterface::class);
        $this->entityManager->method('getConnection')->willReturn($this->connection);

        $managerRegistry = self::createMock(ManagerRegistry::class);
        $managerRegistry->method('getManager')->willReturn($this->entityManager);

        $this->middleware = new DoctrineOpenTransactionLoggerMiddleware($managerRegistry, null, $this->logger);
    }

    public function testMiddlewareWrapsInTransactionAndFlushes()
    {
        $this->connection->expects(self::exactly(1))
            ->method('isTransactionActive')
            ->will(self::onConsecutiveCalls(true, true, false))
        ;

        $this->middleware->handle(new Envelope(new \stdClass()), $this->getStackMock());

        self::assertSame(['error' => ['A handler opened a transaction but did not close it.']], $this->logger->logs);
    }
}
