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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\Limiter;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @experimental in 5.2
 */
final class LoginThrottlingListener implements EventSubscriberInterface
{
    private $requestStack;
    private $limiter;

    public function __construct(RequestStack $requestStack, Limiter $limiter)
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

        $request = $this->requestStack->getMasterRequest();
        $username = $passport->getBadge(UserBadge::class)->getUserIdentifier();
        $limiterKey = $this->createLimiterKey($username, $request);

        $limiter = $this->limiter->create($limiterKey);
        if (!$limiter->consume()) {
            throw new TooManyLoginAttemptsAuthenticationException();
        }
    }

    public function onSuccessfulLogin(LoginSuccessEvent $event): void
    {
        $limiterKey = $this->createLimiterKey($event->getAuthenticatedToken()->getUsername(), $event->getRequest());
        $limiter = $this->limiter->create($limiterKey);

        $limiter->reset();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckPassportEvent::class => ['checkPassport', 64],
            LoginSuccessEvent::class => 'onSuccessfulLogin',
        ];
    }

    private function createLimiterKey($username, Request $request): string
    {
        return $username.$request->getClientIp();
    }
}
