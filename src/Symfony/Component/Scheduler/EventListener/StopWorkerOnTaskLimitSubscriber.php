<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Scheduler\Event\WorkerStartedEvent;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class StopWorkerOnTaskLimitSubscriber implements EventSubscriberInterface
{
    private $consumedTasks = 0;
    private $maximumTasks;
    private $logger;

    public function __construct(int $maximumTasks, LoggerInterface $logger = null)
    {
        $this->maximumTasks = $maximumTasks;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            WorkerStartedEvent::class => 'onWorkerStarted',
        ];
    }

    public function onWorkerStarted(WorkerStartedEvent $event): void
    {
        if (!$event->isIdle() && ++$this->consumedTasks >= $this->maximumTasks) {
            $event->getWorker()->stop();

            $this->log('The worker has been stopped due to maximum tasks executed', ['count' => $this->consumedTasks]);
        }
    }

    private function log(string $message, array $context = []): void
    {
        if (null === $this->logger) {
            return;
        }

        $this->logger->info($message, $context);
    }
}
