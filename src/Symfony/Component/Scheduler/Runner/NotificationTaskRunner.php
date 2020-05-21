<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Runner;

use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Scheduler\Task\NotificationTask;
use Symfony\Component\Scheduler\Task\Output;
use Symfony\Component\Scheduler\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class NotificationTaskRunner implements RunnerInterface
{
    private $notifier;

    public function __construct(NotifierInterface $notifier = null)
    {
        $this->notifier = $notifier;
    }

    /**
     * {@inheritdoc}
     */
    public function run(TaskInterface $task): Output
    {
        try {
            if (null === $this->notifier) {
                return Output::forScriptTerminated($task, 130, null);
            }

            $this->notifier->send($task->get('notification'), $task->get('recipient'));

            return Output::forSuccess($task, 0, null);
        } catch (\Throwable | \Exception $exception) {
            return Output::forError($task, $exception->getCode(), $exception->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function support(TaskInterface $task): bool
    {
        return $task instanceof NotificationTask;
    }
}
