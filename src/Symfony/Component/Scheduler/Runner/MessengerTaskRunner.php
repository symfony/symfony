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

use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\Task\MessengerTask;
use Symfony\Component\Scheduler\Task\Output;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class MessengerTaskRunner implements RunnerInterface
{
    private $bus;

    public function __construct(MessageBusInterface $bus = null)
    {
        $this->bus = $bus;
    }

    /**
     * {@inheritdoc}
     */
    public function run(TaskInterface $task): Output
    {
        try {
            if (null === $this->bus) {
                return Output::forScriptTerminated($task, 130, null);
            }

            $this->bus->dispatch($task->get('message'));

            return Output::forSuccess($task, 0, null);
        } catch (Throwable $throwable) {
            return Output::forError($task, 1, $throwable->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function support(TaskInterface $task): bool
    {
        return $task instanceof MessengerTask;
    }
}
