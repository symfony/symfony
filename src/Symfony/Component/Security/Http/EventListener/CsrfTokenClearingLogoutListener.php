<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\EventListener;

use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Csrf\TokenStorage\ClearableTokenStorageInterface;
use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\FirewallMapInterface;

/**
 * @author Christian Flothmann <christian.flothmann@sensiolabs.de>
 *
 * @final
 */
class CsrfTokenClearingLogoutListener implements EventSubscriberInterface
{
    private ClearableTokenStorageInterface $csrfTokenStorage;
    private FirewallMapInterface $map;

    public function __construct(ClearableTokenStorageInterface $csrfTokenStorage, FirewallMapInterface $map)
    {
        $this->csrfTokenStorage = $csrfTokenStorage;
        $this->map = $map;
    }

    public function onLogout(LogoutEvent $event): void
    {
        $request = $event->getRequest();

        if (
            $this->csrfTokenStorage instanceof SessionTokenStorage
            && (
                ($this->map instanceof FirewallMap && $this->map->getFirewallConfig($request)->isStateless())
                || !$request->hasPreviousSession()
            )
        ) {
            return;
        }

        $this->csrfTokenStorage->clear();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LogoutEvent::class => 'onLogout',
        ];
    }
}
