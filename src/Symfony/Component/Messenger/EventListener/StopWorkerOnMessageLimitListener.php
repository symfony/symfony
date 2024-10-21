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
use Symfony\Component\Messenger\Event\WorkerBusyEvent;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 * @author Tobias Schultze <http://tobion.de>
 */
class StopWorkerOnMessageLimitListener implements EventSubscriberInterface
{
    private int $receivedMessages = 0;

    public function __construct(
        private int $maximumNumberOfMessages,
        private ?LoggerInterface $logger = null,
    ) {
        if ($maximumNumberOfMessages <= 0) {
            throw new InvalidArgumentException('Message limit must be greater than zero.');
        }
    }

    public function onWorkerBusy(WorkerBusyEvent $event): void
    {
        if (++$this->receivedMessages >= $this->maximumNumberOfMessages) {
            $this->receivedMessages = 0;
            $event->getWorker()->stop();

            $this->logger?->info('Worker stopped due to maximum count of {count} messages processed', ['count' => $this->maximumNumberOfMessages]);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerBusyEvent::class => 'onWorkerBusy',
        ];
    }
}
