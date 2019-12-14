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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\Worker\WorkerInterface;
use Symfony\Component\Scheduler\Worker\WorkerRegistryInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ListFailedTasksCommand extends Command
{
    private $workerRegistry;
    protected static $defaultName = 'scheduler:list-failed';

    public function __construct(WorkerRegistryInterface $workerRegistry)
    {
        $this->workerRegistry = $workerRegistry;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDescription('List all the failed tasks')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $workers = $this->workerRegistry->toArray();

        if (0 === \count($workers)) {
            $io->warning('No worker found');

            return 1;
        }

        $failedTasks = [];

        $table = new Table($output);
        $table->setHeaders(['Worker', 'Task', 'Reason', 'Date']);

        array_walk($workers, function (WorkerInterface $worker, string $name) use (&$failedTasks): void {
            $failedTasksList = $worker->getFailedTasks()->toArray();

            foreach ($failedTasksList as $task) {
                $failedTasks[] = [$name, $task->getName(), $task->getReason(), $task->getTriggerDate()->format('m-j-Y H:i:s')];
            }
        });

        $table->addRows($failedTasks);
        $table->render();

        return 0;
    }
}
