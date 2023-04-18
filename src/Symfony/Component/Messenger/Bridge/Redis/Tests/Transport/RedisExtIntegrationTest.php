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
use Relay\Relay;
use Symfony\Component\Messenger\Bridge\Redis\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\Redis\Transport\Connection;
use Symfony\Component\Messenger\Bridge\Redis\Transport\RedisReceiver;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;

/**
 * @requires extension redis
 *
 * @group time-sensitive
 * @group integration
 */
class RedisExtIntegrationTest extends TestCase
{
    private $redis;
    private $connection;

    protected function setUp(): void
    {
        if (!getenv('MESSENGER_REDIS_DSN')) {
            $this->markTestSkipped('The "MESSENGER_REDIS_DSN" environment variable is required.');
        }

        try {
            $this->redis = $this->createRedisClient();
            $this->connection = Connection::fromDsn(getenv('MESSENGER_REDIS_DSN'), ['sentinel_master' => getenv('MESSENGER_REDIS_SENTINEL_MASTER') ?: null], $this->redis);
            $this->connection->cleanup();
            $this->connection->setup();
        } catch (\Exception $e) {
            self::markTestSkipped($e->getMessage());
        }
    }

    public function testConnectionSendAndGet()
    {
        $this->connection->add('{"message": "Hi"}', ['type' => DummyMessage::class]);
        $message = $this->connection->get();
        $this->assertEquals([
            'message' => json_encode([
                'body' => '{"message": "Hi"}',
                'headers' => ['type' => DummyMessage::class],
            ]),
        ], $message['data']);
    }

    public function testGetTheFirstAvailableMessage()
    {
        $this->connection->add('{"message": "Hi1"}', ['type' => DummyMessage::class]);
        $this->connection->add('{"message": "Hi2"}', ['type' => DummyMessage::class]);
        $message = $this->connection->get();
        $this->assertEquals([
            'message' => json_encode([
                'body' => '{"message": "Hi1"}',
                'headers' => ['type' => DummyMessage::class],
            ]),
        ], $message['data']);
        $message = $this->connection->get();
        $this->assertEquals([
            'message' => json_encode([
                'body' => '{"message": "Hi2"}',
                'headers' => ['type' => DummyMessage::class],
            ]),
        ], $message['data']);
    }

    public function testConnectionSendWithSameContent()
    {
        $body = '{"message": "Hi"}';
        $headers = ['type' => DummyMessage::class];

        $this->connection->add($body, $headers);
        $this->connection->add($body, $headers);

        $message = $this->connection->get();
        $this->assertEquals([
            'message' => json_encode([
                'body' => $body,
                'headers' => $headers,
            ]),
        ], $message['data']);

        $message = $this->connection->get();
        $this->assertEquals([
            'message' => json_encode([
                'body' => $body,
                'headers' => $headers,
            ]),
        ], $message['data']);
    }

    public function testConnectionSendAndGetDelayed()
    {
        $this->connection->add('{"message": "Hi"}', ['type' => DummyMessage::class], 500);
        $message = $this->connection->get();
        $this->assertNull($message);
        sleep(2);
        $message = $this->connection->get();
        $this->assertEquals([
            'message' => json_encode([
                'body' => '{"message": "Hi"}',
                'headers' => ['type' => DummyMessage::class],
            ]),
        ], $message['data']);
    }

    public function testConnectionSendDelayedMessagesWithSameContent()
    {
        $body = '{"message": "Hi"}';
        $headers = ['type' => DummyMessage::class];

        $this->connection->add($body, $headers, 500);
        $this->connection->add($body, $headers, 500);
        sleep(2);
        $message = $this->connection->get();
        $this->assertEquals([
            'message' => json_encode([
                'body' => $body,
                'headers' => $headers,
            ]),
        ], $message['data']);

        $message = $this->connection->get();
        $this->assertEquals([
            'message' => json_encode([
                'body' => $body,
                'headers' => $headers,
            ]),
        ], $message['data']);
    }

    public function testConnectionBelowRedeliverTimeout()
    {
        // lower redeliver timeout and claim interval
        $connection = Connection::fromDsn(getenv('MESSENGER_REDIS_DSN'), ['sentinel_master' => getenv('MESSENGER_REDIS_SENTINEL_MASTER') ?: null], $this->redis);

        $connection->cleanup();
        $connection->setup();

        $body = '{"message": "Hi"}';
        $headers = ['type' => DummyMessage::class];

        // Add two messages
        $connection->add($body, $headers);

        // Read first message with other consumer
        $this->redis->xreadgroup(
            $this->getConnectionGroup($connection),
            'other-consumer2',
            [$this->getConnectionStream($connection) => '>'],
            1
        );

        // Queue will not have any messages yet
        $this->assertNull($connection->get());
    }

    public function testConnectionClaimAndRedeliver()
    {
        // lower redeliver timeout and claim interval
        $connection = Connection::fromDsn(
            getenv('MESSENGER_REDIS_DSN'),
            ['redeliver_timeout' => 0, 'claim_interval' => 500, 'sentinel_master' => getenv('MESSENGER_REDIS_SENTINEL_MASTER') ?: null],

            $this->redis
        );

        $connection->cleanup();
        $connection->setup();

        $body1 = '{"message": "Hi"}';
        $body2 = '{"message": "Bye"}';
        $headers = ['type' => DummyMessage::class];

        // Add two messages
        $connection->add($body1, $headers);
        $connection->add($body2, $headers);

        // Read first message with other consumer
        $this->redis->xreadgroup(
            $this->getConnectionGroup($connection),
            'other-consumer2',
            [$this->getConnectionStream($connection) => '>'],
            1
        );

        // Queue will return the pending message first because redeliver_timeout = 0
        $message = $connection->get();
        $this->assertEquals([
            'message' => json_encode([
                'body' => $body1,
                'headers' => $headers,
            ]),
        ], $message['data']);
        $connection->ack($message['id']);

        // Queue will return the second message
        $message = $connection->get();
        $this->assertEquals([
            'message' => json_encode([
                'body' => $body2,
                'headers' => $headers,
            ]),
        ], $message['data']);
        $connection->ack($message['id']);
    }

    public function testLazySentinel()
    {
        $connection = Connection::fromDsn(getenv('MESSENGER_REDIS_DSN'),
            ['lazy' => true,
             'delete_after_ack' => true,
             'sentinel_master' => getenv('MESSENGER_REDIS_SENTINEL_MASTER') ?: null,
            ], $this->redis);

        $connection->add('1', []);
        $this->assertNotEmpty($message = $connection->get());
        $this->assertSame([
            'message' => json_encode([
                'body' => '1',
                'headers' => [],
            ]),
        ], $message['data']);
        $connection->reject($message['id']);
        $connection->cleanup();
    }

    public function testLazyCluster()
    {
        $this->skipIfRedisClusterUnavailable();

        $connection = new Connection(['lazy' => true, 'host' => explode(' ', getenv('REDIS_CLUSTER_HOSTS'))]);

        $connection->add('1', []);
        $this->assertNotEmpty($message = $connection->get());
        $this->assertSame([
            'message' => json_encode([
                'body' => '1',
                'headers' => [],
            ]),
        ], $message['data']);
        $connection->reject($message['id']);
        $connection->cleanup();
    }

    public function testLazy()
    {
        $redis = $this->createRedisClient();
        $connection = Connection::fromDsn('redis://localhost/messenger-lazy?lazy=1', [], $redis);

        $connection->add('1', []);
        $this->assertNotEmpty($message = $connection->get());
        $this->assertSame([
            'message' => json_encode([
                'body' => '1',
                'headers' => [],
            ]),
        ], $message['data']);
        $connection->reject($message['id']);
        $redis->del('messenger-lazy');
    }

    public function testDbIndex()
    {
        $redis = $this->createRedisClient();

        Connection::fromDsn('redis://localhost/queue?dbindex=2', [], $redis);

        $this->assertSame(2, $redis->getDbNum());
    }

    public function testFromDsnWithMultipleHosts()
    {
        $this->skipIfRedisClusterUnavailable();

        $hosts = explode(' ', getenv('REDIS_CLUSTER_HOSTS'));

        $dsn = array_map(fn ($host) => 'redis://'.$host, $hosts);
        $dsn = implode(',', $dsn);

        $this->assertInstanceOf(Connection::class, Connection::fromDsn($dsn, ['sentinel_master' => getenv('MESSENGER_REDIS_SENTINEL_MASTER') ?: null]));
    }

    public function testJsonError()
    {
        $redis = $this->createRedisClient();
        $connection = Connection::fromDsn('redis://localhost/json-error', [], $redis);
        try {
            $connection->add("\xB1\x31", []);
        } catch (TransportException $e) {
        }

        $this->assertSame('Malformed UTF-8 characters, possibly incorrectly encoded', $e->getMessage());
    }

    public function testGetNonBlocking()
    {
        $redis = $this->createRedisClient();

        $connection = Connection::fromDsn('redis://localhost/messenger-getnonblocking', ['sentinel_master' => null], $redis);

        $this->assertNull($connection->get()); // no message, should return null immediately
        $connection->add('1', []);
        $this->assertNotEmpty($message = $connection->get());
        $connection->reject($message['id']);
        $redis->del('messenger-getnonblocking');
    }

    public function testGetAfterReject()
    {
        $redis = $this->createRedisClient();
        $connection = Connection::fromDsn('redis://localhost/messenger-rejectthenget', ['sentinel_master' => null], $redis);

        $connection->add('1', []);
        $connection->add('2', []);

        $failing = $connection->get();
        $connection->reject($failing['id']);

        $connection = Connection::fromDsn('redis://localhost/messenger-rejectthenget', ['sentinel_master' => null]);

        $this->assertNotNull($connection->get());

        $redis->del('messenger-rejectthenget');
    }

    public function testItProperlyHandlesEmptyMessages()
    {
        $redisReceiver = new RedisReceiver($this->connection, new Serializer());

        $this->connection->add('{"message": "Hi1"}', ['type' => DummyMessage::class]);
        $this->connection->add('{"message": "Hi2"}', ['type' => DummyMessage::class]);

        $redisReceiver->get();
        $this->redis->xtrim('messages', 1);

        // The consumer died during handling a message while performing xtrim in parallel process
        $this->redis = new \Redis();
        $this->connection = Connection::fromDsn(getenv('MESSENGER_REDIS_DSN'), ['delete_after_ack' => true], $this->redis);
        $redisReceiver = new RedisReceiver($this->connection, new Serializer());

        /** @var Envelope[] $envelope */
        $envelope = $redisReceiver->get();
        $this->assertCount(1, $envelope);

        $message = $envelope[0]->getMessage();
        $this->assertInstanceOf(DummyMessage::class, $message);
        $this->assertEquals('Hi2', $message->getMessage());
    }

    public function testItCountMessages()
    {
        $this->assertSame(0, $this->connection->getMessageCount());

        $this->connection->add('{"message": "Hi"}', ['type' => DummyMessage::class]);
        $this->connection->add('{"message": "Hi"}', ['type' => DummyMessage::class]);
        $this->connection->add('{"message": "Hi"}', ['type' => DummyMessage::class]);

        $this->assertSame(3, $this->connection->getMessageCount());

        $message = $this->connection->get();
        $this->connection->ack($message['id']);

        $this->assertSame(2, $this->connection->getMessageCount());

        $message = $this->connection->get();
        $this->connection->reject($message['id']);

        $this->assertSame(1, $this->connection->getMessageCount());
    }

    private function getConnectionGroup(Connection $connection): string
    {
        $property = (new \ReflectionClass(Connection::class))->getProperty('group');

        return $property->getValue($connection);
    }

    private function getConnectionStream(Connection $connection): string
    {
        $property = (new \ReflectionClass(Connection::class))->getProperty('stream');

        return $property->getValue($connection);
    }

    private function skipIfRedisClusterUnavailable()
    {
        try {
            new \RedisCluster(null, explode(' ', getenv('REDIS_CLUSTER_HOSTS')));
        } catch (\Exception $e) {
            self::markTestSkipped($e->getMessage());
        }
    }

    protected function createRedisClient(): \Redis|Relay
    {
        return new \Redis();
    }
}
