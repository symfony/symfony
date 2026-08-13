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
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Bridge\Doctrine\Messenger\DoctrineDbalCloseConnectionMiddleware;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\AddBusNameStampMiddleware;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;

class DoctrineDbalCloseConnectionMiddlewareTest extends MiddlewareTestCase
{
    public function testMiddlewareClosesConnections()
    {
        $fooConnection = $this->createMock(Connection::class);
        $fooConnection->expects($this->once())->method('close');

        $barConnection = $this->createMock(Connection::class);
        $barConnection->expects($this->once())->method('close');

        $connectionRegistry = $this->createStub(ConnectionRegistry::class);
        $connectionRegistry->method('getConnection')->willReturn($fooConnection, $barConnection);

        new DoctrineDbalCloseConnectionMiddleware($connectionRegistry, ['foo', 'bar'])->handle(
            new Envelope(new \stdClass(), [new ConsumedByWorkerStamp()]),
            $this->getStackMock(),
        );
    }

    #[TestWith(['foo'])]
    #[TestWith([['foo']])]
    public function testMiddlewareDoesntCloseConnectionsInNonWorkerContext(array|string $connectionNames)
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('close');

        $connectionRegistry = $this->createStub(ConnectionRegistry::class);
        $connectionRegistry->method('getConnection')->willReturn($connection);

        new DoctrineDbalCloseConnectionMiddleware($connectionRegistry, $connectionNames)->handle(
            new Envelope(new \stdClass()),
            $this->getStackMock(),
        );
    }

    public function testMiddlewareClosesAllConnectionsWhenUsedInABus()
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('close');

        $connectionRegistry = $this->createStub(ConnectionRegistry::class);
        $connectionRegistry->method('getConnectionNames')->willReturn(['default' => 'doctrine.dbal.default_connection']);
        $connectionRegistry->method('getConnection')->willReturn($connection);

        $bus = new MessageBus([
            new AddBusNameStampMiddleware('bus'),
            new DoctrineDbalCloseConnectionMiddleware($connectionRegistry),
        ]);

        $bus->dispatch(new Envelope(new \stdClass(), [new ConsumedByWorkerStamp()]));
    }
}
