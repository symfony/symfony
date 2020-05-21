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
use Symfony\Component\Scheduler\Exception\InvalidArgumentException;
use Symfony\Component\Scheduler\SchedulerRegistryInterface;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class RebootSchedulerCommand extends Command
{
    private $registry;
    private $worker;

    protected static $defaultName = 'scheduler:reboot';

    public function __construct(SchedulerRegistryInterface $registry, WorkerInterface $worker)
    {
        $this->registry = $registry;
        $this->worker = $worker;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDescription('Reboot a specific Scheduler')
            ->setDefinition([
                new InputArgument('scheduler', InputArgument::REQUIRED, 'The name of the scheduler to reboot'),
                new InputOption('dry-run', 'd', InputOption::VALUE_OPTIONAL, 'Test the reboot without executing the tasks, the "ready to reboot" tasks are displayed', false)
            ])
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('scheduler');

        try {
            $scheduler = $this->registry->get($name);
        } catch (InvalidArgumentException $exception) {
            $io->error(sprintf('The desired scheduler "%s" cannot be found!', $name));

            return self::FAILURE;
        }

        $dryRun = $input->getOption('dry-run');

        if ($dryRun) {
            $tasks = $scheduler->getTasks()->filter(function (TaskInterface $task): bool {
                return '@reboot' === $task->get('expression');
            });

            if (0 === \count($tasks)) {
                $io->warning('The scheduler does not contain any tasks planned for the reboot process');

                return self::SUCCESS;
            }

            $table = new Table($output);
            $table->setHeaders(['Name', 'Type']);

            foreach ($tasks as $task) {
                $table->addRow([$task->getName(), get_class($task)]);
            }

            $io->success('The following tasks are planned to be executed when the scheduler will reboot:');
            $table->render();

            return self::SUCCESS;
        }

        $scheduler->reboot();
        $tasks = $scheduler->getTasks();

        if (0 === \count($tasks)) {
            $io->success(sprintf('The desired scheduler "%s" have been rebooted', $name));

            return self::SUCCESS;
        }

        while ($this->worker->isRunning()) {
            $io->warning('The scheduler cannot be rebooted as the worker is not available, retrying to access it');
            sleep(1);
        }

        foreach ($tasks as $rebootTask) {
            $this->worker->execute($rebootTask);
        }

        $io->success(sprintf('The desired scheduler "%s" have been rebooted', $name));

        return self::SUCCESS;
    }
}
