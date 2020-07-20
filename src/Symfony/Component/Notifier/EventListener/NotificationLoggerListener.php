<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Notifier\Event\MessageEvent;
use Symfony\Component\Notifier\Event\NotificationEvents;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 5.1
 */
class NotificationLoggerListener implements EventSubscriberInterface, ResetInterface
{
    private $events;

    public function __construct()
    {
        $this->events = new NotificationEvents();
    }

    /**
     * {@inheritdoc}
     */
    public function reset()
    {
        $this->events = new NotificationEvents();
    }

    public function onNotification(MessageEvent $event): void
    {
        $this->events->add($event);
    }

    public function getEvents(): NotificationEvents
    {
        return $this->events;
    }

    public static function getSubscribedEvents()
    {
        return [
            MessageEvent::class => ['onNotification', -255],
        ];
    }
}
