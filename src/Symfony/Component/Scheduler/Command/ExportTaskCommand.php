<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\Export\ExporterInterface;
use Symfony\Component\Scheduler\SchedulerRegistryInterface;
use Symfony\Component\Scheduler\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ExportTaskCommand extends Command
{
    private $exporter;
    private $exportDirectory;
    private $schedulerRegistry;
    private $io;

    protected static $defaultName = 'scheduler:export';

    public function __construct(ExporterInterface $exporter, string $exportDirectory, SchedulerRegistryInterface $schedulerRegistry)
    {
        $this->exporter = $exporter;
        $this->exportDirectory = $exportDirectory;
        $this->schedulerRegistry = $schedulerRegistry;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDescription('Allow to export the desired tasks into a specific file')
            ->setDefinition([
                new InputArgument('scheduler', InputArgument::REQUIRED, 'The name of the scheduler that contains the tasks'),
                new InputOption('directory', 'd', InputOption::VALUE_OPTIONAL, 'The directory where the export will be generated', $this->exportDirectory),
                new InputOption('format', null, InputOption::VALUE_OPTIONAL, 'The format used to export the tasks', 'json'),
                new InputOption('filename', null, InputOption::VALUE_OPTIONAL, 'The filename which contain the task list once exported', 'tasks'),
                new InputOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'The maximum amount of tasks to export', null),
                new InputOption('type', null, InputOption::VALUE_OPTIONAL, 'The type of the tasks to export'),
                new InputOption('tag', null, InputOption::VALUE_OPTIONAL, 'The tag used by the tasks to export', null),
            ])
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $scheduler = $input->getArgument('scheduler');
        $exportDirectory = $input->getOption('directory');
        $format = $input->getOption('format');
        $filename = $input->getOption('filename');

        $tasks = $this->schedulerRegistry->get($scheduler)->getTasks();

        if (null !== $tag = $input->getOption('tag')) {
            $tasks = $tasks->filter(function (TaskInterface $task) use ($tag): bool {
                return $tag === $task->get('tag');
            });
        }

        if (null !== $limit = $input->getOption('limit')) {
            $tasks = \array_slice($tasks->toArray(), 0, $limit);
        }

        if (0 === \count($tasks)) {
            $this->io->error('[KO] No task found!');

            return self::FAILURE;
        }

        $exportDirectory = sprintf('%s/%s', $exportDirectory, $filename);

        $this->exporter->export($tasks, $exportDirectory, $format);
        $this->io->success(sprintf('[OK] Exported "%d" tasks to "%s"', \count($tasks), sprintf('%s.%s', $exportDirectory, $format)));

        return self::SUCCESS;
    }
}
