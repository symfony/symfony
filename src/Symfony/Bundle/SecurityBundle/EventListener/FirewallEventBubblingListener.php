<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * A listener that dispatches all security events from the firewall-specific
 * dispatcher on the global event dispatcher.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
class FirewallEventBubblingListener implements EventSubscriberInterface
{
    private $eventDispatcher;

    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LogoutEvent::class => 'bubbleEvent',
            LoginFailureEvent::class => 'bubbleEvent',
            LoginSuccessEvent::class => 'bubbleEvent',
            CheckPassportEvent::class => 'bubbleEvent',
        ];
    }

    public function bubbleEvent($event): void
    {
        $this->eventDispatcher->dispatch($event);
    }
}
