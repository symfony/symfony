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
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\RedisExt\Connection;

/**
 * @requires extension redis >= 4.3.0
 */
class ConnectionTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $redis = Connection::fromDsn('redis://localhost/queue');

        try {
            $redis->get();
        } catch (TransportException $e) {
            if (0 === strpos($e->getMessage(), 'ERR unknown command \'X')) {
                self::markTestSkipped('Redis server >= 5 is required');
            }

            throw $e;
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

    public function testFromDsnWithOptions()
    {
        $this->assertEquals(
            Connection::fromDsn('redis://localhost', ['stream' => 'queue', 'group' => 'group1', 'consumer' => 'consumer1', 'auto_setup' => false, 'serializer' => 2]),
            Connection::fromDsn('redis://localhost/queue/group1/consumer1?serializer=2&auto_setup=0')
        );
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

    public function testKeepGettingPendingMessages()
    {
        $redis = $this->getMockBuilder(\Redis::class)->disableOriginalConstructor()->getMock();

        $redis->expects($this->exactly(3))->method('xreadgroup')
            ->with('symfony', 'consumer', ['queue' => 0], 1, null)
            ->willReturn(['queue' => [['message' => json_encode(['body' => 'Test', 'headers' => []])]]]);

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

    public function testFailedAuth()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Redis connection failed');
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

    public function testFirstGetPendingMessagesThenNewMessages()
    {
        $redis = $this->getMockBuilder(\Redis::class)->disableOriginalConstructor()->getMock();

        $count = 0;

        $redis->expects($this->exactly(2))->method('xreadgroup')
            ->with('symfony', 'consumer', $this->callback(function ($arr_streams) use (&$count) {
                ++$count;

                if (1 === $count) {
                    return '0' === $arr_streams['queue'];
                }

                return '>' === $arr_streams['queue'];
            }), 1, null)
            ->willReturn(['queue' => []]);

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

    public function testMaxEntries()
    {
        $redis = $this->getMockBuilder(\Redis::class)->disableOriginalConstructor()->getMock();

        $redis->expects($this->exactly(1))->method('xadd')
            ->with('queue', '*', ['message' => '{"body":"1","headers":[]}'], 20000, true)
            ->willReturn(1);

        $connection = Connection::fromDsn('redis://localhost/queue?stream_max_entries=20000', [], $redis); // 1 = always
        $connection->add('1', []);
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
}
