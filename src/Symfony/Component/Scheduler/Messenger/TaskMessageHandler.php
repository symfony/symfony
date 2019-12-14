<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Messenger;

use Cron\CronExpression;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Scheduler\Worker\WorkerInterface;
use Symfony\Component\Scheduler\Worker\WorkerRegistryInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskMessageHandler implements MessageHandlerInterface
{
    private $workerRegistry;

    public function __construct(WorkerRegistryInterface $workerRegistry)
    {
        $this->workerRegistry = $workerRegistry;
    }

    /**
     * @throws \Exception
     */
    public function __invoke(TaskMessage $message): void
    {
        $task = $message->getTask();

        if (!CronExpression::factory($task->get('expression'))->isDue($task->get('scheduled_at'), $task->get('timezone'))) {
            return;
        }

        $workers = $this->workerRegistry->filter(function (WorkerInterface $worker): bool {
            return !$worker->isRunning();
        });

        reset($workers)->execute($task);
    }
}
