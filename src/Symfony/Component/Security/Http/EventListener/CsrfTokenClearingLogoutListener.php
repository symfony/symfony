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

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Csrf\TokenStorage\ClearableTokenStorageInterface;
use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * @author Christian Flothmann <christian.flothmann@sensiolabs.de>
 *
 * @final
 */
class CsrfTokenClearingLogoutListener implements EventSubscriberInterface
{
    public function __construct(
        private ClearableTokenStorageInterface $csrfTokenStorage,
    ) {
    }

    public function onLogout(LogoutEvent $event): void
    {
        if ($this->csrfTokenStorage instanceof SessionTokenStorage && !$event->getRequest()->hasPreviousSession()) {
            return;
        }

        // Don't consider clearing the CSRF token storage as a stateful operation - it's only opportunistic
        $session = $event->getRequest()->getSession();
        $usageIndexValue = $session instanceof Session ? $usageIndexReference = &$session->getUsageIndex() : 0;
        $usageIndexReference = \PHP_INT_MIN;

        try {
            $this->csrfTokenStorage->clear();
        } finally {
            $usageIndexReference = $usageIndexValue;
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LogoutEvent::class => 'onLogout',
        ];
    }
}
