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
use Symfony\Component\Scheduler\Serializer\TaskNormalizer;
use Symfony\Component\Scheduler\Task\NullTask;
use Symfony\Component\Scheduler\Task\ShellTask;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskListInterface;
use Symfony\Component\Scheduler\Transport\Dsn;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @requires extension redis >= 4.3.0
 */
final class ConnectionIntegrationTest extends TestCase
{
    /**
     * @dataProvider provideTasks
     */
    public function testConnectionCanList(TaskInterface $task): void
    {
        $serializer = new Serializer([new TaskNormalizer(), new JsonSerializableNormalizer(), new ArrayDenormalizer()], [new JsonEncoder()]);
        $serializedTask = $serializer->serialize($task, 'json');

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);
        $redis->expects(self::once())->method('multi')->willReturnSelf();
        $redis->expects(self::once())->method('keys')->with(self::equalTo('*'))->willReturn(['foo', 'bar']);
        $redis->expects(self::exactly(2))->method('get')->willReturn($serializedTask);
        $redis->expects(self::once())->method('exec');
        $redis->expects(self::never())->method('discard');

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $serializer, $redis);
        $list = $connection->list();

        static::assertInstanceOf(TaskListInterface::class, $list);
        static::assertNotEmpty($list);
    }

    /**
     * @dataProvider provideCreateTasks
     */
    public function testConnectionCanCreate(TaskInterface $task): void
    {
        $serializer = new Serializer([new TaskNormalizer(), new JsonSerializableNormalizer(), new ArrayDenormalizer()], [new JsonEncoder()]);
        $serializedTask = $serializer->serialize(new NullTask('random'), 'json');

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);
        $redis->expects(self::exactly(3))->method('multi')->willReturnSelf();
        $redis->expects(self::never())->method('setnx')->willReturn(true);
        $redis->expects(self::once())->method('keys')->with(self::equalTo('*'))->willReturn(['random']);
        $redis->expects(self::once())->method('get')->willReturn($serializedTask);
        $redis->expects(self::exactly(2))->method('set')->willReturn(true);
        $redis->expects(self::exactly(3))->method('exec');
        $redis->expects(self::never())->method('discard');

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $serializer, $redis);
        $connection->create($task);
    }

    /**
     * @dataProvider provideTasks
     */
    public function testConnectionCanGet(TaskInterface $task): void
    {
        $serializer = new Serializer([new TaskNormalizer(), new JsonSerializableNormalizer(), new ArrayDenormalizer()], [new JsonEncoder()]);
        $serializedTask = $serializer->serialize($task, 'json');

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);
        $redis->expects(self::once())->method('multi')->willReturnSelf();
        $redis->expects(self::once())->method('get')->willReturn($serializedTask);
        $redis->expects(self::once())->method('exec');
        $redis->expects(self::never())->method('discard');

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $serializer, $redis);
        $storedTask = $connection->get($task->getName());

        static::assertInstanceOf(TaskInterface::class, $storedTask);
    }

    /**
     * @dataProvider provideTasks
     */
    public function testConnectionCanPause(TaskInterface $task): void
    {
        $serializer = new Serializer([new TaskNormalizer(), new JsonSerializableNormalizer(), new ArrayDenormalizer()], [new JsonEncoder()]);
        $serializedTask = $serializer->serialize($task, 'json');

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);
        $redis->expects(self::once())->method('multi')->willReturnSelf();
        $redis->expects(self::once())->method('get')->willReturn($serializedTask);
        $redis->expects(self::once())->method('exec');
        $redis->expects(self::never())->method('discard');

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $serializer, $redis);
        $connection->pause($task->getName());
    }

    /**
     * @dataProvider provideTasks
     */
    public function testConnectionCanResume(TaskInterface $task): void
    {
        $serializer = new Serializer([new TaskNormalizer(), new JsonSerializableNormalizer(), new ArrayDenormalizer()], [new JsonEncoder()]);
        $serializedTask = $serializer->serialize($task, 'json');

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->willReturn(true);
        $redis->expects(self::once())->method('multi')->willReturnSelf();
        $redis->expects(self::once())->method('get')->willReturn($serializedTask);
        $redis->expects(self::once())->method('exec');
        $redis->expects(self::never())->method('discard');

        $connection = new Connection(Dsn::fromString('redis://localhost/test?auth=root&port=6379'), $serializer, $redis);
        $connection->resume($task->getName());
    }

    public function provideTasks(): \Generator
    {
        yield 'NullTask' => [
            new NullTask('foo')
        ];
        yield 'ShellTask' => [
            new ShellTask('bar', 'ls -al /srv/app'),
        ];
    }

    public function provideCreateTasks(): \Generator
    {
        yield 'NullTask' => [
            new NullTask('foo')
        ];
        yield 'ShellTask' => [
            new ShellTask('bar', 'ls -al /srv/app'),
        ];
    }
}
