<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\DataCollector\EventListener;

use Symfony\Bundle\SecurityBundle\DataCollector\SecurityDataCollector;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\TokenDeauthenticatedEvent;

/**
 * Feeds the {@see SecurityDataCollector} with deauthentication information
 * gathered from {@see TokenDeauthenticatedEvent}, keeping profiler-specific
 * plumbing out of the production {@see \Symfony\Component\Security\Http\Firewall\ContextListener}.
 */
class SecurityDataCollectorListener implements EventSubscriberInterface
{
    public function __construct(
        private SecurityDataCollector $dataCollector,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TokenDeauthenticatedEvent::class => 'onTokenDeauthenticated',
        ];
    }

    public function onTokenDeauthenticated(TokenDeauthenticatedEvent $event): void
    {
        $this->dataCollector->collectDeauthentication($event);
    }
}
