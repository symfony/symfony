<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Export;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Scheduler\Exception\UndefinedFormatterException;
use Symfony\Component\Scheduler\Export\Exporter;
use Symfony\Component\Scheduler\Export\FormatterInterface;
use Symfony\Component\Scheduler\Export\SerializerFormatter;
use Symfony\Component\Scheduler\Serializer\TaskNormalizer;
use Symfony\Component\Scheduler\Task\ShellTask;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskList;
use Symfony\Component\Scheduler\Task\TaskListInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ExporterUnitTest extends TestCase
{
    public function testExportCannotBeGeneratedWithoutFormatter(): void
    {
        $fs = $this->createMock(Filesystem::class);
        $taskList = $this->createMock(TaskListInterface::class);

        $exporter = new Exporter([], $fs, null);

        static::expectException(UndefinedFormatterException::class);
        $exporter->export($taskList, 'json', 'export', '/srv/app/exports');
    }

    public function testExportCannotBeGeneratedOnEmptyTask(): void
    {
        $formatter = $this->createMock(FormatterInterface::class);
        $fs = $this->createMock(Filesystem::class);
        $taskList = $this->createMock(TaskListInterface::class);

        $exporter = new Exporter([$formatter], $fs, null);

        static::expectException(\RuntimeException::class);
        $exporter->export($taskList, 'json', 'export', '/srv/app/exports');
    }

    public function testExportCannotBeGeneratedOnInvalidFormat(): void
    {
        $formatter = $this->createMock(FormatterInterface::class);
        $fs = $this->createMock(Filesystem::class);
        $task = $this->createMock(TaskInterface::class);
        $taskList = new TaskList([$task]);

        $exporter = new Exporter([$formatter], $fs, null);

        static::expectException(\InvalidArgumentException::class);
        $exporter->export($taskList, 'cli', 'export', '/srv/app/exports');
    }

    public function testExportCannotBeGeneratedOnMissingDirectory(): void
    {
        $formatter = $this->createMock(FormatterInterface::class);
        $fs = $this->createMock(Filesystem::class);
        $taskList = $this->createMock(TaskListInterface::class);

        $exporter = new Exporter([$formatter], $fs, null);

        static::expectException(\RuntimeException::class);
        $exporter->export($taskList, 'json', 'export');
    }

    /**
     * @dataProvider provideFileExportData
     */
    public function testExportCanBeGenerated(array $exportData): void
    {
        $serializer = new Serializer([new TaskNormalizer()], [new JsonEncoder()]);
        $formatter = new SerializerFormatter($serializer);
        $fs = new Filesystem();
        $task = new ShellTask('foo', 'echo Symfony');
        $taskList = new TaskList([$task]);

        $fs->remove(sprintf('%s/%s', sys_get_temp_dir(), sprintf('%s.%s', $exportData['filename'], $exportData['format'])));

        $exporter = new Exporter([$formatter], $fs, null);

        $exporter->export($taskList, sprintf('%s/%s', sys_get_temp_dir(), $exportData['filename']), $exportData['format']);
        static::assertTrue($fs->exists(sprintf('%s/%s.%s', sys_get_temp_dir(), $exportData['filename'], $exportData['format'])));
    }

    public function provideFileExportData(): \Generator
    {
        yield 'Default data' => [
            [
                'format' => 'json',
                'filename' => 'export',
            ],
            [
                'format' => 'json',
                'filename' => 'false_export',
            ],
        ];
    }
}
