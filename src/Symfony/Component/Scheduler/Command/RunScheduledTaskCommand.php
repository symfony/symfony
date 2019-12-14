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

use Cron\CronExpression;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\SchedulerRegistryInterface;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Worker\WorkerRegistryInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class RunScheduledTaskCommand extends Command
{
    private $schedulerRegistry;
    private $io;
    private $workerRegistry;
    protected static $defaultName = 'scheduler:run';

    public function __construct(SchedulerRegistryInterface $schedulerRegistry, WorkerRegistryInterface $workerRegistry)
    {
        $this->schedulerRegistry = $schedulerRegistry;
        $this->workerRegistry = $workerRegistry;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Run the scheduled tasks')
            ->setDefinition([
                new InputArgument('scheduler', InputArgument::REQUIRED, 'Name of the scheduler used'),
                new InputOption('name', null, InputOption::VALUE_OPTIONAL, 'Name of the task(s) to run'),
                new InputOption('expression', null, InputOption::VALUE_OPTIONAL, 'The expression of the task(s) to run'),
                new InputOption('metadata', 'm', InputOption::VALUE_OPTIONAL, 'A key stored as a metadata in the task(s) to run'),
            ])
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);

        $expression = $input->getOption('expression');

        if (null !== $expression && !CronExpression::isValidExpression($expression)) {
            throw new InvalidArgumentException(sprintf('The expression "%s" is not a valid one!', $expression));
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dueTasks = null;
        $scheduler = $this->schedulerRegistry->get($input->getArgument('scheduler'));
        $options = $input->getOptions();

        switch ($options) {
            case null !== $name = $options['name']:
                $dueTasks = $scheduler->getTasks()->filter(function (TaskInterface $task) use ($name): bool {
                    return $name === $task->getName() && CronExpression::factory($task->get('expression'))->isDue(new \DateTimeImmutable(), $task->get('timezone')->getName());
                });
                break;
            case null !== $expression = $options['expression']:
                $dueTasks = $scheduler->getTasks()->filter(function (TaskInterface $task) use ($expression): bool {
                    return $expression === $task->get('expression') && CronExpression::factory($task->get('expression'))->isDue(new \DateTimeImmutable(), $task->get('timezone')->getName());
                });
                break;
            case null !== $metadata = $options['metadata']:
                $dueTasks = $scheduler->getTasks()->filter(function (TaskInterface $task) use ($metadata): bool {
                    return null !== $task->get($metadata) && CronExpression::factory($task->get('expression'))->isDue(new \DateTimeImmutable(), $task->get('timezone')->getName());
                });
                break;
            default:
                $dueTasks = $scheduler->getDueTasks();
                break;
        }

        if (0 === \count($dueTasks)) {
            $this->io->warning('No tasks found');

            return 0;
        }

        $this->io->note(sprintf('Found %d tasks', $dueTasks->count()));

        $worker = $this->workerRegistry->get($scheduler);

        foreach ($dueTasks as $task) {
            $worker->execute($task);
        }

        $this->io->success(sprintf('%d tasks executed', \count($dueTasks)));

        return 0;
    }
}
