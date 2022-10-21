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

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;

/**
 * @author Simon Delicata <simon.delicata@free.fr>
 * @author Tobias Schultze <http://tobion.de>
 */
class StopWorkerOnMemoryLimitListener implements EventSubscriberInterface
{
    private int $memoryLimit;
    private ?LoggerInterface $logger;
    private \Closure $memoryResolver;

    public function __construct(int $memoryLimit, LoggerInterface $logger = null, callable $memoryResolver = null)
    {
        $this->memoryLimit = $memoryLimit;
        $this->logger = $logger;
        $memoryResolver ??= static fn () => memory_get_usage(true);
        $this->memoryResolver = $memoryResolver(...);
    }

    public function onWorkerRunning(WorkerRunningEvent $event): void
    {
        $memoryResolver = $this->memoryResolver;
        $usedMemory = $memoryResolver();
        if ($usedMemory > $this->memoryLimit) {
            $event->getWorker()->stop();
            $this->logger?->info('Worker stopped due to memory limit of {limit} bytes exceeded ({memory} bytes used)', ['limit' => $this->memoryLimit, 'memory' => $usedMemory]);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerRunningEvent::class => 'onWorkerRunning',
        ];
    }
}
