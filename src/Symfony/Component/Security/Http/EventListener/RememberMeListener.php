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
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\Event\TokenDeauthenticatedEvent;
use Symfony\Component\Security\Http\RememberMe\RememberMeHandlerInterface;

/**
 * The RememberMe *listener* creates and deletes remember-me cookies.
 *
 * Upon login success or failure and support for remember me
 * in the firewall and authenticator, this listener will create
 * a remember-me cookie.
 * Upon login failure, all remember-me cookies are removed.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @final
 */
class RememberMeListener implements EventSubscriberInterface
{
    private RememberMeHandlerInterface $rememberMeHandler;
    private ?LoggerInterface $logger;

    public function __construct(RememberMeHandlerInterface $rememberMeHandler, LoggerInterface $logger = null)
    {
        $this->rememberMeHandler = $rememberMeHandler;
        $this->logger = $logger;
    }

    public function onSuccessfulLogin(LoginSuccessEvent $event): void
    {
        $passport = $event->getPassport();
        if (!$passport->hasBadge(RememberMeBadge::class)) {
            $this->logger?->debug('Remember me skipped: your authenticator does not support it.', ['authenticator' => $event->getAuthenticator()::class]);

            return;
        }

        // Make sure any old remember-me cookies are cancelled
        $this->rememberMeHandler->clearRememberMeCookie();

        /** @var RememberMeBadge $badge */
        $badge = $passport->getBadge(RememberMeBadge::class);
        if (!$badge->isEnabled()) {
            $this->logger?->debug('Remember me skipped: the RememberMeBadge is not enabled.');

            return;
        }

        $this->logger?->debug('Remember-me was requested; setting cookie.');

        $this->rememberMeHandler->createRememberMeCookie($event->getUser());
    }

    public function clearCookie(): void
    {
        $this->rememberMeHandler->clearRememberMeCookie();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => ['onSuccessfulLogin', -64],
            LoginFailureEvent::class => 'clearCookie',
            LogoutEvent::class => 'clearCookie',
            TokenDeauthenticatedEvent::class => 'clearCookie',
        ];
    }
}
