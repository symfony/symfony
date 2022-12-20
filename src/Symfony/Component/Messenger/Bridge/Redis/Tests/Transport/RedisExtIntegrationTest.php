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
use Symfony\Component\Messenger\Bridge\Redis\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\Redis\Transport\Connection;
use Symfony\Component\Messenger\Exception\TransportException;

/**
 * @requires extension redis
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
            self::markTestSkipped('The "MESSENGER_REDIS_DSN" environment variable is required.');
        }

        try {
            $this->redis = new \Redis();
            $this->connection = Connection::fromDsn(getenv('MESSENGER_REDIS_DSN'), ['delete_after_ack' => true], $this->redis);
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
        self::assertEquals([
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
        self::assertEquals([
            'message' => json_encode([
                'body' => '{"message": "Hi1"}',
                'headers' => ['type' => DummyMessage::class],
            ]),
        ], $message['data']);
        $message = $this->connection->get();
        self::assertEquals([
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
        self::assertEquals([
            'message' => json_encode([
                'body' => $body,
                'headers' => $headers,
            ]),
        ], $message['data']);

        $message = $this->connection->get();
        self::assertEquals([
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
        self::assertNull($message);
        sleep(2);
        $message = $this->connection->get();
        self::assertEquals([
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
        self::assertEquals([
            'message' => json_encode([
                'body' => $body,
                'headers' => $headers,
            ]),
        ], $message['data']);

        $message = $this->connection->get();
        self::assertEquals([
            'message' => json_encode([
                'body' => $body,
                'headers' => $headers,
            ]),
        ], $message['data']);
    }

    public function testConnectionBelowRedeliverTimeout()
    {
        // lower redeliver timeout and claim interval
        $connection = Connection::fromDsn(getenv('MESSENGER_REDIS_DSN'), ['delete_after_ack' => true], $this->redis);

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
        self::assertNull($connection->get());
    }

    public function testConnectionClaimAndRedeliver()
    {
        // lower redeliver timeout and claim interval
        $connection = Connection::fromDsn(
            getenv('MESSENGER_REDIS_DSN'),
            ['redeliver_timeout' => 0, 'claim_interval' => 500, 'delete_after_ack' => true],
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
        self::assertEquals([
            'message' => json_encode([
                'body' => $body1,
                'headers' => $headers,
            ]),
        ], $message['data']);
        $connection->ack($message['id']);

        // Queue will return the second message
        $message = $connection->get();
        self::assertEquals([
            'message' => json_encode([
                'body' => $body2,
                'headers' => $headers,
            ]),
        ], $message['data']);
        $connection->ack($message['id']);
    }

    public function testLazyCluster()
    {
        $this->skipIfRedisClusterUnavailable();

        $connection = new Connection(
            ['lazy' => true],
            ['host' => explode(' ', getenv('REDIS_CLUSTER_HOSTS'))],
            ['delete_after_ack' => true]
        );

        $connection->add('1', []);
        self::assertNotEmpty($message = $connection->get());
        self::assertSame([
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
        $redis = new \Redis();
        $connection = Connection::fromDsn('redis://localhost/messenger-lazy?lazy=1', ['delete_after_ack' => true], $redis);

        $connection->add('1', []);
        self::assertNotEmpty($message = $connection->get());
        self::assertSame([
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
        $redis = new \Redis();

        Connection::fromDsn('redis://localhost/queue?dbindex=2', ['delete_after_ack' => true], $redis);

        self::assertSame(2, $redis->getDbNum());
    }

    public function testFromDsnWithMultipleHosts()
    {
        $this->skipIfRedisClusterUnavailable();

        $hosts = explode(' ', getenv('REDIS_CLUSTER_HOSTS'));

        $dsn = array_map(function ($host) {
            return 'redis://'.$host;
        }, $hosts);
        $dsn = implode(',', $dsn);

        self::assertInstanceOf(Connection::class, Connection::fromDsn($dsn, ['delete_after_ack' => true]));
    }

    public function testJsonError()
    {
        $redis = new \Redis();
        $connection = Connection::fromDsn('redis://localhost/json-error', ['delete_after_ack' => true], $redis);
        try {
            $connection->add("\xB1\x31", []);
        } catch (TransportException $e) {
        }

        self::assertSame('Malformed UTF-8 characters, possibly incorrectly encoded', $e->getMessage());
    }

    public function testGetNonBlocking()
    {
        $redis = new \Redis();

        $connection = Connection::fromDsn('redis://localhost/messenger-getnonblocking', ['delete_after_ack' => true], $redis);

        self::assertNull($connection->get()); // no message, should return null immediately
        $connection->add('1', []);
        self::assertNotEmpty($message = $connection->get());
        $connection->reject($message['id']);
        $redis->del('messenger-getnonblocking');
    }

    public function testGetAfterReject()
    {
        $redis = new \Redis();
        $connection = Connection::fromDsn('redis://localhost/messenger-rejectthenget', ['delete_after_ack' => true], $redis);

        $connection->add('1', []);
        $connection->add('2', []);

        $failing = $connection->get();
        $connection->reject($failing['id']);

        $connection = Connection::fromDsn('redis://localhost/messenger-rejectthenget', ['delete_after_ack' => true]);
        self::assertNotNull($connection->get());

        $redis->del('messenger-rejectthenget');
    }

    private function getConnectionGroup(Connection $connection): string
    {
        $property = (new \ReflectionClass(Connection::class))->getProperty('group');
        $property->setAccessible(true);

        return $property->getValue($connection);
    }

    private function getConnectionStream(Connection $connection): string
    {
        $property = (new \ReflectionClass(Connection::class))->getProperty('stream');
        $property->setAccessible(true);

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
}
