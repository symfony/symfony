<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Task;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskList;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskListTest extends TestCase
{
    public function testListCanBeCreatedWithEmptyTasks(): void
    {
        $list = new TaskList();

        static::assertEmpty($list);
    }

    public function testListCanBeCreatedWithTasks(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $list = new TaskList([$task]);

        static::assertNotEmpty($list);
        static::assertSame(1, $list->count());
    }

    public function testListCanBeHydrated(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $list = new TaskList();

        $task->expects(self::once())->method('getName')->willReturn('foo');
        $list->add($task);

        static::assertNotEmpty($list);
        static::assertSame(1, $list->count());
    }

    public function testListCanBeHydratedUsingEmptyOffset(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $list = new TaskList();

        $task->expects(self::once())->method('getName')->willReturn('foo');
        $list->offsetSet(null, $task);

        static::assertNotEmpty($list);
        static::assertSame(1, $list->count());
    }

    public function testListCanBeHydratedUsingOffset(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $list = new TaskList();

        $task->expects(self::any())->method('getName')->willReturn('foo');
        $list->offsetSet('foo', $task);

        static::assertNotEmpty($list);
        static::assertSame(1, $list->count());
    }

    public function testListHasTask(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $list = new TaskList([$task]);

        static::assertTrue($list->has('foo'));
    }

    public function testListHasTaskUsingOffset(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $list = new TaskList([$task]);

        static::assertTrue($list->offsetExists('foo'));
    }

    public function testListCanReturnTask(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $list = new TaskList([$task]);

        static::assertInstanceOf(TaskInterface::class, $list->get('foo'));
    }

    public function testListCanReturnTaskUsingOffset(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $list = new TaskList([$task]);

        static::assertInstanceOf(TaskInterface::class, $list->offsetGet('foo'));
    }

    public function testListCanFindTaskByNames(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $list = new TaskList([$task]);

        $task->expects(self::any())->method('getName')->willReturn('foo');

        $tasks = $list->findByName(['foo']);

        static::assertNotEmpty($tasks);
        static::assertInstanceOf(TaskList::class, $tasks);
    }

    public function testListCanFilterTaskByNames(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $list = new TaskList([$task]);

        $task->expects(self::any())->method('getName')->willReturn('foo');

        $tasks = $list->filter(function (TaskInterface $task) {
            return 'foo' === $task->getName();
        });

        static::assertNotEmpty($tasks);
        static::assertInstanceOf(TaskList::class, $tasks);
        static::assertCount(1, $tasks);
    }

    public function testListCanRemoveTask(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $list = new TaskList([$task]);

        static::assertNotEmpty($list);
        static::assertSame(1, $list->count());

        $list->remove('foo');

        static::assertEmpty($list);
        static::assertSame(0, $list->count());
    }

    public function testListCanRemoveTaskUsingOffset(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $list = new TaskList([$task]);

        static::assertNotEmpty($list);
        static::assertSame(1, $list->count());

        $list->offsetUnset('foo');

        static::assertEmpty($list);
        static::assertSame(0, $list->count());
    }

    public function testIteratorCanBeReturned(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $list = new TaskList([$task]);

        static::assertInstanceOf(\ArrayIterator::class, $list->getIterator());
    }

    public function testArrayCanBeReturned(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $list = new TaskList([$task]);

        static::assertCount(1, $list->toArray());
    }
}
