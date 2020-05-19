<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Task;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskBag
{
    private $task;
    private $mails;
    private $notifications;

    public function __construct(string $task, array $mails = [], array $notifications = [])
    {
        $this->task = $task;
        $this->mails = $mails;
        $this->notifications = $notifications;
    }

    public function getTask(): string
    {
        return $this->task;
    }

    public function getMails(): array
    {
        return $this->mails;
    }

    public function getNotifications(): array
    {
        return $this->notifications;
    }
}
