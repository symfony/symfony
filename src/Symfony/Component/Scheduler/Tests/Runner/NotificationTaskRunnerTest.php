<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Runner;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Scheduler\Runner\NotificationTaskRunner;
use Symfony\Component\Scheduler\Task\NotificationTask;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class NotificationTaskRunnerTest extends TestCase
{
    public function testRunnerCannotSupportWrongTask(): void
    {
        $task = new BarTask('test');

        $runner = new NotificationTaskRunner();
        static::assertFalse($runner->support($task));
    }

    public function testRunnerCanSupportValidTask(): void
    {
        $task = new NotificationTask('test');

        $runner = new NotificationTaskRunner();
        static::assertTrue($runner->support($task));
    }

    public function testRunnerCanReturnOutputWithoutNotifier(): void
    {
        $task = new NotificationTask('test');

        $runner = new NotificationTaskRunner();

        $output = $runner->run($task);
        static::assertNull($output->getOutput());
        static::assertSame($task, $output->getTask());
        static::assertSame(130, $output->getExitCode());
    }

    public function testRunnerCanReturnExceptionOutput(): void
    {
        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects(self::once())->method('send')->willThrowException(new LogicException('An error occurred'));

        $notification = $this->createMock(Notification::class);

        $task = new NotificationTask('test', $notification);

        $runner = new NotificationTaskRunner($notifier);

        $output = $runner->run($task);
        static::assertSame('An error occurred', $output->getOutput());
        static::assertSame($task, $output->getTask());
    }

    public function testRunnerCanReturnSuccessOutput(): void
    {
        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects(self::once())->method('send');

        $notification = $this->createMock(Notification::class);

        $task = new NotificationTask('test', $notification);

        $runner = new NotificationTaskRunner($notifier);

        $output = $runner->run($task);
        static::assertNull($output->getOutput());
        static::assertSame($task, $output->getTask());
        static::assertSame(0, $output->getExitCode());
    }
}
