<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Event;

use Symfony\Component\Scheduler\Worker\WorkerInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerStartedEvent extends Event
{
    private $worker;
    private $idle;

    public function __construct(WorkerInterface $worker, bool $idle = false)
    {
        $this->worker = $worker;
        $this->idle = $idle;
    }

    public function getWorker(): WorkerInterface
    {
        return $this->worker;
    }

    public function isWorkerIdle(): bool
    {
        return $this->idle;
    }
}
