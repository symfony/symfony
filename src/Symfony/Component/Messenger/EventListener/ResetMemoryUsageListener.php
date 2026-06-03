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
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;

/**
 * @author Tim Düsterhus <tim@tideways-gmbh.com>
 */
final class ResetMemoryUsageListener implements EventSubscriberInterface
{
    private bool $collect = false;

    public function resetBefore(WorkerMessageReceivedEvent $event): void
    {
        // Reset the peak memory usage for accurate measurement of the
        // memory usage on a per-message basis.
        memory_reset_peak_usage();
        $this->collect = true;
    }

    public function collectAfter(WorkerRunningEvent $event): void
    {
        if ($event->isWorkerIdle() && $this->collect) {
            gc_collect_cycles();
            $this->collect = false;
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageReceivedEvent::class => ['resetBefore', -1024],
            WorkerRunningEvent::class => ['collectAfter', -1024],
        ];
    }
}
