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
use Doctrine\Persistence\ConnectionRegistry;
use Symfony\Bridge\Doctrine\Messenger\DoctrineDbalPingConnectionMiddleware;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;

class DoctrineDbalPingConnectionMiddlewareTest extends MiddlewareTestCase
{
    public function testMiddlewareReconnectsWhenPingFails()
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('isConnected')->willReturn(true);
        $connection->method('getDatabasePlatform')->willReturn($this->mockPlatform());
        $connection->expects($this->exactly(2))
            ->method('executeQuery')
            ->willReturnCallback(function () {
                static $counter = 0;

                if (1 === ++$counter) {
                    throw $this->createStub(DBALException::class);
                }

                return $this->createStub(Result::class);
            });
        $connection->expects($this->once())->method('close');

        $connectionRegistry = $this->createStub(ConnectionRegistry::class);
        $connectionRegistry->method('getConnection')->willReturn($connection);

        $middleware = new DoctrineDbalPingConnectionMiddleware($connectionRegistry, 'default');

        $envelope = new Envelope(new \stdClass(), [
            new ConsumedByWorkerStamp(),
        ]);
        $middleware->handle($envelope, $this->getStackMock());
    }

    public function testMiddlewarePingsEveryConnectionWhenPassedNoName()
    {
        $fooConnection = $this->connectionExpectingOnePing();
        $barConnection = $this->connectionExpectingOnePing();

        $connectionRegistry = $this->createStub(ConnectionRegistry::class);
        $connectionRegistry->method('getConnectionNames')->willReturn([
            'foo' => 'doctrine.dbal.foo_connection',
            'bar' => 'doctrine.dbal.bar_connection',
        ]);
        $connectionRegistry->method('getConnection')->willReturnMap([
            ['foo', $fooConnection],
            ['bar', $barConnection],
        ]);

        $middleware = new DoctrineDbalPingConnectionMiddleware($connectionRegistry);

        $envelope = new Envelope(new \stdClass(), [
            new ConsumedByWorkerStamp(),
        ]);
        $middleware->handle($envelope, $this->getStackMock());
    }

    public function testMiddlewarePingsHealthyConnectionsWhenAnotherOneFails()
    {
        $failing = $this->createMock(Connection::class);
        $failing->method('isConnected')->willReturn(true);
        $failing->method('getDatabasePlatform')->willReturn($this->mockPlatform());
        $failing->expects($this->exactly(2))
            ->method('executeQuery')
            ->willThrowException($this->createStub(DBALException::class));
        $failing->expects($this->once())->method('close');

        $healthy = $this->connectionExpectingOnePing();

        $connectionRegistry = $this->createStub(ConnectionRegistry::class);
        $connectionRegistry->method('getConnection')->willReturnMap([
            ['broken', $failing],
            ['healthy', $healthy],
        ]);

        $middleware = new DoctrineDbalPingConnectionMiddleware($connectionRegistry, ['broken', 'healthy']);

        $envelope = new Envelope(new \stdClass(), [
            new ConsumedByWorkerStamp(),
        ]);

        $this->expectException(DBALException::class);

        $middleware->handle($envelope, $this->getStackMock(false));
    }

    public function testMiddlewareSkipsPingWhenConnectionIsNotConnected()
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('isConnected')->willReturn(false);
        $connection->expects($this->never())->method('executeQuery');
        $connection->expects($this->never())->method('close');

        $connectionRegistry = $this->createStub(ConnectionRegistry::class);
        $connectionRegistry->method('getConnection')->willReturn($connection);

        $middleware = new DoctrineDbalPingConnectionMiddleware($connectionRegistry, 'default');

        $envelope = new Envelope(new \stdClass(), [
            new ConsumedByWorkerStamp(),
        ]);
        $middleware->handle($envelope, $this->getStackMock());
    }

    public function testMiddlewareDoesntPingInNonWorkerContext()
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('executeQuery');
        $connection->expects($this->never())->method('close');

        $connectionRegistry = $this->createStub(ConnectionRegistry::class);
        $connectionRegistry->method('getConnection')->willReturn($connection);

        $middleware = new DoctrineDbalPingConnectionMiddleware($connectionRegistry, 'default');

        $middleware->handle(new Envelope(new \stdClass()), $this->getStackMock());
    }

    public function testUnknownConnectionThrowsUnrecoverableException()
    {
        $connectionRegistry = $this->createMock(ConnectionRegistry::class);
        $connectionRegistry
            ->expects($this->once())
            ->method('getConnection')
            ->with('unknown_connection')
            ->willThrowException(new \InvalidArgumentException());

        $middleware = new DoctrineDbalPingConnectionMiddleware($connectionRegistry, 'unknown_connection');

        $envelope = new Envelope(new \stdClass(), [
            new ConsumedByWorkerStamp(),
        ]);

        $this->expectException(UnrecoverableMessageHandlingException::class);

        $middleware->handle($envelope, $this->getStackMock(false));
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
}
