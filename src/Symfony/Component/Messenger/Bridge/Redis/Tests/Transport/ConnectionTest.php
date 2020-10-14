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
 * @group integration
 */
class ConnectionTest extends TestCase
{
    use ExpectDeprecationTrait;

    public static function setUpBeforeClass(): void
    {
        try {
            $redis = Connection::fromDsn('redis://localhost/queue');
            $redis->get();
        } catch (TransportException $e) {
            if (0 === strpos($e->getMessage(), 'ERR unknown command \'X')) {
                self::markTestSkipped('Redis server >= 5 is required');
            }

            throw $e;
        } catch (\RedisException $e) {
            self::markTestSkipped($e->getMessage());
        }
    }

    public function testFromInvalidDsn()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The given Redis DSN "redis://" is invalid.');

        Connection::fromDsn('redis://');
    }

    public function testFromDsn()
    {
        $this->assertEquals(
            new Connection(['stream' => 'queue'], [
                'host' => 'localhost',
                'port' => 6379,
            ]),
            Connection::fromDsn('redis://localhost/queue')
        );
    }

    public function testFromDsnOnUnixSocket()
    {
        $this->assertEquals(
            new Connection(['stream' => 'queue'], [
                'host' => '/var/run/redis/redis.sock',
                'port' => 0,
            ], [], $redis = $this->createMock(\Redis::class)),
            Connection::fromDsn('redis:///var/run/redis/redis.sock', ['stream' => 'queue'], $redis)
        );
    }

    public function testFromDsnWithOptions()
    {
        $this->assertEquals(
            Connection::fromDsn('redis://localhost', ['stream' => 'queue', 'group' => 'group1', 'consumer' => 'consumer1', 'auto_setup' => false, 'serializer' => 2]),
            Connection::fromDsn('redis://localhost/queue/group1/consumer1?serializer=2&auto_setup=0')
        );
    }

    public function testFromDsnWithOptionsAndTrailingSlash()
    {
        $this->assertEquals(
            Connection::fromDsn('redis://localhost/', ['stream' => 'queue', 'group' => 'group1', 'consumer' => 'consumer1', 'auto_setup' => false, 'serializer' => 2]),
            Connection::fromDsn('redis://localhost/queue/group1/consumer1?serializer=2&auto_setup=0')
        );
    }

    public function testFromDsnWithTls()
    {
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())
            ->method('connect')
            ->with('tls://127.0.0.1', 6379)
            ->willReturn(null);

        Connection::fromDsn('redis://127.0.0.1?tls=1', [], $redis);
    }

    public function testFromDsnWithTlsOption()
    {
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())
            ->method('connect')
            ->with('tls://127.0.0.1', 6379)
            ->willReturn(null);

        Connection::fromDsn('redis://127.0.0.1', ['tls' => true], $redis);
    }

    public function testFromDsnWithQueryOptions()
    {
        $this->assertEquals(
            new Connection(['stream' => 'queue', 'group' => 'group1', 'consumer' => 'consumer1'], [
                'host' => 'localhost',
                'port' => 6379,
            ], [
                'serializer' => 2,
            ]),
            Connection::fromDsn('redis://localhost/queue/group1/consumer1?serializer=2')
        );
    }

    /**
     * @group legacy
     */
    public function testDeprecationIfInvalidOptionIsPassedWithDsn()
    {
        $this->expectDeprecation('Since symfony/messenger 5.1: Invalid option(s) "foo" passed to the Redis Messenger transport. Passing invalid options is deprecated.');
        Connection::fromDsn('redis://localhost/queue?foo=bar');
    }

    public function testKeepGettingPendingMessages()
    {
        $redis = $this->getMockBuilder(\Redis::class)->disableOriginalConstructor()->getMock();

        $redis->expects($this->exactly(3))->method('xreadgroup')
            ->with('symfony', 'consumer', ['queue' => 0], 1, null)
            ->willReturn(['queue' => [['message' => '{"body":"Test","headers":[]}']]]);

        $connection = Connection::fromDsn('redis://localhost/queue', [], $redis);
        $this->assertNotNull($connection->get());
        $this->assertNotNull($connection->get());
        $this->assertNotNull($connection->get());
    }

    public function testAuth()
    {
        $redis = $this->getMockBuilder(\Redis::class)->disableOriginalConstructor()->getMock();

        $redis->expects($this->exactly(1))->method('auth')
            ->with('password')
            ->willReturn(true);

        Connection::fromDsn('redis://password@localhost/queue', [], $redis);
    }

    public function testNoAuthWithEmptyPassword()
    {
        $redis = $this->getMockBuilder(\Redis::class)->disableOriginalConstructor()->getMock();

        $redis->expects($this->exactly(0))->method('auth')
            ->with('')
            ->willThrowException(new \RuntimeException());

        Connection::fromDsn('redis://@localhost/queue', [], $redis);
    }

    public function testAuthZeroPassword()
    {
        $redis = $this->getMockBuilder(\Redis::class)->disableOriginalConstructor()->getMock();

        $redis->expects($this->exactly(1))->method('auth')
            ->with('0')
            ->willReturn(true);

        Connection::fromDsn('redis://0@localhost/queue', [], $redis);
    }

    public function testFailedAuth()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Redis connection ');
        $redis = $this->getMockBuilder(\Redis::class)->disableOriginalConstructor()->getMock();

        $redis->expects($this->exactly(1))->method('auth')
            ->with('password')
            ->willReturn(false);

        Connection::fromDsn('redis://password@localhost/queue', [], $redis);
    }

    public function testDbIndex()
    {
        $redis = new \Redis();

        Connection::fromDsn('redis://localhost/queue?dbindex=2', [], $redis);

        $this->assertSame(2, $redis->getDbNum());
    }

    public function testGetPendingMessageFirst()
    {
        $redis = $this->getMockBuilder(\Redis::class)->disableOriginalConstructor()->getMock();

        $redis->expects($this->exactly(1))->method('xreadgroup')
            ->with('symfony', 'consumer', ['queue' => '0'], 1, null)
            ->willReturn(['queue' => [['message' => '{"body":"1","headers":[]}']]]);

        $connection = Connection::fromDsn('redis://localhost/queue', [], $redis);
        $connection->get();
    }

    public function testClaimAbandonedMessageWithRaceCondition()
    {
        $redis = $this->getMockBuilder(\Redis::class)->disableOriginalConstructor()->getMock();

        $redis->expects($this->exactly(3))->method('xreadgroup')
            ->withConsecutive(
                ['symfony', 'consumer', ['queue' => '0'], 1, null], // first call for pending messages
                ['symfony', 'consumer', ['queue' => '0'], 1, null], // sencond call because of claimed message (redisid-123)
                ['symfony', 'consumer', ['queue' => '>'], 1, null] // third call because of no result (other consumer claimed message redisid-123)
            )
            ->willReturnOnConsecutiveCalls([], [], []);

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
        $redis = $this->getMockBuilder(\Redis::class)->disableOriginalConstructor()->getMock();

        $redis->expects($this->exactly(2))->method('xreadgroup')
            ->withConsecutive(
                ['symfony', 'consumer', ['queue' => '0'], 1, null], // first call for pending messages
                ['symfony', 'consumer', ['queue' => '0'], 1, null] // sencond call because of claimed message (redisid-123)
            )
            ->willReturnOnConsecutiveCalls(
                [], // first call returns no result
                ['queue' => [['message' => '{"body":"1","headers":[]}']]] // second call returns clamed message (redisid-123)
            );

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
        $redis = $this->getMockBuilder(\Redis::class)->disableOriginalConstructor()->getMock();
        $redis->expects($this->once())->method('xreadgroup')->willReturn(false);
        $redis->expects($this->once())->method('getLastError')->willReturn('Redis error happens');

        $connection = Connection::fromDsn('redis://localhost/queue', ['auto_setup' => false], $redis);
        $connection->get();
    }

    public function testGetAfterReject()
    {
        $redis = new \Redis();
        $connection = Connection::fromDsn('redis://localhost/messenger-rejectthenget', [], $redis);

        $connection->add('1', []);
        $connection->add('2', []);

        $failing = $connection->get();
        $connection->reject($failing['id']);

        $connection = Connection::fromDsn('redis://localhost/messenger-rejectthenget');
        $this->assertNotNull($connection->get());

        $redis->del('messenger-rejectthenget');
    }

    public function testGetNonBlocking()
    {
        $redis = new \Redis();

        $connection = Connection::fromDsn('redis://localhost/messenger-getnonblocking', [], $redis);

        $this->assertNull($connection->get()); // no message, should return null immediately
        $connection->add('1', []);
        $this->assertNotEmpty($message = $connection->get());
        $connection->reject($message['id']);
        $redis->del('messenger-getnonblocking');
    }

    public function testJsonError()
    {
        $redis = new \Redis();
        $connection = Connection::fromDsn('redis://localhost/json-error', [], $redis);
        try {
            $connection->add("\xB1\x31", []);
        } catch (TransportException $e) {
        }

        $this->assertSame('Malformed UTF-8 characters, possibly incorrectly encoded', $e->getMessage());
    }

    public function testMaxEntries()
    {
        $redis = $this->getMockBuilder(\Redis::class)->disableOriginalConstructor()->getMock();

        $redis->expects($this->exactly(1))->method('xadd')
            ->with('queue', '*', ['message' => '{"body":"1","headers":[]}'], 20000, true)
            ->willReturn(1);

        $connection = Connection::fromDsn('redis://localhost/queue?stream_max_entries=20000', [], $redis); // 1 = always
        $connection->add('1', []);
    }

    public function testDeleteAfterAck()
    {
        $redis = $this->getMockBuilder(\Redis::class)->disableOriginalConstructor()->getMock();

        $redis->expects($this->exactly(1))->method('xack')
            ->with('queue', 'symfony', ['1'])
            ->willReturn(1);
        $redis->expects($this->exactly(1))->method('xdel')
            ->with('queue', ['1'])
            ->willReturn(1);

        $connection = Connection::fromDsn('redis://localhost/queue?delete_after_ack=true', [], $redis); // 1 = always
        $connection->ack('1');
    }

    public function testDeleteAfterReject()
    {
        $redis = $this->getMockBuilder(\Redis::class)->disableOriginalConstructor()->getMock();

        $redis->expects($this->exactly(1))->method('xack')
            ->with('queue', 'symfony', ['1'])
            ->willReturn(1);
        $redis->expects($this->exactly(1))->method('xdel')
            ->with('queue', ['1'])
            ->willReturn(1);

        $connection = Connection::fromDsn('redis://localhost/queue?delete_after_reject=true', [], $redis); // 1 = always
        $connection->reject('1');
    }

    public function testLastErrorGetsCleared()
    {
        $redis = $this->getMockBuilder(\Redis::class)->disableOriginalConstructor()->getMock();

        $redis->expects($this->once())->method('xadd')->willReturn(0);
        $redis->expects($this->once())->method('xack')->willReturn(0);

        $redis->method('getLastError')->willReturnOnConsecutiveCalls('xadd error', 'xack error');
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

    public function testLazy()
    {
        $redis = new \Redis();
        $connection = Connection::fromDsn('redis://localhost/messenger-lazy?lazy=1', [], $redis);

        $connection->add('1', []);
        $this->assertNotEmpty($message = $connection->get());
        $this->assertSame('1', $message['body']);
        $connection->reject($message['id']);
        $redis->del('messenger-lazy');
    }
}
