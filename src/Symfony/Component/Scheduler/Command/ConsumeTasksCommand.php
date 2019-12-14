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
use Symfony\Component\Scheduler\SchedulerInterface;
use Symfony\Component\Scheduler\SchedulerRegistryInterface;
use Symfony\Component\Scheduler\Worker\WorkerInterface;
use Symfony\Component\Scheduler\Worker\WorkerRegistryInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ConsumeTasksCommand extends Command
{
    private $eventDispatcher;
    private $logger;
    private $schedulerRegistry;
    private $workerRegistry;
    protected static $defaultName = 'scheduler:consume';

    public function __construct(EventDispatcherInterface $eventDispatcher, SchedulerRegistryInterface $schedulerRegistry, WorkerRegistryInterface $workerRegistry, LoggerInterface $logger = null)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
        $this->schedulerRegistry = $schedulerRegistry;
        $this->workerRegistry = $workerRegistry;

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
                new InputOption('time-limit', 't', InputOption::VALUE_REQUIRED, 'Limit the time inn seconds the worker can run'),
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

        $worker = $this->workerRegistry->filter(function (WorkerInterface $worker): bool {
            return !$worker->isRunning();
        });

        $availableWorker = reset($worker);

        if ($limit = $input->getOption('limit')) {
            $stopOptions[] = sprintf('%s tasks has been processed', $limit);
            $availableWorker->addSubscriber(new StopWorkerOnTaskLimitSubscriber($limit, $this->logger));
        }

        if ($timeLimit = $input->getOption('time-limit')) {
            $stopOptions[] = sprintf('the worker has been running for %d seconds', $timeLimit);
            $availableWorker->addSubscriber(new StopWorkerOnTimeLimitSubscriber($timeLimit, $this->logger));
        }

        $schedulers = $input->getArgument('schedulers');

        $filteredSchedulers = $this->schedulerRegistry->filter(function (SchedulerInterface $scheduler, string $name) use ($schedulers): bool {
            return \in_array($name, $schedulers);
        });

        $io = new SymfonyStyle($input, $output);
        if (empty($filteredSchedulers)) {
            $io->error('No schedulers can be found');

            return 1;
        }

        $io->success(sprintf('Consuming tasks from scheduler%s: "%s"', \count($schedulers) > 0 ? 's' : '', implode(', ', $schedulers)));

        $tasks = [];
        array_map(function (SchedulerInterface $scheduler) use (&$tasks): void {
            $tasks[] = $scheduler->toArray();
        }, $filteredSchedulers);

        $io->comment('Quit the worker with CONTROL-C.');

        foreach ($tasks as $task) {
            $availableWorker->execute($task);
        }

        return 0;
    }
}
