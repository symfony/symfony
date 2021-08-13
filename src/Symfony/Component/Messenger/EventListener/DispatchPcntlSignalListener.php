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

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;

/**
 * @author Tobias Schultze <http://tobion.de>
 */
class DispatchPcntlSignalListener implements EventSubscriberInterface
{
    public function onWorkerRunning(): void
    {
        pcntl_signal_dispatch();
    }

    public static function getSubscribedEvents(): array
    {
        if (!\function_exists('pcntl_signal_dispatch')) {
            return [];
        }

        return [
            WorkerRunningEvent::class => ['onWorkerRunning', 100],
        ];
    }
}
