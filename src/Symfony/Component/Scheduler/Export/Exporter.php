<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Export;

use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Scheduler\Exception\UndefinedFormatterException;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskListInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Exporter implements ExporterInterface
{
    private $formatters;
    private $filesystem;
    private $logger;

    /**
     * @param FormatterInterface[] $formatters
     */
    public function __construct(array $formatters, Filesystem $filesystem, LoggerInterface $logger = null)
    {
        $this->formatters = $formatters;
        $this->filesystem = $filesystem;
        $this->logger = $logger;
    }

    public function exportSingle(TaskInterface $task, string $filePath, string $format): void
    {
        if (0 === \count($this->formatters)) {
            throw new UndefinedFormatterException('No formatter found!');
        }

        foreach ($this->formatters as $formatter) {
            if ($formatter->support($format)) {
                $exportPath = sprintf('%s.%s', $filePath, $format);

                if ($this->filesystem->exists($exportPath)) {
                    $this->log(sprintf('A file with the name "%s" already exist!', $exportPath));
                }

                $this->filesystem->dumpFile($exportPath, $formatter->format($task));

                return;
            }
        }

        throw new \InvalidArgumentException(sprintf('The following format "%s" cannot be used!', $format));
    }

    public function export(TaskListInterface $taskList, string $filePath, string $format): void
    {
        if (0 === \count($this->formatters)) {
            throw new UndefinedFormatterException('No formatter found!');
        }

        if (0 === \count($taskList)) {
            throw new \RuntimeException('The export cannot be generated with empty tasks');
        }

        foreach ($taskList as $task) {
            $this->exportSingle($task, $filePath, $format);
        }
    }

    private function log(string $message): void
    {
        if (null === $this->logger) {
            return;
        }

        $this->logger->info($message);
    }
}
