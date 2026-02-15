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
    public function testFromInvalidDsn()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The given Redis DSN is invalid.');

        Connection::fromDsn('redis://');
    }

    public function testFromDsn()
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

    public function testFromDsnOnUnixSocket()
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

    public function testFromDsnWithOptions()
    {
        $this->assertEqualsConnection(
            Connection::fromDsn('redis://localhost', ['stream' => 'queue', 'group' => 'group1', 'consumer' => 'consumer1', 'auto_setup' => false, 'serializer' => 2], $this->createRedisMock()),
            Connection::fromDsn('redis://localhost/queue/group1/consumer1?serializer=2&auto_setup=0', [], $this->createRedisMock())
        );
    }

    public function testFromDsnWithOptionsAndTrailingSlash()
    {
        $this->assertEqualsConnection(
            Connection::fromDsn('redis://localhost/', ['stream' => 'queue', 'group' => 'group1', 'consumer' => 'consumer1', 'auto_setup' => false, 'serializer' => 2], $this->createRedisMock()),
            Connection::fromDsn('redis://localhost/queue/group1/consumer1?serializer=2&auto_setup=0', [], $this->createRedisMock())
        );
    }

    public function testFromDsnWithRedissScheme()
    {
        $redis = $this->createRedisMock();
        $redis->expects($this->once())
            ->method('connect')
            ->with('tls://127.0.0.1', 6379)
            ->willReturn(true);
        $redis
            ->method('isConnected')
            ->willReturnOnConsecutiveCalls(false, true);

        Connection::fromDsn('rediss://127.0.0.1', [], $redis);
    }

    public function testFromDsnWithQueryOptions()
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

    public function testFromDsnWithMixDsnQueryOptions()
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

    public function testFromDsnWithClusterAlias()
    {
        $this->assertInstanceOf(Connection::class, Connection::fromDsn('redis://localhost/queue?cluster=0', [], $this->createRedisMock()));
    }

    public function testFromDsnWithRedisSentinelAlias()
    {
        $connection = Connection::fromDsn('redis://localhost/queue?lazy=1&redis_sentinel=mymaster', [], $this->createStub(\Redis::class));

        $initializerProperty = new \ReflectionProperty(Connection::class, 'redisInitializer');
        $initializer = $initializerProperty->getValue($connection);

        $staticVariables = (new \ReflectionFunction($initializer))->getStaticVariables();

        $this->assertSame('mymaster', $staticVariables['sentinelMaster']);
    }

    public function testFromDsnWithSentinelMasterAlias()
    {
        $connection = Connection::fromDsn('redis://localhost/queue?lazy=1&sentinel_master=mymaster', [], $this->createStub(\Redis::class));

        $initializerProperty = new \ReflectionProperty(Connection::class, 'redisInitializer');
        $initializer = $initializerProperty->getValue($connection);

        $staticVariables = (new \ReflectionFunction($initializer))->getStaticVariables();

        $this->assertSame('mymaster', $staticVariables['sentinelMaster']);
    }

    public function testRedisClusterInstanceIsSupported()
    {
        $redis = $this->createRedisMock();
        $this->assertInstanceOf(Connection::class, new Connection([], $redis));
    }

    public function testKeepGettingPendingMessages()
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
    public function testAuth(string|array $expected, string $dsn)
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

    public function testAuthFromOptions()
    {
        $redis = $this->createRedisMock();

        $redis->expects($this->exactly(1))->method('auth')
            ->with('password')
            ->willReturn(true);

        Connection::fromDsn('redis://localhost/queue', ['auth' => 'password'], $redis);
    }

    public function testAuthFromOptionsAndDsn()
    {
        $redis = $this->createRedisMock();

        $redis->expects($this->exactly(1))->method('auth')
            ->with('password2')
            ->willReturn(true);

        Connection::fromDsn('redis://password1@localhost/queue', ['auth' => 'password2'], $redis);
    }

    public function testSentinelUsesAuthOptionWhileMasterPrefersUserInfoAuth()
    {
        $connection = Connection::fromDsn('redis://master-password@localhost/queue?auth=sentinel-password&sentinel=mymaster&lazy=1', [], $this->createStub(\Redis::class));

        $staticVariables = $this->getInitializerStaticVariables($connection);

        $this->assertSame('master-password', $staticVariables['auth']);
        $this->assertSame('sentinel-password', $staticVariables['sentinelAuth']);
    }

    #[DataProvider('provideAuthResolutionMatrix')]
    public function testFromDsnAuthResolutionMatrix(string $dsn, array $options, string|array|null $expectedMasterAuth, string|array|null $expectedSentinelAuth)
    {
        $connection = Connection::fromDsn($dsn, ['lazy' => true] + $options, $this->createStub(\Redis::class));

        $staticVariables = $this->getInitializerStaticVariables($connection);

        $this->assertSame($expectedMasterAuth, $staticVariables['auth']);
        $this->assertSame($expectedSentinelAuth, $staticVariables['sentinelAuth']);
    }

    public static function provideAuthResolutionMatrix(): \Generator
    {
        yield 'userinfo user+pass' => [
            'redis://user:pass@localhost/queue',
            [],
            ['user', 'pass'],
            null,
        ];

        yield 'userinfo with @ + query auth array' => [
            'redis://user@pass@localhost/queue?auth[]=otheruser&auth[]=otherpass',
            [],
            ['otheruser', 'otherpass'],
            null,
        ];

        yield 'query auth array' => [
            'redis://localhost/queue?auth[]=user&auth[]=pass',
            [],
            ['user', 'pass'],
            null,
        ];

        yield 'options auth array' => [
            'redis://localhost/queue',
            ['auth' => ['user', 'pass']],
            ['user', 'pass'],
            null,
        ];

        yield 'query auth beats options auth' => [
            'redis://localhost/queue?auth[]=query-user&auth[]=query-pass',
            ['auth' => ['opt-user', 'opt-pass']],
            ['query-user', 'query-pass'],
            null,
        ];

        yield 'sentinel query auth, master userinfo' => [
            'redis://master-user:master-pass@localhost/queue?sentinel=mymaster&auth[]=sentinel-user&auth[]=sentinel-pass',
            [],
            ['master-user', 'master-pass'],
            ['sentinel-user', 'sentinel-pass'],
        ];

        yield 'sentinel options auth when query missing' => [
            'redis://master-pass@localhost/queue?sentinel=mymaster',
            ['auth' => ['sentinel-user', 'sentinel-pass']],
            'master-pass',
            ['sentinel-user', 'sentinel-pass'],
        ];

        yield 'sentinel query auth beats options auth' => [
            'redis://master-pass@localhost/queue?sentinel=mymaster&auth[]=query-user&auth[]=query-pass',
            ['auth' => ['opt-user', 'opt-pass']],
            'master-pass',
            ['query-user', 'query-pass'],
        ];
    }

    public function testNoAuthWithEmptyPassword()
    {
        $redis = $this->createRedisMock();

        $redis->expects($this->exactly(0))->method('auth')
            ->with('')
            ->willThrowException(new \RuntimeException());

        Connection::fromDsn('redis://@localhost/queue', [], $redis);
    }

    public function testAuthZeroPassword()
    {
        $redis = $this->createRedisMock();

        $redis->expects($this->exactly(1))->method('auth')
            ->with('0')
            ->willReturn(true);

        Connection::fromDsn('redis://0@localhost/queue', [], $redis);
    }

    public function testFailedAuth()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Redis connection ');
        $redis = $this->createRedisMock();

        $redis->expects($this->exactly(1))->method('auth')
            ->with('password')
            ->willReturn(false);

        Connection::fromDsn('redis://password@localhost/queue', [], $redis);
    }

    public function testGetPendingMessageFirst()
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

    public function testClaimAbandonedMessageWithRaceCondition()
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

    public function testClaimAbandonedMessage()
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

    public function testUnexpectedRedisError()
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Redis error happens');
        $redis = $this->createRedisMock();
        $redis->expects($this->once())->method('xreadgroup')->willReturn(false);
        $redis->expects($this->once())->method('getLastError')->willReturn('Redis error happens');

        $connection = Connection::fromDsn('redis://localhost/queue', ['auto_setup' => false], $redis);
        $connection->get();
    }

    public function testMaxEntries()
    {
        $redis = $this->createRedisMock();

        $redis->expects($this->exactly(1))->method('xadd')
            ->with('queue', '*', ['message' => '{"body":"1","headers":[]}'], 20000, true)
            ->willReturn('1');

        $connection = Connection::fromDsn('redis://localhost/queue?stream_max_entries=20000', [], $redis);
        $connection->add('1', []);
    }

    public function testDeleteAfterAck()
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

    public function testDeleteAfterReject()
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

    public function testLastErrorGetsCleared()
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
    public function testAddReturnId(string $expected, int $delay, string $method, string $return)
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
    public function testInvalidSentinelMasterName()
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

    public function testFromDsnOnUnixSocketWithUserAndPassword()
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

    public function testFromDsnOnUnixSocketWithPassword()
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

    public function testFromDsnOnUnixSocketWithUser()
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

    public function testKeepalive()
    {
        $redis = $this->createRedisMock();

        $redis->expects($this->exactly(1))->method('xclaim')
            ->with('queue', 'symfony', 'consumer', 0, [$id = 'redisid-123'], ['JUSTID'])
            ->willReturn([]);

        $connection = Connection::fromDsn('redis://localhost/queue', [], $redis);
        $connection->keepalive($id);
    }

    public function testKeepaliveWhenARedisExceptionOccurs()
    {
        $redis = $this->createRedisMock();

        $redis->expects($this->exactly(1))->method('xclaim')
            ->with('queue', 'symfony', 'consumer', 0, [$id = 'redisid-123'], ['JUSTID'])
            ->willThrowException($exception = new \RedisException('Something went wrong '.time()));

        $connection = Connection::fromDsn('redis://localhost/queue', [], $redis);

        $this->expectExceptionObject(new TransportException($exception->getMessage(), 0, $exception));
        $connection->keepalive($id);
    }

    public function testKeepaliveWithTooSmallTtl()
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

    private function assertEqualsConnection(Connection $expected, $actual)
    {
        $this->assertInstanceOf(Connection::class, $actual);

        foreach ((new \ReflectionClass(Connection::class))->getProperties() as $property) {
            if ('redisInitializer' === $property->getName()) {
                continue;
            }

            $this->assertEquals($property->getValue($expected), $property->getValue($actual));
        }
    }

    private function getInitializerStaticVariables(Connection $connection): array
    {
        $initializerProperty = new \ReflectionProperty(Connection::class, 'redisInitializer');
        $initializer = $initializerProperty->getValue($connection);

        return (new \ReflectionFunction($initializer))->getStaticVariables();
    }

    public function testFindAllReturnsAllMessages()
    {
        $redis = $this->createRedisMock();

        $redis->expects($this->exactly(1))->method('xRange')
            ->with('queue', '-', '+')
            ->willReturn([
                '1234567890-0' => ['message' => json_encode(['body' => 'test1', 'headers' => []])],
                '1234567890-1' => ['message' => json_encode(['body' => 'test2', 'headers' => []])],
            ]);

        $connection = Connection::fromDsn('redis://localhost/queue', [], $redis);
        $messages = $connection->findAll();

        $this->assertCount(2, $messages);
        $this->assertEquals('1234567890-0', $messages[0]['id']);
        $this->assertEquals('1234567890-1', $messages[1]['id']);
        $this->assertArrayHasKey('data', $messages[0]);
        $this->assertArrayHasKey('data', $messages[1]);
    }

    public function testFindAllWithLimit()
    {
        $redis = $this->createRedisMock();

        $redis->expects($this->exactly(1))->method('xRange')
            ->with('queue', '-', '+', 1)
            ->willReturn([
                '1234567890-0' => ['message' => json_encode(['body' => 'test1', 'headers' => []])],
            ]);

        $connection = Connection::fromDsn('redis://localhost/queue', [], $redis);
        $messages = $connection->findAll(1);

        $this->assertCount(1, $messages);
        $this->assertEquals('1234567890-0', $messages[0]['id']);
    }

    public function testFindAllWhenRedisExceptionOccurs()
    {
        $redis = $this->createRedisMock();

        $redis->expects($this->exactly(1))->method('xRange')
            ->with('queue', '-', '+')
            ->willThrowException($exception = new \RedisException('Something went wrong'));

        $connection = Connection::fromDsn('redis://localhost/queue', [], $redis);

        $this->expectExceptionObject(new TransportException($exception->getMessage(), 0, $exception));
        $connection->findAll();
    }

    public function testFindReturnsMessageById()
    {
        $redis = $this->createRedisMock();

        $redis->expects($this->exactly(1))->method('xRange')
            ->with('queue', '1234567890-0', '1234567890-0', 1)
            ->willReturn([
                '1234567890-0' => ['message' => json_encode(['body' => 'test1', 'headers' => []])],
            ]);

        $connection = Connection::fromDsn('redis://localhost/queue', [], $redis);
        $message = $connection->find('1234567890-0');

        $this->assertNotNull($message);
        $this->assertEquals('1234567890-0', $message['id']);
        $this->assertArrayHasKey('data', $message);
    }

    public function testFindReturnsNullForNonExistentMessage()
    {
        $redis = $this->createRedisMock();

        $redis->expects($this->exactly(1))->method('xRange')
            ->with('queue', '9999999999-0', '9999999999-0', 1)
            ->willReturn([]);

        $connection = Connection::fromDsn('redis://localhost/queue', [], $redis);
        $message = $connection->find('9999999999-0');

        $this->assertNull($message);
    }

    public function testFindReturnsNullForInvalidId()
    {
        $connection = Connection::fromDsn('redis://localhost/queue', [], $this->createRedisMock());
        $message = $connection->find(123);

        $this->assertNull($message);
    }
}
