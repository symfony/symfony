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
use Symfony\Component\Scheduler\Exception\InvalidArgumentException;
use Symfony\Component\Scheduler\Exception\LogicException;
use Symfony\Component\Scheduler\Exception\TransportException;
use Symfony\Component\Scheduler\Task\NullFactory;
use Symfony\Component\Scheduler\Task\NullTask;
use Symfony\Component\Scheduler\Task\TaskFactory;
use Symfony\Component\Scheduler\Task\TaskFactoryInterface;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskListInterface;
use Symfony\Component\Scheduler\Transport\Dsn;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @requires extension redis >= 4.3.0
 */
final class ConnectionTest extends TestCase
{
    public function testConnectionCannotBeCreatedWithInvalidCredentials(): void
    {
        $factory = $this->createMock(TaskFactoryInterface::class);

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(false);
        $redis->expects(self::once())->method('getLastError')->willReturn('ERR Error connecting user: wrong credentials');

        static::expectException(InvalidArgumentException::class);
        static::expectExceptionMessage('Redis connection failed: "ERR Error connecting user: wrong credentials".');
        new Connection(Dsn::fromString('redis://root@localhost?auth=root&port=6379&dbindex=test'), $factory, $redis);
    }

    public function testConnectionCannotBeCreatedWithTransactionMode(): void
    {
        $factory = $this->createMock(TaskFactoryInterface::class);

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);

        static::expectException(InvalidArgumentException::class);
        static::expectExceptionMessage('The transaction mode "test" is not a valid one.');
        new Connection(Dsn::fromString('redis://root@localhost/test?auth=root&port=6379&transaction_mode=test'), $factory, $redis);
    }

    public function testConnectionCannotBeCreatedWithInvalidDatabase(): void
    {
        $factory = $this->createMock(TaskFactoryInterface::class);

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(false);
        $redis->expects(self::once())->method('getLastError')->willReturn('ERR Error selecting database: wrong database name');

        static::expectException(InvalidArgumentException::class);
        static::expectExceptionMessage('Redis connection failed: "ERR Error selecting database: wrong database name".');
        new Connection(Dsn::fromString('redis://localhost?dbindex=test&auth=root&port=6379'), $factory, $redis);
    }

    public function testConnectionCannotListWithException(): void
    {
        $factory = $this->createMock(TaskFactoryInterface::class);

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);
        $redis->expects(self::once())->method('multi')->willReturnSelf();
        $redis->expects(self::once())->method('keys')->with(self::equalTo('*'))->willThrowException(new \RedisException('An error occurred'));
        $redis->expects(self::never())->method('exec');
        $redis->expects(self::once())->method('discard');

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $factory, $redis);

        static::expectException(TransportException::class);
        static::expectExceptionMessage('An error occurred');
        $connection->list();
    }
    public function testConnectionCanListEmptyData(): void
    {
        $factory = new TaskFactory([new NullFactory()]);

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);
        $redis->expects(self::once())->method('multi')->willReturnSelf();
        $redis->expects(self::once())->method('keys')->with(self::equalTo('*'))->willReturn(json_encode([]));
        $redis->expects(self::once())->method('exec');
        $redis->expects(self::never())->method('discard');

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $factory, $redis);
        $data = $connection->list();

        static::assertInstanceOf(TaskListInterface::class, $data);
        static::assertArrayNotHasKey('foo', $data->toArray());
    }

    public function testConnectionCanList(): void
    {
        $factory = new TaskFactory([new NullFactory()]);

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);
        $redis->expects(self::once())->method('multi')->willReturnSelf();
        $redis->expects(self::once())->method('keys')->with(self::equalTo('*'))->willReturn(json_encode([
            [
                'name' => 'foo',
                'expression' => '* * * * *',
                'options' => [],
                'state' => 'paused',
                'type' => 'null',
            ],
            [
                'name' => 'bar',
                'expression' => '* * * * *',
                'options' => [],
                'state' => 'enabled',
                'type' => 'null',
            ]
        ]));
        $redis->expects(self::once())->method('exec');
        $redis->expects(self::never())->method('discard');

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $factory, $redis);
        $data = $connection->list();

        static::assertInstanceOf(TaskListInterface::class, $data);
        static::assertInstanceOf(TaskInterface::class, $data->get('foo'));
    }

    public function testConnectionCannotCreateWithExistingKey(): void
    {
        $factory = $this->createMock(TaskFactoryInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('getExpression')->willReturn('* * * * *');
        $task->expects(self::once())->method('getOptions')->willReturn([]);
        $task->expects(self::once())->method('get')->with(self::equalTo('state'))->willReturn('paused');
        $task->expects(self::once())->method('getType')->willReturn('null');

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);
        $redis->expects(self::once())->method('multi')->willReturnSelf();
        $redis->expects(self::once())->method('setnx')->willReturn(false);
        $redis->expects(self::once())->method('exec');
        $redis->expects(self::never())->method('discard');

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $factory, $redis);

        static::expectException(LogicException::class);
        static::expectExceptionMessage(sprintf('The task cannot be created as it already exist, consider using "%s::update().', Connection::class));
        $connection->create($task);
    }

    public function testConnectionCannotCreateWithException(): void
    {
        $factory = $this->createMock(TaskFactoryInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('getExpression')->willReturn('* * * * *');
        $task->expects(self::once())->method('getOptions')->willReturn([]);
        $task->expects(self::once())->method('get')->with(self::equalTo('state'))->willReturn('paused');
        $task->expects(self::once())->method('getType')->willReturn('null');

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);
        $redis->expects(self::once())->method('multi')->willReturnSelf();
        $redis->expects(self::once())->method('setnx')->willThrowException(new \Exception());
        $redis->expects(self::never())->method('exec');
        $redis->expects(self::once())->method('discard');

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $factory, $redis);

        static::expectException(TransportException::class);
        $connection->create($task);
    }

    public function testConnectionCanCreate(): void
    {
        $factory = $this->createMock(TaskFactoryInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('getExpression')->willReturn('* * * * *');
        $task->expects(self::once())->method('getOptions')->willReturn([]);
        $task->expects(self::once())->method('get')->with(self::equalTo('state'))->willReturn('paused');
        $task->expects(self::once())->method('getType')->willReturn('null');

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);
        $redis->expects(self::once())->method('multi')->willReturnSelf();
        $redis->expects(self::once())->method('setnx')->willReturn(true);
        $redis->expects(self::once())->method('exec');
        $redis->expects(self::never())->method('discard');

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $factory, $redis);
        $connection->create($task);
    }

    public function testConnectionCannotGetUndefinedTask(): void
    {
        $factory = $this->createMock(TaskFactoryInterface::class);

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);
        $redis->expects(self::once())->method('get')->willReturn(false);

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $factory, $redis);

        static::expectException(InvalidArgumentException::class);
        static::expectExceptionMessage('The task does not exist');
        $connection->get('foo');
    }

    public function testConnectionCanGet(): void
    {
        $factory = new TaskFactory([new NullFactory()]);

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);
        $redis->expects(self::once())->method('get')->willReturn(json_encode([
            'name' => 'foo',
            'expression' => '* * * * *',
            'options' => [],
            'state' => 'paused',
            'type' => 'null',
        ]));

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $factory, $redis);
        $task = $connection->get('foo');

        static::assertInstanceOf(TaskInterface::class, $task);
        static::assertInstanceOf(NullTask::class, $task);
    }

    public function testConnectionCannotUpdateWithException(): void
    {
        $factory = $this->createMock(TaskFactoryInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('getExpression')->willReturn('* * * * *');
        $task->expects(self::once())->method('getOptions')->willReturn([]);
        $task->expects(self::once())->method('get')->with(self::equalTo('state'))->willReturn('paused');
        $task->expects(self::once())->method('getType')->willReturn('null');

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);
        $redis->expects(self::once())->method('multi')->willReturnSelf();
        $redis->expects(self::once())->method('set')->willThrowException(new \LogicException());
        $redis->expects(self::never())->method('exec');
        $redis->expects(self::once())->method('discard');

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $factory, $redis);

        static::expectException(TransportException::class);
        $connection->update('foo', $task);
    }

    public function testConnectionCanUpdateWithInvalidReturn(): void
    {
        $factory = $this->createMock(TaskFactoryInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('getExpression')->willReturn('* * * * *');
        $task->expects(self::once())->method('getOptions')->willReturn([]);
        $task->expects(self::once())->method('get')->with(self::equalTo('state'))->willReturn('paused');
        $task->expects(self::once())->method('getType')->willReturn('null');

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);
        $redis->expects(self::once())->method('multi')->willReturnSelf();
        $redis->expects(self::once())->method('getLastError')->willReturn('The key cannot be updated as it is already accessed');
        $redis->expects(self::once())->method('set')->willReturn(false);
        $redis->expects(self::once())->method('exec');
        $redis->expects(self::never())->method('discard');

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $factory, $redis);

        static::expectException(LogicException::class);
        static::expectExceptionMessage('The task cannot be updated, error: The key cannot be updated as it is already accessed');
        $connection->update('foo', $task);
    }

    public function testConnectionCanUpdate(): void
    {
        $factory = $this->createMock(TaskFactoryInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('getExpression')->willReturn('* * * * *');
        $task->expects(self::once())->method('getOptions')->willReturn([]);
        $task->expects(self::once())->method('get')->with(self::equalTo('state'))->willReturn('paused');
        $task->expects(self::once())->method('getType')->willReturn('null');

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);
        $redis->expects(self::once())->method('multi')->willReturnSelf();
        $redis->expects(self::once())->method('set')->willReturn(true);
        $redis->expects(self::once())->method('exec');
        $redis->expects(self::never())->method('discard');

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $factory, $redis);
        $connection->update('foo', $task);
    }

    public function testConnectionCannotPauseInvalidTask(): void
    {
        $factory = $this->createMock(TaskFactoryInterface::class);

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);
        $redis->expects(self::once())->method('multi')->willReturnSelf();
        $redis->expects(self::once())->method('get')->willReturn(false);
        $redis->expects(self::never())->method('exec');
        $redis->expects(self::once())->method('discard');

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $factory, $redis);

        static::expectException(TransportException::class);
        static::expectExceptionMessage('The task does not exist');
        static::expectExceptionCode(0);
        $connection->pause('foo');
    }

    public function testConnectionCannotPauseWithException(): void
    {
        $factory = $this->createMock(TaskFactoryInterface::class);

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);
        $redis->expects(self::once())->method('multi')->willReturnSelf();
        $redis->expects(self::once())->method('get')->willReturn(true)->willThrowException(new \RedisException('An error occurred'));
        $redis->expects(self::never())->method('exec');
        $redis->expects(self::once())->method('discard');

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $factory, $redis);

        static::expectException(TransportException::class);
        static::expectExceptionMessage('An error occurred');
        static::expectExceptionCode(0);
        $connection->pause('foo');
    }

    public function testConnectionCannotPauseWithUpdateException(): void
    {
        $factory = $this->createMock(TaskFactoryInterface::class);

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

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $factory, $redis);

        static::expectException(TransportException::class);
        static::expectExceptionMessage('The task cannot be updated');
        static::expectExceptionCode(0);
        $connection->pause('foo');
    }

    public function testConnectionCanPause(): void
    {
        $factory = $this->createMock(TaskFactoryInterface::class);

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);
        $redis->expects(self::once())->method('multi')->willReturnSelf();
        $redis->expects(self::once())->method('rPush')->willReturn(true);
        $redis->expects(self::once())->method('get')->willReturn(json_encode([
            'name' => 'foo',
            'expression' => '* * * * *',
            'options' => [],
            'type' => 'null',
            'state' => 'paused',
        ]));
        $redis->expects(self::once())->method('exec');
        $redis->expects(self::never())->method('discard');

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $factory, $redis);
        $connection->pause('foo');
    }

    public function testConnectionCannotResumeInvalidTask(): void
    {
        $factory = $this->createMock(TaskFactoryInterface::class);

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);
        $redis->expects(self::once())->method('multi')->willReturnSelf();
        $redis->expects(self::once())->method('get')->willReturn(false);
        $redis->expects(self::never())->method('exec');
        $redis->expects(self::once())->method('discard');

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $factory, $redis);

        static::expectException(TransportException::class);
        static::expectExceptionMessage('The task does not exist');
        static::expectExceptionCode(0);
        $connection->resume('foo');
    }

    public function testConnectionCannotResumeWithException(): void
    {
        $factory = $this->createMock(TaskFactoryInterface::class);

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);
        $redis->expects(self::once())->method('multi')->willReturnSelf();
        $redis->expects(self::once())->method('get')->willReturn(true)->willThrowException(new \RedisException('An error occurred'));
        $redis->expects(self::never())->method('exec');
        $redis->expects(self::once())->method('discard');

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $factory, $redis);

        static::expectException(TransportException::class);
        static::expectExceptionMessage('An error occurred');
        static::expectExceptionCode(0);
        $connection->resume('foo');
    }

    public function testConnectionCannotResumeWithUpdateException(): void
    {
        $factory = $this->createMock(TaskFactoryInterface::class);

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

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $factory, $redis);

        static::expectException(TransportException::class);
        static::expectExceptionMessage('The task cannot be updated');
        static::expectExceptionCode(0);
        $connection->resume('foo');
    }

    public function testConnectionCanResume(): void
    {
        $factory = $this->createMock(TaskFactoryInterface::class);

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);
        $redis->expects(self::once())->method('multi')->willReturnSelf();
        $redis->expects(self::once())->method('rPush')->willReturn(true);
        $redis->expects(self::once())->method('get')->willReturn(json_encode([
            'name' => 'foo',
            'expression' => '* * * * *',
            'options' => [],
            'type' => 'null',
            'state' => 'paused',
        ]));
        $redis->expects(self::once())->method('exec');
        $redis->expects(self::never())->method('discard');

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $factory, $redis);
        $connection->resume('foo');
    }

    public function testConnectionCanDeleteWithException(): void
    {
        $factory = $this->createMock(TaskFactoryInterface::class);

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);
        $redis->expects(self::once())->method('multi')->willReturnSelf();
        $redis->expects(self::once())->method('del')->with(self::equalTo('foo'))->willThrowException(new \LogicException());
        $redis->expects(self::never())->method('exec');
        $redis->expects(self::once())->method('discard');

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $factory, $redis);

        static::expectException(TransportException::class);
        $connection->delete('foo');
    }

    public function testConnectionCanDeleteWithInvalidOperation(): void
    {
        $factory = $this->createMock(TaskFactoryInterface::class);

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);
        $redis->expects(self::once())->method('multi')->willReturnSelf();
        $redis->expects(self::once())->method('del')->with(self::equalTo('foo'))->willReturn(0);
        $redis->expects(self::once())->method('exec');
        $redis->expects(self::never())->method('discard');

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $factory, $redis);

        static::expectException(LogicException::class);
        static::expectExceptionMessage('The task cannot be deleted as it does not exist');
        $connection->delete('foo');
    }

    public function testConnectionCanDelete(): void
    {
        $factory = $this->createMock(TaskFactoryInterface::class);

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);
        $redis->expects(self::once())->method('multi')->willReturnSelf();
        $redis->expects(self::once())->method('del')->with(self::equalTo('foo'))->willReturn(1);
        $redis->expects(self::once())->method('exec');
        $redis->expects(self::never())->method('discard');

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $factory, $redis);
        $connection->delete('foo');
    }

    public function testConnectionCannotEmptyWithException(): void
    {
        $factory = $this->createMock(TaskFactoryInterface::class);

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);
        $redis->expects(self::once())->method('multi')->willReturnSelf();
        $redis->expects(self::once())->method('flushDB')->willReturn(true)->willThrowException(new \LogicException());
        $redis->expects(self::never())->method('exec');
        $redis->expects(self::once())->method('discard');

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $factory, $redis);

        static::expectException(TransportException::class);
        $connection->empty();
    }

    public function testConnectionCanEmpty(): void
    {
        $factory = $this->createMock(TaskFactoryInterface::class);

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);
        $redis->expects(self::once())->method('multi')->willReturnSelf();
        $redis->expects(self::once())->method('flushDB')->willReturn(true);
        $redis->expects(self::once())->method('exec');

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $factory, $redis);
        $connection->empty();
    }
}
