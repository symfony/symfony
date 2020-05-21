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
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\SchedulerRegistryInterface;
use Symfony\Component\Scheduler\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class GetScheduledTaskCommand extends Command
{
    private $schedulerRegistry;
    private $io;
    protected static $defaultName = 'scheduler:get-tasks';

    /**
     * {@inheritdoc}
     */
    public function __construct(SchedulerRegistryInterface $schedulerRegistry)
    {
        $this->schedulerRegistry = $schedulerRegistry;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDescription('List the scheduled tasks')
            ->setDefinition([
                new InputArgument('scheduler', InputArgument::REQUIRED, 'The scheduler used to fetch the tasks'),
                new InputOption('expression', 'e', InputOption::VALUE_OPTIONAL, 'The expression of the scheduled tasks'),
                new InputOption('state', 's', InputOption::VALUE_OPTIONAL, 'The state of the scheduled tasks'),
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
        $scheduledTasks = $this->schedulerRegistry->get($scheduler)->getTasks();

        if (null !== $state = $input->getOption('state')) {
            $scheduledTasks = $scheduledTasks->filter(function (TaskInterface $task) use ($state): bool {
                return $state === $task->get('state');
            });
        }

        if (null !== $expression = $input->getOption('expression')) {
            $scheduledTasks = $scheduledTasks->filter(function (TaskInterface $task) use ($expression): bool {
                return $expression === $task->get('expression');
            });
        }

        if (0 === \count($scheduledTasks)) {
            $this->io->warning('No tasks found');

            return self::SUCCESS;
        }

        $tableRows = [];
        foreach ($scheduledTasks as $task) {
            $taskOptions = $task->getOptions();
            $tableRows[] = [
                $task->getName(),
                $taskOptions['expression'],
                null !== $taskOptions['last_execution'] ? $taskOptions['last_execution']->format('m-j-Y H:i:s') : '',
                $taskOptions['state']
            ];
        }

        $table = new Table($output);
        $table->setHeaders(['Name', 'Expression', 'Last execution date', 'State']);
        $table->addRows($tableRows);
        $table->render();

        return self::SUCCESS;
    }
}
