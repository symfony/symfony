<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Security;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\Logout\LogoutHandlerInterface;
use Symfony\Component\Security\Http\Logout\LogoutSuccessHandlerInterface;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @internal
 */
class LegacyLogoutHandlerListener implements EventSubscriberInterface
{
    private $logoutHandler;

    public function __construct(object $logoutHandler)
    {
        if (!$logoutHandler instanceof LogoutSuccessHandlerInterface && !$logoutHandler instanceof LogoutHandlerInterface) {
            throw new \InvalidArgumentException(sprintf('An instance of "%s" or "%s" must be passed to "%s", "%s" given.', LogoutHandlerInterface::class, LogoutSuccessHandlerInterface::class, __METHOD__, get_debug_type($logoutHandler)));
        }

        $this->logoutHandler = $logoutHandler;
    }

    public function onLogout(LogoutEvent $event): void
    {
        if ($this->logoutHandler instanceof LogoutSuccessHandlerInterface) {
            $event->setResponse($this->logoutHandler->onLogoutSuccess($event->getRequest()));
        } elseif ($this->logoutHandler instanceof LogoutHandlerInterface) {
            $this->logoutHandler->logout($event->getRequest(), $event->getResponse(), $event->getToken());
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LogoutEvent::class => 'onLogout',
        ];
    }
}
