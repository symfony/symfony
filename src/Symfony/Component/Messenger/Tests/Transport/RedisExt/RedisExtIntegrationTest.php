<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\RedisExt;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\RedisExt\Connection;

/**
 * @requires extension redis
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

        $this->redis = new \Redis();
        $this->connection = Connection::fromDsn(getenv('MESSENGER_REDIS_DSN'), [], $this->redis);
        $this->clearRedis();
        $this->connection->setup();
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

    private function clearRedis()
    {
        $parsedUrl = parse_url(getenv('MESSENGER_REDIS_DSN'));
        $pathParts = explode('/', $parsedUrl['path'] ?? '');
        $stream = $pathParts[1] ?? 'symfony';
        $this->redis->del($stream);
    }
}
