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
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Messenger\AbstractDoctrineDbalMiddleware;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Middleware\StackInterface;

class AbstractDoctrineDbalMiddlewareTest extends TestCase
{
    public function testGetDefaultConnection()
    {
        $defaultConnection = $this->createStub(Connection::class);

        $connectionRegistry = $this->createMock(ConnectionRegistry::class);
        $connectionRegistry
            ->expects($this->once())
            ->method('getConnection')
            ->with(null)
            ->willReturn($defaultConnection)
        ;

        $this->assertSame($defaultConnection, new DummyDbalMiddleware($connectionRegistry)->connection());
    }

    public function testGetNamedConnection()
    {
        $fooConnection = $this->createStub(Connection::class);

        $connectionRegistry = $this->createMock(ConnectionRegistry::class);
        $connectionRegistry
            ->expects($this->once())
            ->method('getConnection')
            ->with('foo')
            ->willReturn($fooConnection)
        ;

        $this->assertSame($fooConnection, new DummyDbalMiddleware($connectionRegistry)->connection('foo'));
    }

    public function testGetMissingConnection()
    {
        $connectionRegistry = $this->createStub(ConnectionRegistry::class);
        $connectionRegistry
            ->method('getConnection')
            ->willThrowException(new \InvalidArgumentException('Doctrine default Connection named "foo" does not exist.'))
        ;

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('Doctrine default Connection named "foo" does not exist.');

        new DummyDbalMiddleware($connectionRegistry)->connection('foo');
    }

    public function testGetInvalidConnection()
    {
        $connectionRegistry = $this->createStub(ConnectionRegistry::class);
        $connectionRegistry->method('getConnection')->willReturn(new \stdClass());

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('Expected "foo" to be a DBAL connection, but got a "stdClass".');

        new DummyDbalMiddleware($connectionRegistry)->connection('foo');
    }

    public function testGetInvalidDefaultConnection()
    {
        $connectionRegistry = $this->createStub(ConnectionRegistry::class);
        $connectionRegistry->method('getDefaultConnectionName')->willReturn('default');
        $connectionRegistry->method('getConnection')->willReturn(new \stdClass());

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('Expected "default" to be a DBAL connection, but got a "stdClass".');

        new DummyDbalMiddleware($connectionRegistry)->connection();
    }

    public function testGetAllConnectionsSkipsNonDbalOnes()
    {
        $fooConnection = $this->createStub(Connection::class);
        $barConnection = $this->createStub(Connection::class);

        $connectionRegistry = $this->createStub(ConnectionRegistry::class);
        $connectionRegistry
            ->method('getConnectionNames')
            ->willReturn([
                'foo' => 'doctrine.dbal.foo_connection',
                'bar' => 'doctrine.dbal.bar_connection',
                'baz' => 'doctrine.dbal.baz_connection',
            ]);
        $connectionRegistry
            ->method('getConnection')
            ->willReturnMap([
                ['foo', $fooConnection],
                ['bar', $barConnection],
                ['baz', new \stdClass()],
            ])
        ;

        $this->assertSame(['foo' => $fooConnection, 'bar' => $barConnection], new DummyDbalMiddleware($connectionRegistry)->connections());
    }

    public function testGetNamedConnections()
    {
        $fooConnection = $this->createStub(Connection::class);

        $connectionRegistry = $this->createStub(ConnectionRegistry::class);
        $connectionRegistry->method('getConnection')->willReturn($fooConnection);

        $this->assertSame(['foo' => $fooConnection], new DummyDbalMiddleware($connectionRegistry)->connections(['foo']));
    }

    public function testGetMissingNamedConnection()
    {
        $connectionRegistry = $this->createStub(ConnectionRegistry::class);
        $connectionRegistry
            ->method('getConnection')
            ->willThrowException(new \InvalidArgumentException('Doctrine default Connection named "foo" does not exist.'))
        ;

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('Doctrine default Connection named "foo" does not exist.');

        new DummyDbalMiddleware($connectionRegistry)->connections(['foo']);
    }

    public function testGetInvalidNamedConnection()
    {
        $connectionRegistry = $this->createStub(ConnectionRegistry::class);
        $connectionRegistry->method('getConnection')->willReturn(new \stdClass());

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('Expected "foo" to be a DBAL connection, but got a "stdClass".');

        new DummyDbalMiddleware($connectionRegistry)->connections(['foo']);
    }
}

class DummyDbalMiddleware extends AbstractDoctrineDbalMiddleware
{
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        return $stack->next()->handle($envelope, $stack);
    }

    public function connection(?string $name = null): Connection
    {
        return $this->getConnection($name);
    }

    /**
     * @param list<string> $names
     *
     * @return array<string, Connection>
     */
    public function connections(array $names = []): array
    {
        return $this->getConnections($names);
    }
}
