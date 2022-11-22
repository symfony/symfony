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

use PHPUnit\Framework\SkippedTestSuiteError;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\RedisExt\Connection;

/**
 * @requires extension redis >= 4.3.0
 * @group integration
 */
class ConnectionTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        try {
            $redis = Connection::fromDsn('redis://localhost/queue');
            $redis->get();
        } catch (TransportException $e) {
            if (str_starts_with($e->getMessage(), 'ERR unknown command \'X')) {
                throw new SkippedTestSuiteError('Redis server >= 5 is required');
            }

            throw $e;
        } catch (\RedisException $e) {
            throw new SkippedTestSuiteError($e->getMessage());
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

    public function testFromDsnWithOptionsAndTrailingSlash()
    {
        $this->assertEquals(
            Connection::fromDsn('redis://localhost/', ['stream' => 'queue', 'group' => 'group1', 'consumer' => 'consumer1', 'auto_setup' => false, 'serializer' => 2]),
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

    public function testFromDsnWithMixDsnQueryOptions()
    {
        $this->assertEquals(
            Connection::fromDsn('redis://localhost/queue/group1?serializer=2', ['consumer' => 'specific-consumer']),
            Connection::fromDsn('redis://localhost/queue/group1/specific-consumer?serializer=2')
        );

        $this->assertEquals(
            Connection::fromDsn('redis://localhost/queue/group1/consumer1', ['consumer' => 'specific-consumer']),
            Connection::fromDsn('redis://localhost/queue/group1/consumer1')
        );
    }

    public function testKeepGettingPendingMessages()
    {
        $redis = $this->createMock(\Redis::class);

        $redis->expects($this->exactly(3))->method('xreadgroup')
            ->with('symfony', 'consumer', ['queue' => 0], 1, null)
            ->willReturn(['queue' => [['message' => json_encode(['body' => 'Test', 'headers' => []])]]]);

        $connection = Connection::fromDsn('redis://localhost/queue', [], $redis);
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

    public function testNoAuthWithEmptyPassword()
    {
        $redis = $this->createMock(\Redis::class);

        $redis->expects($this->exactly(0))->method('auth')
            ->with('')
            ->willThrowException(new \RuntimeException());

        Connection::fromDsn('redis://@localhost/queue', [], $redis);
    }

    public function testAuthZeroPassword()
    {
        $redis = $this->createMock(\Redis::class);

        $redis->expects($this->exactly(1))->method('auth')
            ->with('0')
            ->willReturn(true);

        Connection::fromDsn('redis://0@localhost/queue', [], $redis);
    }

    public function testFailedAuth()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Redis connection ');
        $redis = $this->createMock(\Redis::class);

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
        $redis = $this->createMock(\Redis::class);

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
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())->method('xreadgroup')->willReturn(false);
        $redis->expects($this->once())->method('getLastError')->willReturn('Redis error happens');

        $connection = Connection::fromDsn('redis://localhost/queue', ['auto_setup' => false], $redis);
        $connection->get();
    }

    public function testGetAfterReject()
    {
        $redis = new \Redis();
        $connection = Connection::fromDsn('redis://localhost/messenger-rejectthenget', [], $redis);
        $connection->cleanup();

        $connection->add('1', []);
        $connection->add('2', []);

        $failing = $connection->get();
        $connection->reject($failing['id']);

        $connection = Connection::fromDsn('redis://localhost/messenger-rejectthenget');
        $this->assertNotNull($connection->get());

        $connection->cleanup();
    }

    public function testGetNonBlocking()
    {
        $redis = new \Redis();

        $connection = Connection::fromDsn('redis://localhost/messenger-getnonblocking', [], $redis);
        $connection->cleanup();

        $this->assertNull($connection->get()); // no message, should return null immediately
        $connection->add('1', []);
        $this->assertNotEmpty($message = $connection->get());
        $connection->reject($message['id']);

        $connection->cleanup();
    }

    public function testGetDelayed()
    {
        $redis = new \Redis();

        $connection = Connection::fromDsn('redis://localhost/messenger-delayed', [], $redis);
        $connection->cleanup();

        $connection->add('1', [], 100);
        $this->assertNull($connection->get());
        usleep(300000);
        $this->assertNotEmpty($message = $connection->get());
        $connection->reject($message['id']);

        $connection->cleanup();
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
        $redis = $this->createMock(\Redis::class);

        $redis->expects($this->exactly(1))->method('xadd')
            ->with('queue', '*', ['message' => '{"body":"1","headers":[]}'], 20000, true)
            ->willReturn(1);

        $connection = Connection::fromDsn('redis://localhost/queue?stream_max_entries=20000', [], $redis); // 1 = always
        $connection->add('1', []);
    }

    public function testLastErrorGetsCleared()
    {
        $redis = $this->createMock(\Redis::class);

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
