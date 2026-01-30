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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\Redis\Transport\Connection;
use Symfony\Component\Messenger\Exception\TransportException;

#[RequiresPhpExtension('redis')]
class ConnectionTest extends TestCase
{
    public function testFromInvalidDsn(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The given Redis DSN is invalid.');

        Connection::fromDsn('redis://');
    }

    public function testFromDsn(): void
    {
        $this->assertEqualsConnection(
            new Connection([
                'stream' => 'queue',
                'host' => 'localhost',
                'port' => 6379,
            ], $this->createRedisMock()),
            Connection::fromDsn('redis://localhost/queue?delete_after_ack=1', [], $this->createRedisMock())
        );
    }

    public function testFromDsnOnUnixSocket(): void
    {
        $this->assertEqualsConnection(
            new Connection([
                'stream' => 'queue',
                'host' => '/var/run/redis/redis.sock',
                'port' => 0,
            ], $this->createRedisMock()),
            Connection::fromDsn('redis:///var/run/redis/redis.sock', ['stream' => 'queue'], $this->createRedisMock())
        );
    }

    public function testFromDsnWithOptions(): void
    {
        $this->assertEqualsConnection(
            Connection::fromDsn('redis://localhost', ['stream' => 'queue', 'group' => 'group1', 'consumer' => 'consumer1', 'auto_setup' => false, 'serializer' => 2], $this->createRedisMock()),
            Connection::fromDsn('redis://localhost/queue/group1/consumer1?serializer=2&auto_setup=0', [], $this->createRedisMock())
        );
    }

    public function testFromDsnWithOptionsAndTrailingSlash(): void
    {
        $this->assertEqualsConnection(
            Connection::fromDsn('redis://localhost/', ['stream' => 'queue', 'group' => 'group1', 'consumer' => 'consumer1', 'auto_setup' => false, 'serializer' => 2], $this->createRedisMock()),
            Connection::fromDsn('redis://localhost/queue/group1/consumer1?serializer=2&auto_setup=0', [], $this->createRedisMock())
        );
    }

    public function testFromDsnWithRedissScheme(): void
    {
        $redis = $this->createRedisMock();
        $redis->expects($this->once())
            ->method('connect')
            ->with('tls://127.0.0.1', 6379)
            ->willReturn(true);
        $redis->expects($this->any())
            ->method('isConnected')
            ->willReturnOnConsecutiveCalls(false, true);

        Connection::fromDsn('rediss://127.0.0.1', [], $redis);
    }

    public function testFromDsnWithQueryOptions(): void
    {
        $this->assertEqualsConnection(
            new Connection([
                'stream' => 'queue',
                'group' => 'group1',
                'consumer' => 'consumer1',
                'host' => 'localhost',
                'port' => 6379,
                'serializer' => 2,
            ], $this->createRedisMock()),
            Connection::fromDsn('redis://localhost/queue/group1/consumer1?serializer=2', [], $this->createRedisMock())
        );
    }

    public function testFromDsnWithMixDsnQueryOptions(): void
    {
        $this->assertEqualsConnection(
            Connection::fromDsn('redis://localhost/queue/group1?serializer=2', ['consumer' => 'specific-consumer'], $this->createRedisMock()),
            Connection::fromDsn('redis://localhost/queue/group1/specific-consumer?serializer=2', [], $this->createRedisMock())
        );

        $this->assertEqualsConnection(
            Connection::fromDsn('redis://localhost/queue/group1/consumer1', ['consumer' => 'specific-consumer'], $this->createRedisMock()),
            Connection::fromDsn('redis://localhost/queue/group1/consumer1', [], $this->createRedisMock())
        );
    }

    public function testRedisClusterInstanceIsSupported(): void
    {
        $redis = $this->createRedisMock();
        $this->assertInstanceOf(Connection::class, new Connection([], $redis));
    }

    public function testKeepGettingPendingMessages(): void
    {
        $redis = $this->createRedisMock();

        $redis->expects($this->exactly(3))->method('xreadgroup')
            ->with('symfony', 'consumer', ['queue' => 0], 1, 1)
            ->willReturn(['queue' => [['message' => json_encode(['body' => 'Test', 'headers' => []])]]]);

        $connection = Connection::fromDsn('redis://localhost/queue', [], $redis);
        $this->assertNotNull($connection->get());
        $this->assertNotNull($connection->get());
        $this->assertNotNull($connection->get());
    }

    #[DataProvider('provideAuthDsn')]
    public function testAuth(string|array $expected, string $dsn): void
    {
        $redis = $this->createRedisMock();

        $redis->expects($this->exactly(1))->method('auth')
            ->with($expected)
            ->willReturn(true);

        Connection::fromDsn($dsn, [], $redis);
    }

    public static function provideAuthDsn(): \Generator
    {
        yield 'Password only' => ['password', 'redis://password@localhost/queue'];
        yield 'User and password' => [['user', 'password'], 'redis://user:password@localhost/queue'];
        yield 'User and colon' => ['user', 'redis://user:@localhost/queue'];
        yield 'Colon and password' => ['password', 'redis://:password@localhost/queue'];
        yield 'Colon and falsy password' => ['0', 'redis://:0@localhost/queue'];
    }

    public function testAuthFromOptions(): void
    {
        $redis = $this->createRedisMock();

        $redis->expects($this->exactly(1))->method('auth')
            ->with('password')
            ->willReturn(true);

        Connection::fromDsn('redis://localhost/queue', ['auth' => 'password'], $redis);
    }

    public function testAuthFromOptionsAndDsn(): void
    {
        $redis = $this->createRedisMock();

        $redis->expects($this->exactly(1))->method('auth')
            ->with('password2')
            ->willReturn(true);

        Connection::fromDsn('redis://password1@localhost/queue', ['auth' => 'password2'], $redis);
    }

    public function testNoAuthWithEmptyPassword(): void
    {
        $redis = $this->createRedisMock();

        $redis->expects($this->exactly(0))->method('auth')
            ->with('')
            ->willThrowException(new \RuntimeException());

        Connection::fromDsn('redis://@localhost/queue', [], $redis);
    }

    public function testAuthZeroPassword(): void
    {
        $redis = $this->createRedisMock();

        $redis->expects($this->exactly(1))->method('auth')
            ->with('0')
            ->willReturn(true);

        Connection::fromDsn('redis://0@localhost/queue', [], $redis);
    }

    public function testFailedAuth(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Redis connection ');
        $redis = $this->createRedisMock();

        $redis->expects($this->exactly(1))->method('auth')
            ->with('password')
            ->willReturn(false);

        Connection::fromDsn('redis://password@localhost/queue', [], $redis);
    }

    public function testGetPendingMessageFirst(): void
    {
        $redis = $this->createRedisMock();

        $redis->expects($this->exactly(1))->method('xreadgroup')
            ->with('symfony', 'consumer', ['queue' => '0'], 1, 1)
            ->willReturn(['queue' => [['message' => '{"body":"1","headers":[]}']]]);

        $connection = Connection::fromDsn('redis://localhost/queue', [], $redis);
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

    public function testClaimAbandonedMessageWithRaceCondition(): void
    {
        $redis = $this->createRedisMock();

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

        $connection = Connection::fromDsn('redis://localhost/queue', [], $redis);
        $connection->get();
    }

    public function testClaimAbandonedMessage(): void
    {
        $redis = $this->createRedisMock();

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

        $connection = Connection::fromDsn('redis://localhost/queue', [], $redis);
        $connection->get();
    }

    public function testUnexpectedRedisError(): void
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Redis error happens');
        $redis = $this->createRedisMock();
        $redis->expects($this->once())->method('xreadgroup')->willReturn(false);
        $redis->expects($this->once())->method('getLastError')->willReturn('Redis error happens');

        $connection = Connection::fromDsn('redis://localhost/queue', ['auto_setup' => false], $redis);
        $connection->get();
    }

    public function testMaxEntries(): void
    {
        $redis = $this->createRedisMock();

        $redis->expects($this->exactly(1))->method('xadd')
            ->with('queue', '*', ['message' => '{"body":"1","headers":[]}'], 20000, true)
            ->willReturn('1');

        $connection = Connection::fromDsn('redis://localhost/queue?stream_max_entries=20000', [], $redis);
        $connection->add('1', []);
    }

    public function testDeleteAfterAck(): void
    {
        $redis = $this->createRedisMock();

        $redis->expects($this->exactly(1))->method('xack')
            ->with('queue', 'symfony', ['1'])
            ->willReturn(1);
        $redis->expects($this->exactly(1))->method('xdel')
            ->with('queue', ['1'])
            ->willReturn(1);

        $connection = Connection::fromDsn('redis://localhost/queue', [], $redis);
        $connection->ack('1');
    }

    public function testDeleteAfterReject(): void
    {
        $redis = $this->createRedisMock();

        $redis->expects($this->exactly(1))->method('xack')
            ->with('queue', 'symfony', ['1'])
            ->willReturn(1);
        $redis->expects($this->exactly(1))->method('xdel')
            ->with('queue', ['1'])
            ->willReturn(1);

        $connection = Connection::fromDsn('redis://localhost/queue?delete_after_reject=true', [], $redis);
        $connection->reject('1');
    }

    public function testLastErrorGetsCleared(): void
    {
        $redis = $this->createRedisMock();

        $redis->expects($this->once())->method('xadd')->willReturn('0');
        $redis->expects($this->once())->method('xack')->willReturn(0);

        $redis->method('getLastError')->willReturn('xadd error', 'xack error');
        $redis->expects($this->exactly(2))->method('clearLastError');

        $connection = Connection::fromDsn('redis://localhost/messenger-clearlasterror', ['auto_setup' => false], $redis);

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

    #[DataProvider('provideIdPatterns')]
    public function testAddReturnId(string $expected, int $delay, string $method, string $return): void
    {
        $redis = $this->createRedisMock();
        $redis->expects($this->atLeastOnce())->method($method)->willReturn($return);

        $id = Connection::fromDsn(dsn: 'redis://localhost/queue', redis: $redis)->add('body', [], $delay);

        $this->assertMatchesRegularExpression($expected, $id);
    }

    public static function provideIdPatterns(): \Generator
    {
        yield 'No delay' => ['/^THE_MESSAGE_ID$/', 0, 'xadd', 'THE_MESSAGE_ID'];

        yield '100ms delay' => ['/^[A-Z\d\/+]+$/i', 100, 'rawCommand', '1'];
    }

    #[Group('integration')]
    public function testInvalidSentinelMasterName(): void
    {
        if (!$hosts = getenv('REDIS_SENTINEL_HOSTS')) {
            $this->markTestSkipped('REDIS_SENTINEL_HOSTS env var is not defined.');
        }

        if (!getenv('MESSENGER_REDIS_SENTINEL_MASTER')) {
            self::markTestSkipped('Redis sentinel is not configured');
        }

        $dsn = 'redis:?host['.str_replace(' ', ']&host[', $hosts).']';

        try {
            Connection::fromDsn($dsn, ['delete_after_ack' => true, 'sentinel' => getenv('MESSENGER_REDIS_SENTINEL_MASTER')]);
        } catch (\Exception $e) {
            self::markTestSkipped($e->getMessage());
        }

        $uid = random_int(1, \PHP_INT_MAX);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Failed to retrieve master information from sentinel "%s".', $uid));

        Connection::fromDsn(\sprintf('%s/messenger-clearlasterror', $dsn), ['delete_after_ack' => true, 'sentinel' => $uid]);
    }

    public function testFromDsnOnUnixSocketWithUserAndPassword(): void
    {
        $redis = $this->createRedisMock();

        $redis->expects($this->exactly(1))->method('auth')
            ->with(['user', 'password'])
            ->willReturn(true);

        $this->assertEqualsConnection(
            new Connection([
                'stream' => 'queue',
                'delete_after_ack' => true,
                'host' => '/var/run/redis/redis.sock',
                'port' => 0,
                'auth' => ['user', 'password'],
            ], $redis),
            Connection::fromDsn('redis://user:password@/var/run/redis/redis.sock', ['stream' => 'queue', 'delete_after_ack' => true], $redis)
        );
    }

    public function testFromDsnOnUnixSocketWithPassword(): void
    {
        $redis = $this->createRedisMock();

        $redis->expects($this->exactly(1))->method('auth')
            ->with('password')
            ->willReturn(true);

        $this->assertEqualsConnection(
            new Connection([
                'stream' => 'queue',
                'delete_after_ack' => true,
                'host' => '/var/run/redis/redis.sock',
                'port' => 0,
                'auth' => 'password',
            ], $redis),
            Connection::fromDsn('redis://password@/var/run/redis/redis.sock', ['stream' => 'queue', 'delete_after_ack' => true], $redis)
        );
    }

    public function testFromDsnOnUnixSocketWithUser(): void
    {
        $redis = $this->createRedisMock();

        $redis->expects($this->exactly(1))->method('auth')
            ->with('user')
            ->willReturn(true);

        $this->assertEqualsConnection(
            new Connection([
                'stream' => 'queue',
                'delete_after_ack' => true,
                'host' => '/var/run/redis/redis.sock',
                'port' => 0,
                'auth' => 'user',
            ], $redis),
            Connection::fromDsn('redis://user:@/var/run/redis/redis.sock', ['stream' => 'queue', 'delete_after_ack' => true], $redis)
        );
    }

    public function testKeepalive(): void
    {
        $redis = $this->createRedisMock();

        $redis->expects($this->exactly(1))->method('xclaim')
            ->with('queue', 'symfony', 'consumer', 0, [$id = 'redisid-123'], ['JUSTID'])
            ->willReturn([]);

        $connection = Connection::fromDsn('redis://localhost/queue', [], $redis);
        $connection->keepalive($id);
    }

    public function testKeepaliveWhenARedisExceptionOccurs(): void
    {
        $redis = $this->createRedisMock();

        $redis->expects($this->exactly(1))->method('xclaim')
            ->with('queue', 'symfony', 'consumer', 0, [$id = 'redisid-123'], ['JUSTID'])
            ->willThrowException($exception = new \RedisException('Something went wrong '.time()));

        $connection = Connection::fromDsn('redis://localhost/queue', [], $redis);

        $this->expectExceptionObject(new TransportException($exception->getMessage(), 0, $exception));
        $connection->keepalive($id);
    }

    public function testKeepaliveWithTooSmallTtl(): void
    {
        $redis = $this->createRedisMock();

        $redis->expects($this->never())->method('xclaim');

        $connection = Connection::fromDsn('redis://localhost/queue?redeliver_timeout=1', [], $redis);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Redis redeliver_timeout (1000s) cannot be smaller than the keepalive interval (3000s).');
        $connection->keepalive('redisid-123', 3000);
    }

    private function createRedisMock(): MockObject&\Redis
    {
        $redis = $this->createMock(\Redis::class);
        $redis
            ->expects($this->atLeastOnce())
            ->method('connect')
            ->willReturn(true);
        $redis
            ->method('isConnected')
            ->willReturnOnConsecutiveCalls(false, true, true);

        return $redis;
    }

    private function assertEqualsConnection(Connection $expected, $actual): void
    {
        $this->assertInstanceOf(Connection::class, $actual);

        foreach ((new \ReflectionClass(Connection::class))->getProperties() as $property) {
            if ('redisInitializer' === $property->getName()) {
                continue;
            }

            $this->assertEquals($property->getValue($expected), $property->getValue($actual));
        }
    }
}
