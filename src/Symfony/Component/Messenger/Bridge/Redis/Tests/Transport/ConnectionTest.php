<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Redis\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Messenger\Bridge\Redis\Transport\Connection;
use Symfony\Component\Messenger\Exception\TransportException;

/**
 * @requires extension redis >= 4.3.0
 */
class ConnectionTest extends TestCase
{
    use ExpectDeprecationTrait;

    public function testFromInvalidDsn()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('The given Redis DSN "redis://" is invalid.');

        Connection::fromDsn('redis://');
    }

    public function testFromDsn()
    {
        self::assertEquals(new Connection(['stream' => 'queue', 'delete_after_ack' => true], [
            'host' => 'localhost',
            'port' => 6379,
        ], [], self::createMock(\Redis::class)), Connection::fromDsn('redis://localhost/queue?delete_after_ack=1', [], self::createMock(\Redis::class)));
    }

    public function testFromDsnOnUnixSocket()
    {
        self::assertEquals(new Connection(['stream' => 'queue', 'delete_after_ack' => true], [
            'host' => '/var/run/redis/redis.sock',
            'port' => 0,
        ], [], $redis = self::createMock(\Redis::class)), Connection::fromDsn('redis:///var/run/redis/redis.sock', ['stream' => 'queue', 'delete_after_ack' => true], $redis));
    }

    public function testFromDsnWithOptions()
    {
        self::assertEquals(Connection::fromDsn('redis://localhost', ['stream' => 'queue', 'group' => 'group1', 'consumer' => 'consumer1', 'auto_setup' => false, 'serializer' => 2, 'delete_after_ack' => true], self::createMock(\Redis::class)), Connection::fromDsn('redis://localhost/queue/group1/consumer1?serializer=2&auto_setup=0&delete_after_ack=1', [], self::createMock(\Redis::class)));
    }

    public function testFromDsnWithOptionsAndTrailingSlash()
    {
        self::assertEquals(Connection::fromDsn('redis://localhost/', ['stream' => 'queue', 'group' => 'group1', 'consumer' => 'consumer1', 'auto_setup' => false, 'serializer' => 2, 'delete_after_ack' => true], self::createMock(\Redis::class)), Connection::fromDsn('redis://localhost/queue/group1/consumer1?serializer=2&auto_setup=0&delete_after_ack=1', [], self::createMock(\Redis::class)));
    }

    /**
     * @group legacy
     */
    public function testFromDsnWithTls()
    {
        $redis = self::createMock(\Redis::class);
        $redis->expects(self::once())
            ->method('connect')
            ->with('tls://127.0.0.1', 6379)
            ->willReturn(true);

        Connection::fromDsn('redis://127.0.0.1?tls=1', [], $redis);
    }

    /**
     * @group legacy
     */
    public function testFromDsnWithTlsOption()
    {
        $redis = self::createMock(\Redis::class);
        $redis->expects(self::once())
            ->method('connect')
            ->with('tls://127.0.0.1', 6379)
            ->willReturn(true);

        Connection::fromDsn('redis://127.0.0.1', ['tls' => true], $redis);
    }

    public function testFromDsnWithRedissScheme()
    {
        $redis = self::createMock(\Redis::class);
        $redis->expects(self::once())
            ->method('connect')
            ->with('tls://127.0.0.1', 6379)
            ->willReturn(true);

        Connection::fromDsn('rediss://127.0.0.1?delete_after_ack=true', [], $redis);
    }

    public function testFromDsnWithQueryOptions()
    {
        self::assertEquals(new Connection(['stream' => 'queue', 'group' => 'group1', 'consumer' => 'consumer1', 'delete_after_ack' => true], [
            'host' => 'localhost',
            'port' => 6379,
        ], [
            'serializer' => 2,
        ], self::createMock(\Redis::class)), Connection::fromDsn('redis://localhost/queue/group1/consumer1?serializer=2&delete_after_ack=1', [], self::createMock(\Redis::class)));
    }

    public function testFromDsnWithMixDsnQueryOptions()
    {
        self::assertEquals(Connection::fromDsn('redis://localhost/queue/group1?serializer=2', ['consumer' => 'specific-consumer', 'delete_after_ack' => true], self::createMock(\Redis::class)), Connection::fromDsn('redis://localhost/queue/group1/specific-consumer?serializer=2&delete_after_ack=1', [], self::createMock(\Redis::class)));

        self::assertEquals(Connection::fromDsn('redis://localhost/queue/group1/consumer1', ['consumer' => 'specific-consumer', 'delete_after_ack' => true], self::createMock(\Redis::class)), Connection::fromDsn('redis://localhost/queue/group1/consumer1', ['delete_after_ack' => true], self::createMock(\Redis::class)));
    }

    /**
     * @group legacy
     */
    public function testDeprecationIfInvalidOptionIsPassedWithDsn()
    {
        $this->expectDeprecation('Since symfony/messenger 5.1: Invalid option(s) "foo" passed to the Redis Messenger transport. Passing invalid options is deprecated.');
        Connection::fromDsn('redis://localhost/queue?foo=bar', [], self::createMock(\Redis::class));
    }

    public function testRedisClusterInstanceIsSupported()
    {
        $redis = self::createMock(\RedisCluster::class);
        self::assertInstanceOf(Connection::class, new Connection([], [], [], $redis));
    }

    public function testKeepGettingPendingMessages()
    {
        $redis = self::createMock(\Redis::class);

        $redis->expects(self::exactly(3))->method('xreadgroup')
            ->with('symfony', 'consumer', ['queue' => 0], 1, null)
            ->willReturn(['queue' => [['message' => json_encode(['body' => 'Test', 'headers' => []])]]]);

        $connection = Connection::fromDsn('redis://localhost/queue', ['delete_after_ack' => true], $redis);
        self::assertNotNull($connection->get());
        self::assertNotNull($connection->get());
        self::assertNotNull($connection->get());
    }

    /**
     * @param string|array $expected
     *
     * @dataProvider provideAuthDsn
     */
    public function testAuth($expected, string $dsn)
    {
        $redis = self::createMock(\Redis::class);

        $redis->expects(self::exactly(1))->method('auth')
            ->with($expected)
            ->willReturn(true);

        Connection::fromDsn($dsn, ['delete_after_ack' => true], $redis);
    }

    public function provideAuthDsn(): \Generator
    {
        yield 'Password only' => ['password', 'redis://password@localhost/queue'];
        yield 'User and password' => [['user', 'password'], 'redis://user:password@localhost/queue'];
        yield 'User and colon' => ['user', 'redis://user:@localhost/queue'];
        yield 'Colon and password' => ['password', 'redis://:password@localhost/queue'];
        yield 'Colon and falsy password' => ['0', 'redis://:0@localhost/queue'];
    }

    public function testAuthFromOptions()
    {
        $redis = self::createMock(\Redis::class);

        $redis->expects(self::exactly(1))->method('auth')
            ->with('password')
            ->willReturn(true);

        Connection::fromDsn('redis://localhost/queue', ['auth' => 'password', 'delete_after_ack' => true], $redis);
    }

    public function testAuthFromOptionsAndDsn()
    {
        $redis = self::createMock(\Redis::class);

        $redis->expects(self::exactly(1))->method('auth')
            ->with('password2')
            ->willReturn(true);

        Connection::fromDsn('redis://password1@localhost/queue', ['auth' => 'password2', 'delete_after_ack' => true], $redis);
    }

    public function testNoAuthWithEmptyPassword()
    {
        $redis = self::createMock(\Redis::class);

        $redis->expects(self::exactly(0))->method('auth')
            ->with('')
            ->willThrowException(new \RuntimeException());

        Connection::fromDsn('redis://@localhost/queue', ['delete_after_ack' => true], $redis);
    }

    public function testAuthZeroPassword()
    {
        $redis = self::createMock(\Redis::class);

        $redis->expects(self::exactly(1))->method('auth')
            ->with('0')
            ->willReturn(true);

        Connection::fromDsn('redis://0@localhost/queue', ['delete_after_ack' => true], $redis);
    }

    public function testFailedAuth()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Redis connection ');
        $redis = self::createMock(\Redis::class);

        $redis->expects(self::exactly(1))->method('auth')
            ->with('password')
            ->willReturn(false);

        Connection::fromDsn('redis://password@localhost/queue', ['delete_after_ack' => true], $redis);
    }

    public function testGetPendingMessageFirst()
    {
        $redis = self::createMock(\Redis::class);

        $redis->expects(self::exactly(1))->method('xreadgroup')
            ->with('symfony', 'consumer', ['queue' => '0'], 1, null)
            ->willReturn(['queue' => [['message' => '{"body":"1","headers":[]}']]]);

        $connection = Connection::fromDsn('redis://localhost/queue', ['delete_after_ack' => true], $redis);
        $message = $connection->get();

        self::assertSame([
            'id' => 0,
            'data' => [
                'message' => json_encode([
                    'body' => '1',
                    'headers' => [],
                ]),
            ],
        ], $message);
    }

    public function testClaimAbandonedMessageWithRaceCondition()
    {
        $redis = self::createMock(\Redis::class);

        $redis->expects(self::exactly(3))->method('xreadgroup')
            ->withConsecutive(
                ['symfony', 'consumer', ['queue' => '0'], 1, null], // first call for pending messages
                ['symfony', 'consumer', ['queue' => '0'], 1, null], // second call because of claimed message (redisid-123)
                ['symfony', 'consumer', ['queue' => '>'], 1, null] // third call because of no result (other consumer claimed message redisid-123)
            )
            ->willReturnOnConsecutiveCalls([], [], []);

        $redis->expects(self::once())->method('xpending')->willReturn([[
            0 => 'redisid-123', // message-id
            1 => 'consumer-2', // consumer-name
            2 => 3600001, // idle
        ]]);

        $redis->expects(self::exactly(1))->method('xclaim')
            ->with('queue', 'symfony', 'consumer', 3600000, ['redisid-123'], ['JUSTID'])
            ->willReturn([]);

        $connection = Connection::fromDsn('redis://localhost/queue', ['delete_after_ack' => true], $redis);
        $connection->get();
    }

    public function testClaimAbandonedMessage()
    {
        $redis = self::createMock(\Redis::class);

        $redis->expects(self::exactly(2))->method('xreadgroup')
            ->withConsecutive(
                ['symfony', 'consumer', ['queue' => '0'], 1, null], // first call for pending messages
                ['symfony', 'consumer', ['queue' => '0'], 1, null] // second call because of claimed message (redisid-123)
            )
            ->willReturnOnConsecutiveCalls(
                [], // first call returns no result
                ['queue' => [['message' => '{"body":"1","headers":[]}']]] // second call returns claimed message (redisid-123)
            );

        $redis->expects(self::once())->method('xpending')->willReturn([[
            0 => 'redisid-123', // message-id
            1 => 'consumer-2', // consumer-name
            2 => 3600001, // idle
        ]]);

        $redis->expects(self::exactly(1))->method('xclaim')
            ->with('queue', 'symfony', 'consumer', 3600000, ['redisid-123'], ['JUSTID'])
            ->willReturn([]);

        $connection = Connection::fromDsn('redis://localhost/queue', ['delete_after_ack' => true], $redis);
        $connection->get();
    }

    public function testUnexpectedRedisError()
    {
        self::expectException(TransportException::class);
        self::expectExceptionMessage('Redis error happens');
        $redis = self::createMock(\Redis::class);
        $redis->expects(self::once())->method('xreadgroup')->willReturn(false);
        $redis->expects(self::once())->method('getLastError')->willReturn('Redis error happens');

        $connection = Connection::fromDsn('redis://localhost/queue', ['auto_setup' => false, 'delete_after_ack' => true], $redis);
        $connection->get();
    }

    public function testMaxEntries()
    {
        $redis = self::createMock(\Redis::class);

        $redis->expects(self::exactly(1))->method('xadd')
            ->with('queue', '*', ['message' => '{"body":"1","headers":[]}'], 20000, true)
            ->willReturn('1');

        $connection = Connection::fromDsn('redis://localhost/queue?stream_max_entries=20000', ['delete_after_ack' => true], $redis);
        $connection->add('1', []);
    }

    public function testDeleteAfterAck()
    {
        $redis = self::createMock(\Redis::class);

        $redis->expects(self::exactly(1))->method('xack')
            ->with('queue', 'symfony', ['1'])
            ->willReturn(1);
        $redis->expects(self::exactly(1))->method('xdel')
            ->with('queue', ['1'])
            ->willReturn(1);

        $connection = Connection::fromDsn('redis://localhost/queue?delete_after_ack=true', [], $redis);
        $connection->ack('1');
    }

    /**
     * @group legacy
     */
    public function testLegacyOmitDeleteAfterAck()
    {
        $this->expectDeprecation('Since symfony/redis-messenger 5.4: Not setting the "delete_after_ack" boolean option explicitly is deprecated, its default value will change to true in 6.0.');

        Connection::fromDsn('redis://localhost/queue', [], self::createMock(\Redis::class));
    }

    public function testDeleteAfterReject()
    {
        $redis = self::createMock(\Redis::class);

        $redis->expects(self::exactly(1))->method('xack')
            ->with('queue', 'symfony', ['1'])
            ->willReturn(1);
        $redis->expects(self::exactly(1))->method('xdel')
            ->with('queue', ['1'])
            ->willReturn(1);

        $connection = Connection::fromDsn('redis://localhost/queue?delete_after_reject=true', ['delete_after_ack' => true], $redis);
        $connection->reject('1');
    }

    public function testLastErrorGetsCleared()
    {
        $redis = self::createMock(\Redis::class);

        $redis->expects(self::once())->method('xadd')->willReturn('0');
        $redis->expects(self::once())->method('xack')->willReturn(0);

        $redis->method('getLastError')->willReturnOnConsecutiveCalls('xadd error', 'xack error');
        $redis->expects(self::exactly(2))->method('clearLastError');

        $connection = Connection::fromDsn('redis://localhost/messenger-clearlasterror', ['auto_setup' => false, 'delete_after_ack' => true], $redis);

        try {
            $connection->add('message', []);
        } catch (TransportException $e) {
        }

        self::assertSame('xadd error', $e->getMessage());

        try {
            $connection->ack('1');
        } catch (TransportException $e) {
        }

        self::assertSame('xack error', $e->getMessage());
    }
}
