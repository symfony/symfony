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
    private $memoryLimit;
    private $logger;
    private $memoryResolver;

    public function __construct(int $memoryLimit, ?LoggerInterface $logger = null, ?callable $memoryResolver = null)
    {
        $this->memoryLimit = $memoryLimit;
        $this->logger = $logger;
        $this->memoryResolver = $memoryResolver ?: static function () {
            return memory_get_usage(true);
        };
    }

    public function onWorkerRunning(WorkerRunningEvent $event): void
    {
        $memoryResolver = $this->memoryResolver;
        $usedMemory = $memoryResolver();
        if ($usedMemory > $this->memoryLimit) {
            $event->getWorker()->stop();
            if (null !== $this->logger) {
                $this->logger->info('Worker stopped due to memory limit of {limit} bytes exceeded ({memory} bytes used)', ['limit' => $this->memoryLimit, 'memory' => $usedMemory]);
            }
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            WorkerRunningEvent::class => 'onWorkerRunning',
        ];
    }
}
