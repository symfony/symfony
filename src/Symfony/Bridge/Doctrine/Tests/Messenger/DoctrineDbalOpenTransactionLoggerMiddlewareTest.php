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
use Doctrine\Persistence\ConnectionRegistry;
use Psr\Log\AbstractLogger;
use Symfony\Bridge\Doctrine\Messenger\DoctrineDbalOpenTransactionLoggerMiddleware;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Middleware\StackMiddleware;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;

class DoctrineDbalOpenTransactionLoggerMiddlewareTest extends MiddlewareTestCase
{
    private AbstractLogger $logger;

    protected function setUp(): void
    {
        $this->logger = new class extends AbstractLogger {
            public array $logs = [];

            public function log($level, $message, $context = []): void
            {
                $this->logs[] = [
                    'level' => $level,
                    'message' => $message,
                    'context' => $context,
                ];
            }
        };
    }

    public function testMiddlewareLogsUnclosedTransactions()
    {
        $fooConnection = $this->createStub(Connection::class);
        $fooConnection->method('getTransactionNestingLevel')->willReturn(0, 1);

        $barConnection = $this->createStub(Connection::class);
        $barConnection->method('getTransactionNestingLevel')->willReturn(0, 2);

        $bazConnection = $this->createStub(Connection::class);
        $bazConnection->method('getTransactionNestingLevel')->willReturn(0, 0);

        $connectionRegistry = $this->createStub(ConnectionRegistry::class);
        $connectionRegistry->method('getConnection')->willReturnMap([
            ['foo', $fooConnection],
            ['bar', $barConnection],
            ['baz', $bazConnection],
        ]);

        $message = new \stdClass();

        $middleware = new DoctrineDbalOpenTransactionLoggerMiddleware(
            $connectionRegistry,
            $this->logger,
            ['foo', 'bar', 'baz'],
        );

        $middleware->handle(new Envelope($message), $this->getStackMock());

        $this->assertSame(
            [[
                'level' => 'error',
                'message' => 'A handler opened a transaction but did not close it.',
                'context' => ['connections' => ['foo', 'bar'], 'message' => $message],
            ]],
            $this->logger->logs,
        );
    }

    public function testMiddlewareChecksEveryConnectionWhenPassedNoName()
    {
        $fooConnection = $this->createMock(Connection::class);
        $fooConnection->expects($this->exactly(2))->method('getTransactionNestingLevel')->willReturn(0, 0);

        $barConnection = $this->createMock(Connection::class);
        $barConnection->expects($this->exactly(2))->method('getTransactionNestingLevel')->willReturn(0, 0);

        $connectionRegistry = $this->createStub(ConnectionRegistry::class);
        $connectionRegistry->method('getConnectionNames')->willReturn([
            'foo' => 'doctrine.dbal.foo_connection',
            'bar' => 'doctrine.dbal.bar_connection',
        ]);
        $connectionRegistry->method('getConnection')->willReturnMap([
            ['foo', $fooConnection],
            ['bar', $barConnection],
        ]);

        $middleware = new DoctrineDbalOpenTransactionLoggerMiddleware($connectionRegistry, $this->logger);

        $middleware->handle(new Envelope(new \stdClass()), $this->getStackMock());
    }

    public function testMiddlewareLogsNothingIfNoTransactionIsLeftOpen()
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('getTransactionNestingLevel')->willReturn(0, 0);

        $connectionRegistry = $this->createStub(ConnectionRegistry::class);
        $connectionRegistry->method('getConnection')->willReturn($connection);

        $middleware = new DoctrineDbalOpenTransactionLoggerMiddleware($connectionRegistry, $this->logger, 'default');

        $middleware->handle(new Envelope(new \stdClass()), $this->getStackMock());

        $this->assertSame([], $this->logger->logs);
    }

    public function testMiddlewareLogsOnceWhenAMessageIsDispatchedFromAHandler()
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(2))->method('getTransactionNestingLevel')->willReturn(0, 1);

        $connectionRegistry = $this->createStub(ConnectionRegistry::class);
        $connectionRegistry->method('getConnection')->willReturn($connection);

        $middleware = new DoctrineDbalOpenTransactionLoggerMiddleware($connectionRegistry, $this->logger, 'default');

        $handler = $this->createMock(MiddlewareInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->willReturnCallback(static fn (Envelope $envelope, StackInterface $stack): Envelope => $middleware->handle($envelope, new StackMiddleware()))
        ;

        $middleware->handle(new Envelope(new \stdClass()), new StackMiddleware($handler));

        $this->assertCount(1, $this->logger->logs);
    }
}
