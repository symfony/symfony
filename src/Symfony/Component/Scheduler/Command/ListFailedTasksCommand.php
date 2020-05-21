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

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ListFailedTasksCommand extends Command
{
    private $worker;

    protected static $defaultName = 'scheduler:list-failed';

    public function __construct(WorkerInterface $worker)
    {
        $this->worker = $worker;

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
        $failedTasks = [];

        $table = new Table($output);
        $table->setHeaders(['Task', 'Reason', 'Date']);

        $failedTasksList = $this->worker->getFailedTasks()->toArray();

        if (0 === \count($failedTasksList)) {
            $io->warning('No failed task has been found');

            return self::SUCCESS;
        }

        foreach ($failedTasksList as $task) {
            $failedTasks[] = [$task->getName(), $task->getReason(), $task->getTriggerDate()->format('m-j-Y H:i:s')];
        }

        $table->addRows($failedTasks);

        $io->success('List of the failed tasks:');
        $table->render();

        return self::SUCCESS;
    }
}
