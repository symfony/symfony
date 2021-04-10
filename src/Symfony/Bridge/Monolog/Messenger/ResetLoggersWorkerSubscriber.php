<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Messenger;

use Monolog\ResettableInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;

/**
 * Reset loggers between messages being handled to release buffered handler logs.
 *
 * @author Laurent VOULLEMIER <laurent.voullemier@gmail.com>
 */
class ResetLoggersWorkerSubscriber implements EventSubscriberInterface
{
    private $loggers;

    public function __construct(iterable $loggers)
    {
        $this->loggers = $loggers;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageHandledEvent::class => 'resetLoggers',
            WorkerMessageFailedEvent::class => 'resetLoggers',
        ];
    }

    public function resetLoggers(): void
    {
        foreach ($this->loggers as $logger) {
            if ($logger instanceof ResettableInterface) {
                $logger->reset();
            }
        }
    }
}
