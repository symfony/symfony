<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\EventListener;

use Symfony\Component\Messenger\Event\WorkerRunningEvent;

/**
 * @author Gary PEGEOT <garypegeot@gmail.com>
 */
class StopWorkerOnInactivityLimitListener extends StopWorkerOnTimeLimitListener
{
    public function onWorkerRunning(WorkerRunningEvent $event): void
    {
        if (!$event->isWorkerIdle()) {
            $this->endTime += $this->timeLimitInSeconds;
        }

        parent::onWorkerRunning($event);
    }
}
