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
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * This listener clears the passed cookies when a user logs out.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * @final
 */
class CookieClearingLogoutListener implements EventSubscriberInterface
{
    /**
     * @param array $cookies An array of cookies (keys are names, values contain path and domain) to unset
     */
    public function __construct(
        private array $cookies,
    ) {
    }

    public function onLogout(LogoutEvent $event): void
    {
        if (!$response = $event->getResponse()) {
            return;
        }

        foreach ($this->cookies as $cookieName => $cookieData) {
            $response->headers->clearCookie($cookieName, $cookieData['path'], $cookieData['domain'], $cookieData['secure'] ?? false, true, $cookieData['samesite'] ?? null, $cookieData['partitioned'] ?? false);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LogoutEvent::class => ['onLogout', -255],
        ];
    }
}
