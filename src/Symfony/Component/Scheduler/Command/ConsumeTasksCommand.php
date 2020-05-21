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

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\EventListener\StopWorkerOnTaskLimitSubscriber;
use Symfony\Component\Scheduler\EventListener\StopWorkerOnTimeLimitSubscriber;
use Symfony\Component\Scheduler\Runner\RunnerInterface;
use Symfony\Component\Scheduler\SchedulerInterface;
use Symfony\Component\Scheduler\SchedulerRegistryInterface;
use Symfony\Component\Scheduler\Task\TaskExecutionWatcherInterface;
use Symfony\Component\Scheduler\Task\TaskList;
use Symfony\Component\Scheduler\Worker\Worker;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use function array_map;
use function array_pop;
use function count;
use function implode;
use function in_array;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ConsumeTasksCommand extends Command
{
    private $eventDispatcher;
    private $logger;
    private $runners;
    private $schedulerRegistry;
    private $watcher;

    protected static $defaultName = 'scheduler:consume';

    /**
     * @param iterable|RunnerInterface[] $runners
     */
    public function __construct(iterable $runners, TaskExecutionWatcherInterface $watcher, EventDispatcherInterface $eventDispatcher, SchedulerRegistryInterface $schedulerRegistry, LoggerInterface $logger = null)
    {
        $this->runners = $runners;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
        $this->schedulerRegistry = $schedulerRegistry;
        $this->watcher = $watcher;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDescription('Consumes tasks')
            ->setDefinition([
                new InputArgument('schedulers', InputArgument::IS_ARRAY, 'The name of the schedulers to consume'),
                new InputOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Limit the number of tasks consumed'),
                new InputOption('time-limit', 't', InputOption::VALUE_REQUIRED, 'Limit the time in seconds the worker can run'),
            ])
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command consumes tasks.

    <info>php %command.full_name% <scheduler-name></info>

To consume from multiple schedulers, pass each name:
    <info>php %command.full_name% scheduler1 scheduler2</info>

Use the --limit option to limit the number of tasks consumed:
    <info>php %command.full_name% <scheduler-name> --limit=10</info>

Use the --time-limit option to stop the worker when the given time limit (in seconds) is reached:
    <info>php %command.full_name% <scheduler-name> --time-limit=3600</info>
EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $stopOptions = [];

        $schedulers = $input->getArgument('schedulers');

        $filteredSchedulers = $this->schedulerRegistry->filter(function (SchedulerInterface $scheduler, string $name) use ($schedulers): bool {
            return in_array($name, $schedulers);
        });

        $io = new SymfonyStyle($input, $output);

        if (0 === count($filteredSchedulers)) {
            $io->error('No schedulers can be found, please retry');

            return self::FAILURE;
        }

        if (count($filteredSchedulers) !== count($schedulers)) {
            $io->error('The schedulers cannot be found, please retry');

            return self::FAILURE;
        }

        $io->success(sprintf('Consuming tasks from scheduler%s: "%s"', count($schedulers) > 1 ? 's' : '', implode(', ', $schedulers)));

        if (null !== $limit = $input->getOption('limit')) {
            $stopOptions[] = sprintf('%s tasks has been processed', $limit);
            $this->eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber($limit, $this->logger));
        }

        if (null !== $timeLimit = $input->getOption('time-limit')) {
            $stopOptions[] = sprintf('it has been running for %d seconds', $timeLimit);
            $this->eventDispatcher->addSubscriber(new StopWorkerOnTimeLimitSubscriber($timeLimit, $this->logger));
        }

        $tasks = new TaskList();
        array_map(function (SchedulerInterface $scheduler) use (&$tasks): void {
            $tasks->addMultiples($scheduler->getTasks()->toArray());
        }, $filteredSchedulers);

        if ($stopOptions) {
            $last = array_pop($stopOptions);
            $stopsWhen = ($stopOptions ? implode(', ', $stopOptions).' or ' : '').$last;
            $io->comment(sprintf('The worker will automatically exit once %s.', $stopsWhen));
        }

        $io->comment('Quit the worker with CONTROL-C.');

        $worker = new Worker($this->runners, $this->watcher, $this->eventDispatcher, $this->logger);

        foreach ($tasks as $task) {
            $worker->execute($task);
        }

        return self::SUCCESS;
    }
}
