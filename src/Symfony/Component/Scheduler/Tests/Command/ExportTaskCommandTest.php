<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Scheduler\Command\ExportTaskCommand;
use Symfony\Component\Scheduler\Export\ExporterInterface;
use Symfony\Component\Scheduler\SchedulerInterface;
use Symfony\Component\Scheduler\SchedulerRegistryInterface;
use Symfony\Component\Scheduler\Task\TaskListInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ExportTaskCommandTest extends TestCase
{
    public function testCommandIsConfigured(): void
    {
        $scheduler = $this->createMock(SchedulerRegistryInterface::class);
        $exporter = $this->createMock(ExporterInterface::class);

        $command = new ExportTaskCommand($exporter, sys_get_temp_dir(), $scheduler);

        static::assertSame('scheduler:export', $command->getName());
        static::assertSame('Allow to export the desired tasks into a specific file', $command->getDescription());
        static::assertNotNull($command->getDefinition());
    }

    public function testCommandCannotExportEmptyTasks(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);

        $schedulerRegistry = $this->createMock(SchedulerRegistryInterface::class);
        $schedulerRegistry->expects(self::once())->method('get')->willReturn($scheduler);

        $exporter = $this->createMock(ExporterInterface::class);

        $command = new ExportTaskCommand($exporter, sys_get_temp_dir(), $schedulerRegistry);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('scheduler:export'));
        $tester->execute([
            'scheduler' => 'foo',
            '--format' => 'json',
            '--filename' => 'export',
        ]);

        static::assertSame(1, $tester->getStatusCode());
        static::assertStringContainsString('[KO] No task found!', $tester->getDisplay());
    }

    public function testCommandCanExportTasks(): void
    {
        $tasksList = $this->createMock(TaskListInterface::class);
        $tasksList->expects(self::exactly(2))->method('count')->willReturn(1);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($tasksList);

        $schedulerRegistry = $this->createMock(SchedulerRegistryInterface::class);
        $schedulerRegistry->expects(self::once())->method('get')->willReturn($scheduler);

        $exporter = $this->createMock(ExporterInterface::class);

        $command = new ExportTaskCommand($exporter, sys_get_temp_dir(), $schedulerRegistry);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('scheduler:export'));
        $tester->execute([
            'scheduler' => 'foo',
            '--format' => 'json',
            '--filename' => 'export',
        ]);

        static::assertSame(0, $tester->getStatusCode());
        static::assertStringContainsString(sprintf('[OK] Exported "1" tasks to "%s/export.json"', sys_get_temp_dir()), $tester->getDisplay());
    }
}
