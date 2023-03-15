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
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class StopWorkerOnSignalsListener implements EventSubscriberInterface
{
    private array $signals;
    private ?LoggerInterface $logger;

    public function __construct(array $signals = null, LoggerInterface $logger = null)
    {
        if (null === $signals && \defined('SIGTERM')) {
            $signals = [SIGTERM, SIGINT];
        }
        $this->signals = $signals;
        $this->logger = $logger;
    }

    public function onWorkerStarted(WorkerStartedEvent $event): void
    {
        foreach ($this->signals as $signal) {
            pcntl_signal($signal, function () use ($event, $signal) {
                $this->logger?->info('Received signal {signal}.', ['signal' => $signal, 'transport_names' => $event->getWorker()->getMetadata()->getTransportNames()]);

                $event->getWorker()->stop();
            });
        }
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
