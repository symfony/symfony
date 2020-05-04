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
            $this->markTestSkipped('The "MESSENGER_REDIS_DSN" environment variable is required.');
        }

        try {
            $this->redis = new \Redis();
            $this->connection = Connection::fromDsn(getenv('MESSENGER_REDIS_DSN'), [], $this->redis);
            $this->connection->cleanup();
            $this->connection->setup();
        } catch (\Exception $e) {
            self::markTestSkipped($e->getMessage());
        }
    }

    public function testConnectionSendAndGet()
    {
        $this->connection->add('{"message": "Hi"}', ['type' => DummyMessage::class]);
        $encoded = $this->connection->get();
        $this->assertEquals('{"message": "Hi"}', $encoded['body']);
        $this->assertEquals(['type' => DummyMessage::class], $encoded['headers']);
    }

    public function testGetTheFirstAvailableMessage()
    {
        $this->connection->add('{"message": "Hi1"}', ['type' => DummyMessage::class]);
        $this->connection->add('{"message": "Hi2"}', ['type' => DummyMessage::class]);
        $encoded = $this->connection->get();
        $this->assertEquals('{"message": "Hi1"}', $encoded['body']);
        $this->assertEquals(['type' => DummyMessage::class], $encoded['headers']);
        $encoded = $this->connection->get();
        $this->assertEquals('{"message": "Hi2"}', $encoded['body']);
        $this->assertEquals(['type' => DummyMessage::class], $encoded['headers']);
    }

    public function testConnectionSendWithSameContent()
    {
        $body = '{"message": "Hi"}';
        $headers = ['type' => DummyMessage::class];

        $this->connection->add($body, $headers);
        $this->connection->add($body, $headers);

        $encoded = $this->connection->get();
        $this->assertEquals($body, $encoded['body']);
        $this->assertEquals($headers, $encoded['headers']);

        $encoded = $this->connection->get();
        $this->assertEquals($body, $encoded['body']);
        $this->assertEquals($headers, $encoded['headers']);
    }

    public function testConnectionSendAndGetDelayed()
    {
        $this->connection->add('{"message": "Hi"}', ['type' => DummyMessage::class], 500);
        $encoded = $this->connection->get();
        $this->assertNull($encoded);
        sleep(2);
        $encoded = $this->connection->get();
        $this->assertEquals('{"message": "Hi"}', $encoded['body']);
        $this->assertEquals(['type' => DummyMessage::class], $encoded['headers']);
    }

    public function testConnectionSendDelayedMessagesWithSameContent()
    {
        $body = '{"message": "Hi"}';
        $headers = ['type' => DummyMessage::class];

        $this->connection->add($body, $headers, 500);
        $this->connection->add($body, $headers, 500);
        sleep(2);
        $encoded = $this->connection->get();
        $this->assertEquals($body, $encoded['body']);
        $this->assertEquals($headers, $encoded['headers']);

        $encoded = $this->connection->get();
        $this->assertEquals($body, $encoded['body']);
        $this->assertEquals($headers, $encoded['headers']);
    }

    public function testConnectionBelowRedeliverTimeout()
    {
        // lower redeliver timeout and claim interval
        $connection = Connection::fromDsn(getenv('MESSENGER_REDIS_DSN'), [], $this->redis);

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
            ['redeliver_timeout' => 0, 'claim_interval' => 500],
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
        $encoded = $connection->get();
        $this->assertEquals($body1, $encoded['body']);
        $this->assertEquals($headers, $encoded['headers']);
        $connection->ack($encoded['id']);

        // Queue will return the second message
        $encoded = $connection->get();
        $this->assertEquals($body2, $encoded['body']);
        $this->assertEquals($headers, $encoded['headers']);
        $connection->ack($encoded['id']);
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
}
