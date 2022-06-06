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
use Symfony\Component\Security\Core\Exception\LogicException;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\RememberMe\RememberMeServicesInterface;

trigger_deprecation('symfony/security-http', '5.4', 'The "%s" class is deprecated.', RememberMeLogoutListener::class);

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @final
 *
 * @deprecated since Symfony 5.4
 */
class RememberMeLogoutListener implements EventSubscriberInterface
{
    private $rememberMeServices;

    public function __construct(RememberMeServicesInterface $rememberMeServices)
    {
        if (!method_exists($rememberMeServices, 'logout')) {
            trigger_deprecation('symfony/security-core', '5.1', '"%s" should implement the "logout(Request $request, Response $response, TokenInterface $token)" method, this method will be added to the "%s" in version 6.0.', \get_class($rememberMeServices), RememberMeServicesInterface::class);
        }

        $this->rememberMeServices = $rememberMeServices;
    }

    public function onLogout(LogoutEvent $event): void
    {
        if (!method_exists($this->rememberMeServices, 'logout')) {
            return;
        }

        if (!$event->getToken()) {
            return;
        }

        if (null === $event->getResponse()) {
            throw new LogicException(sprintf('No response was set for this logout action. Make sure the DefaultLogoutListener or another listener has set the response before "%s" is called.', __CLASS__));
        }

        $this->rememberMeServices->logout($event->getRequest(), $event->getResponse(), $event->getToken());
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LogoutEvent::class => 'onLogout',
        ];
    }
}
