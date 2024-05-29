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
 * Handler for Clear-Site-Data header during logout.
 *
 * @author Max Beckers <beckers.maximilian@gmail.com>
 *
 * @final
 */
class ClearSiteDataLogoutListener implements EventSubscriberInterface
{
    private const HEADER_NAME = 'Clear-Site-Data';

    /**
     * @param string[] $cookieValue The value for the Clear-Site-Data header.
     *                              Can be '*' or a subset of 'cache', 'cookies', 'storage', 'executionContexts'.
     */
    public function __construct(private readonly array $cookieValue)
    {
    }

    public function onLogout(LogoutEvent $event): void
    {
        if (!$event->getResponse()?->headers->has(static::HEADER_NAME)) {
            $event->getResponse()->headers->set(static::HEADER_NAME, implode(', ', array_map(fn ($v) => '"'.$v.'"', $this->cookieValue)));
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LogoutEvent::class => 'onLogout',
        ];
    }
}
