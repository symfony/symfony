<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Bridge\Redis\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Bridge\Redis\Transport\Connection;
use Symfony\Component\Scheduler\Exception\AlreadyScheduledTaskException;
use Symfony\Component\Scheduler\Exception\InvalidArgumentException;
use Symfony\Component\Scheduler\Exception\LogicException;
use Symfony\Component\Scheduler\Exception\TransportException;
use Symfony\Component\Scheduler\Task\NullTask;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskListInterface;
use Symfony\Component\Scheduler\Transport\Dsn;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @requires extension redis >= 4.3.0
 */
final class ConnectionTest extends TestCase
{
    public function testConnectionCannotBeCreatedWithInvalidCredentials(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(false);
        $redis->expects(self::once())->method('getLastError')->willReturn('ERR Error connecting user: wrong credentials');

        static::expectException(InvalidArgumentException::class);
        static::expectExceptionMessage('Redis connection failed: "ERR Error connecting user: wrong credentials".');
        new Connection(Dsn::fromString('redis://root@localhost?auth=root&port=6379&dbindex=test'), $serializer, $redis);
    }

    public function testConnectionCannotBeCreatedWithTransactionMode(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);

        static::expectException(InvalidArgumentException::class);
        static::expectExceptionMessage('The transaction mode "test" is not a valid one.');
        new Connection(Dsn::fromString('redis://root@localhost/test?auth=root&port=6379&transaction_mode=test'), $serializer, $redis);
    }

    public function testConnectionCannotBeCreatedWithInvalidDatabase(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(false);
        $redis->expects(self::once())->method('getLastError')->willReturn('ERR Error selecting database: wrong database name');

        static::expectException(InvalidArgumentException::class);
        static::expectExceptionMessage('Redis connection failed: "ERR Error selecting database: wrong database name".');
        new Connection(Dsn::fromString('redis://localhost?dbindex=test&auth=root&port=6379'), $serializer, $redis);
    }

    public function testConnectionCannotListWithException(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);
        $redis->expects(self::once())->method('multi')->willReturnSelf();
        $redis->expects(self::once())->method('keys')->with(self::equalTo('*'))->willThrowException(new \RedisException('An error occurred'));
        $redis->expects(self::never())->method('exec');
        $redis->expects(self::once())->method('discard');

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $serializer, $redis);

        static::expectException(TransportException::class);
        static::expectExceptionMessage('An error occurred');
        $connection->list();
    }

    public function testConnectionCanListEmptyData(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);
        $redis->expects(self::once())->method('multi')->willReturnSelf();
        $redis->expects(self::once())->method('keys')->with(self::equalTo('*'))->willReturn([]);
        $redis->expects(self::once())->method('exec');
        $redis->expects(self::never())->method('discard');

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $serializer, $redis);
        $data = $connection->list();

        static::assertInstanceOf(TaskListInterface::class, $data);
        static::assertArrayNotHasKey('foo', $data->toArray());
    }

    public function testConnectionCanList(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::exactly(2))->method('deserialize')->willReturn(new NullTask('foo'));

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);
        $redis->expects(self::once())->method('multi')->willReturnSelf();
        $redis->expects(self::once())->method('keys')->with(self::equalTo('*'))->willReturn(['foo', 'bar']);
        $redis->expects(self::exactly(2))->method('get')->willReturnOnConsecutiveCalls(json_encode([
            'name' => 'foo',
            'expression' => '* * * * *',
            'options' => [],
            'state' => 'paused',
            'type' => 'null',
        ]), json_encode([
            'name' => 'bar',
            'expression' => '* * * * *',
            'options' => [],
            'state' => 'enabled',
            'type' => 'null',
        ]));
        $redis->expects(self::once())->method('exec');
        $redis->expects(self::never())->method('discard');

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $serializer, $redis);
        $data = $connection->list();

        static::assertInstanceOf(TaskListInterface::class, $data);
        static::assertInstanceOf(TaskInterface::class, $data->get('foo'));
    }

    public function testConnectionCannotCreateWithExistingKey(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->method('getName')->willReturn('random');

        $taskToCreate = $this->createMock(TaskInterface::class);
        $taskToCreate->method('getName')->willReturn('random');

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())->method('deserialize')->willReturn($task);

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);
        $redis->expects(self::once())->method('multi')->willReturnSelf();
        $redis->expects(self::once())->method('keys')->with(self::equalTo('*'))->willReturn(['random']);
        $redis->expects(self::once())->method('get')->willReturn(json_encode($task));
        $redis->expects(self::never())->method('set')->willReturn(true);
        $redis->expects(self::once())->method('exec');
        $redis->expects(self::never())->method('discard');

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $serializer, $redis);

        static::expectException(AlreadyScheduledTaskException::class);
        static::expectExceptionMessage('The following task "random" has already been scheduled!');
        $connection->create($taskToCreate);
    }

    public function testConnectionCannotCreateWithException(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->method('getName')->willReturn('random');

        $taskToCreate = $this->createMock(TaskInterface::class);
        $taskToCreate->method('getName')->willReturn('foo');

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())->method('deserialize')->willReturn($task);

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);
        $redis->expects(self::exactly(2))->method('multi')->willReturnSelf();
        $redis->expects(self::once())->method('keys')->with(self::equalTo('*'))->willReturn(['random']);
        $redis->expects(self::once())->method('get')->willReturn(json_encode($task));
        $redis->expects(self::once())->method('set')->willThrowException(new \RedisException('An error occured'));
        $redis->expects(self::once())->method('exec');
        $redis->expects(self::once())->method('discard');

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $serializer, $redis);

        static::expectException(TransportException::class);
        $connection->create($taskToCreate);
    }

    public function testConnectionCannotGetUndefinedTask(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);
        $redis->expects(self::once())->method('multi')->willReturnSelf();
        $redis->expects(self::once())->method('get')->willReturn(false);
        $redis->expects(self::once())->method('exec');
        $redis->expects(self::never())->method('discard');

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $serializer, $redis);

        static::expectException(InvalidArgumentException::class);
        static::expectExceptionMessage('The task "foo" does not exist');
        $connection->get('foo');
    }

    public function testConnectionCannotUpdateWithException(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $task = $this->createMock(TaskInterface::class);

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);
        $redis->expects(self::once())->method('multi')->willReturnSelf();
        $redis->expects(self::once())->method('set')->willThrowException(new \LogicException());
        $redis->expects(self::never())->method('exec');
        $redis->expects(self::once())->method('discard');

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $serializer, $redis);

        static::expectException(TransportException::class);
        $connection->update('foo', $task);
    }

    public function testConnectionCanUpdateWithInvalidReturn(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $task = $this->createMock(TaskInterface::class);

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);
        $redis->expects(self::once())->method('multi')->willReturnSelf();
        $redis->expects(self::once())->method('getLastError')->willReturn('The key cannot be updated as it is already accessed');
        $redis->expects(self::once())->method('set')->willReturn(false);
        $redis->expects(self::once())->method('exec');
        $redis->expects(self::never())->method('discard');

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $serializer, $redis);

        static::expectException(LogicException::class);
        static::expectExceptionMessage('The task cannot be updated, error: The key cannot be updated as it is already accessed');
        $connection->update('foo', $task);
    }

    public function testConnectionCannotPauseInvalidTask(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);
        $redis->expects(self::once())->method('multi')->willReturnSelf();
        $redis->expects(self::once())->method('get')->willReturn(false);
        $redis->expects(self::never())->method('exec');
        $redis->expects(self::once())->method('discard');

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $serializer, $redis);

        static::expectException(TransportException::class);
        static::expectExceptionMessage('The task does not exist');
        static::expectExceptionCode(0);
        $connection->pause('foo');
    }

    public function testConnectionCannotPauseWithException(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);
        $redis->expects(self::once())->method('multi')->willReturnSelf();
        $redis->expects(self::once())->method('get')->willReturn(true)->willThrowException(new \RedisException('An error occurred'));
        $redis->expects(self::never())->method('exec');
        $redis->expects(self::once())->method('discard');

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $serializer, $redis);

        static::expectException(TransportException::class);
        static::expectExceptionMessage('An error occurred');
        static::expectExceptionCode(0);
        $connection->pause('foo');
    }

    public function testConnectionCannotPauseWithUpdateException(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())->method('deserialize')->willReturn(new NullTask('foo'));

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);
        $redis->expects(self::once())->method('multi')->willReturnSelf();
        $redis->expects(self::once())->method('rPush')->willReturn(false);
        $redis->expects(self::once())->method('get')->willReturn(json_encode([
            'name' => 'foo',
            'expression' => '* * * * *',
            'options' => [],
            'type' => 'null',
            'state' => 'paused',
        ]));
        $redis->expects(self::never())->method('exec');
        $redis->expects(self::once())->method('discard');

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $serializer, $redis);

        static::expectException(TransportException::class);
        static::expectExceptionMessage('The task cannot be updated');
        static::expectExceptionCode(0);
        $connection->pause('foo');
    }

    public function testConnectionCannotResumeInvalidTask(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);
        $redis->expects(self::once())->method('multi')->willReturnSelf();
        $redis->expects(self::once())->method('get')->willReturn(false);
        $redis->expects(self::never())->method('exec');
        $redis->expects(self::once())->method('discard');

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $serializer, $redis);

        static::expectException(TransportException::class);
        static::expectExceptionMessage('The task does not exist');
        static::expectExceptionCode(0);
        $connection->resume('foo');
    }

    public function testConnectionCannotResumeWithException(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::never())->method('deserialize');

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);
        $redis->expects(self::once())->method('multi')->willReturnSelf();
        $redis->expects(self::once())->method('get')->willReturn(true)->willThrowException(new \RedisException('An error occurred'));
        $redis->expects(self::never())->method('exec');
        $redis->expects(self::once())->method('discard');

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $serializer, $redis);

        static::expectException(TransportException::class);
        static::expectExceptionMessage('An error occurred');
        static::expectExceptionCode(0);
        $connection->resume('foo');
    }

    public function testConnectionCannotResumeWithUpdateException(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())->method('deserialize')->willReturn(new NullTask('foo'));

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);
        $redis->expects(self::once())->method('multi')->willReturnSelf();
        $redis->expects(self::once())->method('rPush')->willReturn(false);
        $redis->expects(self::once())->method('get')->willReturn(json_encode([
            'name' => 'foo',
            'expression' => '* * * * *',
            'options' => [],
            'type' => 'null',
            'state' => 'paused',
        ]));
        $redis->expects(self::never())->method('exec');
        $redis->expects(self::once())->method('discard');

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $serializer, $redis);

        static::expectException(TransportException::class);
        static::expectExceptionMessage('The task cannot be updated');
        static::expectExceptionCode(0);
        $connection->resume('foo');
    }

    public function testConnectionCanDeleteWithException(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);
        $redis->expects(self::once())->method('multi')->willReturnSelf();
        $redis->expects(self::once())->method('del')->with(self::equalTo('foo'))->willThrowException(new \LogicException());
        $redis->expects(self::never())->method('exec');
        $redis->expects(self::once())->method('discard');

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $serializer, $redis);

        static::expectException(TransportException::class);
        $connection->delete('foo');
    }

    public function testConnectionCanDeleteWithInvalidOperation(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);
        $redis->expects(self::once())->method('multi')->willReturnSelf();
        $redis->expects(self::once())->method('del')->with(self::equalTo('foo'))->willReturn(0);
        $redis->expects(self::once())->method('exec');
        $redis->expects(self::never())->method('discard');

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $serializer, $redis);

        static::expectException(LogicException::class);
        static::expectExceptionMessage('The task cannot be deleted as it does not exist');
        $connection->delete('foo');
    }

    public function testConnectionCanDelete(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);
        $redis->expects(self::once())->method('multi')->willReturnSelf();
        $redis->expects(self::once())->method('del')->with(self::equalTo('foo'))->willReturn(1);
        $redis->expects(self::once())->method('exec');
        $redis->expects(self::never())->method('discard');

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $serializer, $redis);
        $connection->delete('foo');
    }

    public function testConnectionCannotEmptyWithException(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);
        $redis->expects(self::once())->method('multi')->willReturnSelf();
        $redis->expects(self::once())->method('flushDB')->willReturn(true)->willThrowException(new \LogicException());
        $redis->expects(self::never())->method('exec');
        $redis->expects(self::once())->method('discard');

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $serializer, $redis);

        static::expectException(TransportException::class);
        $connection->empty();
    }

    public function testConnectionCanEmpty(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);
        $redis->expects(self::once())->method('multi')->willReturnSelf();
        $redis->expects(self::once())->method('flushDB')->willReturn(true);
        $redis->expects(self::once())->method('exec');

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $serializer, $redis);
        $connection->empty();
    }
}
