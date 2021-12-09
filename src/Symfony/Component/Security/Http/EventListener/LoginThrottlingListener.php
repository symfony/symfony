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
use Symfony\Component\HttpFoundation\RateLimiter\RequestRateLimiterInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
final class LoginThrottlingListener implements EventSubscriberInterface
{
    private $requestStack;
    private $limiter;

    public function __construct(RequestStack $requestStack, RequestRateLimiterInterface $limiter)
    {
        $this->requestStack = $requestStack;
        $this->limiter = $limiter;
    }

    public function checkPassport(CheckPassportEvent $event): void
    {
        $passport = $event->getPassport();
        if (!$passport->hasBadge(UserBadge::class)) {
            return;
        }

        $request = $this->requestStack->getMainRequest();
        $request->attributes->set(Security::LAST_USERNAME, $passport->getBadge(UserBadge::class)->getUserIdentifier());

        $limit = $this->limiter->consume($request);
        if (!$limit->isAccepted()) {
            throw new TooManyLoginAttemptsAuthenticationException(ceil(($limit->getRetryAfter()->getTimestamp() - time()) / 60));
        }
    }

    public function onSuccessfulLogin(LoginSuccessEvent $event): void
    {
        $this->limiter->reset($event->getRequest());
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckPassportEvent::class => ['checkPassport', 2080],
            LoginSuccessEvent::class => 'onSuccessfulLogin',
        ];
    }
}
