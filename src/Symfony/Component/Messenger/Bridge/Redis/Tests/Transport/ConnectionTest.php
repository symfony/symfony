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
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The given Redis DSN is invalid.');

        Connection::fromDsn('redis://');
    }

    public function testFromDsn()
    {
        $this->assertEquals(
            new Connection(['stream' => 'queue', 'delete_after_ack' => true], [
                'host' => 'localhost',
                'port' => 6379,
            ], [], $this->createMock(\Redis::class)),
            Connection::fromDsn('redis://localhost/queue?delete_after_ack=1', [], $this->createMock(\Redis::class))
        );
    }

    public function testFromDsnOnUnixSocket()
    {
        $this->assertEquals(
            new Connection(['stream' => 'queue', 'delete_after_ack' => true], [
                'host' => '/var/run/redis/redis.sock',
                'port' => 0,
            ], [], $redis = $this->createMock(\Redis::class)),
            Connection::fromDsn('redis:///var/run/redis/redis.sock', ['stream' => 'queue', 'delete_after_ack' => true], $redis)
        );
    }

    public function testFromDsnWithOptions()
    {
        $this->assertEquals(
            Connection::fromDsn('redis://localhost', ['stream' => 'queue', 'group' => 'group1', 'consumer' => 'consumer1', 'auto_setup' => false, 'serializer' => 2, 'delete_after_ack' => true], $this->createMock(\Redis::class)),
            Connection::fromDsn('redis://localhost/queue/group1/consumer1?serializer=2&auto_setup=0&delete_after_ack=1', [], $this->createMock(\Redis::class))
        );
    }

    public function testFromDsnWithOptionsAndTrailingSlash()
    {
        $this->assertEquals(
            Connection::fromDsn('redis://localhost/', ['stream' => 'queue', 'group' => 'group1', 'consumer' => 'consumer1', 'auto_setup' => false, 'serializer' => 2, 'delete_after_ack' => true], $this->createMock(\Redis::class)),
            Connection::fromDsn('redis://localhost/queue/group1/consumer1?serializer=2&auto_setup=0&delete_after_ack=1', [], $this->createMock(\Redis::class))
        );
    }

    /**
     * @group legacy
     */
    public function testFromDsnWithTls()
    {
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())
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
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())
            ->method('connect')
            ->with('tls://127.0.0.1', 6379)
            ->willReturn(true);

        Connection::fromDsn('redis://127.0.0.1', ['tls' => true], $redis);
    }

    public function testFromDsnWithRedissScheme()
    {
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())
            ->method('connect')
            ->with('tls://127.0.0.1', 6379)
            ->willReturn(true);

        Connection::fromDsn('rediss://127.0.0.1?delete_after_ack=true', [], $redis);
    }

    public function testFromDsnWithQueryOptions()
    {
        $this->assertEquals(
            new Connection(['stream' => 'queue', 'group' => 'group1', 'consumer' => 'consumer1', 'delete_after_ack' => true], [
                'host' => 'localhost',
                'port' => 6379,
            ], [
                'serializer' => 2,
            ], $this->createMock(\Redis::class)),
            Connection::fromDsn('redis://localhost/queue/group1/consumer1?serializer=2&delete_after_ack=1', [], $this->createMock(\Redis::class))
        );
    }

    public function testFromDsnWithMixDsnQueryOptions()
    {
        $this->assertEquals(
            Connection::fromDsn('redis://localhost/queue/group1?serializer=2', ['consumer' => 'specific-consumer', 'delete_after_ack' => true], $this->createMock(\Redis::class)),
            Connection::fromDsn('redis://localhost/queue/group1/specific-consumer?serializer=2&delete_after_ack=1', [], $this->createMock(\Redis::class))
        );

        $this->assertEquals(
            Connection::fromDsn('redis://localhost/queue/group1/consumer1', ['consumer' => 'specific-consumer', 'delete_after_ack' => true], $this->createMock(\Redis::class)),
            Connection::fromDsn('redis://localhost/queue/group1/consumer1', ['delete_after_ack' => true], $this->createMock(\Redis::class))
        );
    }

    /**
     * @group legacy
     */
    public function testDeprecationIfInvalidOptionIsPassedWithDsn()
    {
        $this->expectDeprecation('Since symfony/messenger 5.1: Invalid option(s) "foo" passed to the Redis Messenger transport. Passing invalid options is deprecated.');
        Connection::fromDsn('redis://localhost/queue?foo=bar', [], $this->createMock(\Redis::class));
    }

    public function testRedisClusterInstanceIsSupported()
    {
        $redis = $this->createMock(\RedisCluster::class);
        $this->assertInstanceOf(Connection::class, new Connection([], [], [], $redis));
    }

    public function testKeepGettingPendingMessages()
    {
        $redis = $this->createMock(\Redis::class);

        $redis->expects($this->exactly(3))->method('xreadgroup')
            ->with('symfony', 'consumer', ['queue' => 0], 1, 1)
            ->willReturn(['queue' => [['message' => json_encode(['body' => 'Test', 'headers' => []])]]]);

        $connection = Connection::fromDsn('redis://localhost/queue', ['delete_after_ack' => true], $redis);
        $this->assertNotNull($connection->get());
        $this->assertNotNull($connection->get());
        $this->assertNotNull($connection->get());
    }

    /**
     * @param string|array $expected
     *
     * @dataProvider provideAuthDsn
     */
    public function testAuth($expected, string $dsn)
    {
        $redis = $this->createMock(\Redis::class);

        $redis->expects($this->exactly(1))->method('auth')
            ->with($expected)
            ->willReturn(true);

        Connection::fromDsn($dsn, ['delete_after_ack' => true], $redis);
    }

    public static function provideAuthDsn(): \Generator
    {
        yield 'Password only' => ['password', 'redis://password@localhost/queue'];
        yield 'User and password' => [['user', 'password'], 'redis://user:password@localhost/queue'];
        yield 'User and colon' => ['user', 'redis://user:@localhost/queue'];
        yield 'Colon and password' => ['password', 'redis://:password@localhost/queue'];
        yield 'Colon and falsy password' => ['0', 'redis://:0@localhost/queue'];
    }

    public function testAuthFromOptions()
    {
        $redis = $this->createMock(\Redis::class);

        $redis->expects($this->exactly(1))->method('auth')
            ->with('password')
            ->willReturn(true);

        Connection::fromDsn('redis://localhost/queue', ['auth' => 'password', 'delete_after_ack' => true], $redis);
    }

    public function testAuthFromOptionsAndDsn()
    {
        $redis = $this->createMock(\Redis::class);

        $redis->expects($this->exactly(1))->method('auth')
            ->with('password2')
            ->willReturn(true);

        Connection::fromDsn('redis://password1@localhost/queue', ['auth' => 'password2', 'delete_after_ack' => true], $redis);
    }

    public function testNoAuthWithEmptyPassword()
    {
        $redis = $this->createMock(\Redis::class);

        $redis->expects($this->exactly(0))->method('auth')
            ->with('')
            ->willThrowException(new \RuntimeException());

        Connection::fromDsn('redis://@localhost/queue', ['delete_after_ack' => true], $redis);
    }

    public function testAuthZeroPassword()
    {
        $redis = $this->createMock(\Redis::class);

        $redis->expects($this->exactly(1))->method('auth')
            ->with('0')
            ->willReturn(true);

        Connection::fromDsn('redis://0@localhost/queue', ['delete_after_ack' => true], $redis);
    }

    public function testFailedAuth()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Redis connection ');
        $redis = $this->createMock(\Redis::class);

        $redis->expects($this->exactly(1))->method('auth')
            ->with('password')
            ->willReturn(false);

        Connection::fromDsn('redis://password@localhost/queue', ['delete_after_ack' => true], $redis);
    }

    public function testGetPendingMessageFirst()
    {
        $redis = $this->createMock(\Redis::class);

        $redis->expects($this->exactly(1))->method('xreadgroup')
            ->with('symfony', 'consumer', ['queue' => '0'], 1, 1)
            ->willReturn(['queue' => [['message' => '{"body":"1","headers":[]}']]]);

        $connection = Connection::fromDsn('redis://localhost/queue', ['delete_after_ack' => true], $redis);
        $message = $connection->get();

        $this->assertSame([
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
        $redis = $this->createMock(\Redis::class);

        $redis->expects($this->exactly(3))->method('xreadgroup')
            ->willReturnCallback(function (...$args) {
                static $series = [
                    // first call for pending messages
                    [['symfony', 'consumer', ['queue' => '0'], 1, 1], []],
                    // second call because of claimed message (redisid-123)
                    [['symfony', 'consumer', ['queue' => '0'], 1, 1], []],
                    // third call because of no result (other consumer claimed message redisid-123)
                    [['symfony', 'consumer', ['queue' => '>'], 1, 1], []],
                ];

                [$expectedArgs, $return] = array_shift($series);
                $this->assertSame($expectedArgs, $args);

                return $return;
            })
        ;

        $redis->expects($this->once())->method('xpending')->willReturn([[
            0 => 'redisid-123', // message-id
            1 => 'consumer-2', // consumer-name
            2 => 3600001, // idle
        ]]);

        $redis->expects($this->exactly(1))->method('xclaim')
            ->with('queue', 'symfony', 'consumer', 3600000, ['redisid-123'], ['JUSTID'])
            ->willReturn([]);

        $connection = Connection::fromDsn('redis://localhost/queue', ['delete_after_ack' => true], $redis);
        $connection->get();
    }

    public function testClaimAbandonedMessage()
    {
        $redis = $this->createMock(\Redis::class);

        $redis->expects($this->exactly(2))->method('xreadgroup')
            ->willReturnCallback(function (...$args) {
                static $series = [
                    // first call for pending messages
                    [['symfony', 'consumer', ['queue' => '0'], 1, 1], []],
                    // second call because of claimed message (redisid-123)
                    [['symfony', 'consumer', ['queue' => '0'], 1, 1], ['queue' => [['message' => '{"body":"1","headers":[]}']]]],
                ];

                [$expectedArgs, $return] = array_shift($series);
                $this->assertSame($expectedArgs, $args);

                return $return;
            })
        ;

        $redis->expects($this->once())->method('xpending')->willReturn([[
            0 => 'redisid-123', // message-id
            1 => 'consumer-2', // consumer-name
            2 => 3600001, // idle
        ]]);

        $redis->expects($this->exactly(1))->method('xclaim')
            ->with('queue', 'symfony', 'consumer', 3600000, ['redisid-123'], ['JUSTID'])
            ->willReturn([]);

        $connection = Connection::fromDsn('redis://localhost/queue', ['delete_after_ack' => true], $redis);
        $connection->get();
    }

    public function testUnexpectedRedisError()
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Redis error happens');
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())->method('xreadgroup')->willReturn(false);
        $redis->expects($this->once())->method('getLastError')->willReturn('Redis error happens');

        $connection = Connection::fromDsn('redis://localhost/queue', ['auto_setup' => false, 'delete_after_ack' => true], $redis);
        $connection->get();
    }

    public function testMaxEntries()
    {
        $redis = $this->createMock(\Redis::class);

        $redis->expects($this->exactly(1))->method('xadd')
            ->with('queue', '*', ['message' => '{"body":"1","headers":[]}'], 20000, true)
            ->willReturn('1');

        $connection = Connection::fromDsn('redis://localhost/queue?stream_max_entries=20000', ['delete_after_ack' => true], $redis);
        $connection->add('1', []);
    }

    public function testDeleteAfterAck()
    {
        $redis = $this->createMock(\Redis::class);

        $redis->expects($this->exactly(1))->method('xack')
            ->with('queue', 'symfony', ['1'])
            ->willReturn(1);
        $redis->expects($this->exactly(1))->method('xdel')
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

        Connection::fromDsn('redis://localhost/queue', [], $this->createMock(\Redis::class));
    }

    public function testDeleteAfterReject()
    {
        $redis = $this->createMock(\Redis::class);

        $redis->expects($this->exactly(1))->method('xack')
            ->with('queue', 'symfony', ['1'])
            ->willReturn(1);
        $redis->expects($this->exactly(1))->method('xdel')
            ->with('queue', ['1'])
            ->willReturn(1);

        $connection = Connection::fromDsn('redis://localhost/queue?delete_after_reject=true', ['delete_after_ack' => true], $redis);
        $connection->reject('1');
    }

    public function testLastErrorGetsCleared()
    {
        $redis = $this->createMock(\Redis::class);

        $redis->expects($this->once())->method('xadd')->willReturn('0');
        $redis->expects($this->once())->method('xack')->willReturn(0);

        $redis->method('getLastError')->willReturn('xadd error', 'xack error');
        $redis->expects($this->exactly(2))->method('clearLastError');

        $connection = Connection::fromDsn('redis://localhost/messenger-clearlasterror', ['auto_setup' => false, 'delete_after_ack' => true], $redis);

        try {
            $connection->add('message', []);
        } catch (TransportException $e) {
        }

        $this->assertSame('xadd error', $e->getMessage());

        try {
            $connection->ack('1');
        } catch (TransportException $e) {
        }

        $this->assertSame('xack error', $e->getMessage());
    }
}
