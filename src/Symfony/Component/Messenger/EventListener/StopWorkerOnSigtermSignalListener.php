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
use Symfony\Component\Messenger\Event\WorkerStartedEvent;

/**
 * @author Tobias Schultze <http://tobion.de>
 */
class StopWorkerOnSigtermSignalListener implements EventSubscriberInterface
{
    private ?LoggerInterface $logger;

    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    public function onWorkerStarted(WorkerStartedEvent $event): void
    {
        pcntl_signal(\SIGTERM, function () use ($event) {
            $this->logger?->info('Received SIGTERM signal.', ['transport_names' => $event->getWorker()->getMetadata()->getTransportNames()]);

            $event->getWorker()->stop();
        });
    }

    public static function getSubscribedEvents(): array
    {
        if (!\function_exists('pcntl_signal')) {
            return [];
        }

        return [
            WorkerStartedEvent::class => ['onWorkerStarted', 100],
        ];
    }
}
