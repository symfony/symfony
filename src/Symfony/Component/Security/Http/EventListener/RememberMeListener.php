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

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\RememberMe\RememberMeServicesInterface;

/**
 * The RememberMe *listener* creates and deletes remember me cookies.
 *
 * Upon login success or failure and support for remember me
 * in the firewall and authenticator, this listener will create
 * a remember me cookie.
 * Upon login failure, all remember me cookies are removed.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @final
 * @experimental in 5.1
 */
class RememberMeListener implements EventSubscriberInterface
{
    private $rememberMeServices;
    private $logger;

    public function __construct(RememberMeServicesInterface $rememberMeServices, ?LoggerInterface $logger = null)
    {
        $this->rememberMeServices = $rememberMeServices;
        $this->logger = $logger;
    }

    public function onSuccessfulLogin(LoginSuccessEvent $event): void
    {
        $passport = $event->getPassport();
        if (!$passport->hasBadge(RememberMeBadge::class)) {
            if (null !== $this->logger) {
                $this->logger->debug('Remember me skipped: your authenticator does not support it.', ['authenticator' => \get_class($event->getAuthenticator())]);
            }

            return;
        }

        if (null === $event->getResponse()) {
            if (null !== $this->logger) {
                $this->logger->debug('Remember me skipped: the authenticator did not set a success response.', ['authenticator' => \get_class($event->getAuthenticator())]);
            }

            return;
        }

        $this->rememberMeServices->loginSuccess($event->getRequest(), $event->getResponse(), $event->getAuthenticatedToken());
    }

    public function onFailedLogin(LoginFailureEvent $event): void
    {
        $this->rememberMeServices->loginFail($event->getRequest(), $event->getException());
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onSuccessfulLogin',
            LoginFailureEvent::class => 'onFailedLogin',
        ];
    }
}
