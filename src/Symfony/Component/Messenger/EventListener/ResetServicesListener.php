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
use Symfony\Component\HttpKernel\DependencyInjection\ServicesResetter;
use Symfony\Component\Messenger\Event\AbstractWorkerMessageEvent;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class ResetServicesListener implements EventSubscriberInterface
{
    private $servicesResetter;
    private $receiversName;

    public function __construct(ServicesResetter $servicesResetter, array $receiversName)
    {
        $this->servicesResetter = $servicesResetter;
        $this->receiversName = $receiversName;
    }

    public function resetServices(AbstractWorkerMessageEvent $event)
    {
        if (!\in_array($event->getReceiverName(), $this->receiversName, true)) {
            return;
        }

        $this->servicesResetter->reset();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageHandledEvent::class => ['resetServices'],
            WorkerMessageFailedEvent::class => ['resetServices'],
            WorkerRunningEvent::class => ['resetServices'],
        ];
    }
}
