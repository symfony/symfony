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
use Symfony\Component\HttpFoundation\RateLimiter\PeekableRequestRateLimiterInterface;
use Symfony\Component\HttpFoundation\RateLimiter\RequestRateLimiterInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\Event\RateLimitExceededEvent;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
final class LoginThrottlingListener implements EventSubscriberInterface
{
    private const UNTOUCHED_LIMITER_ATTRIBUTE = '_security_login_throttling_untouched';

    public function __construct(
        private RequestStack $requestStack,
        private RequestRateLimiterInterface $limiter,
        private ?string $limiterName = null,
    ) {
    }

    public function checkPassport(CheckPassportEvent $event, ?string $eventName = null, ?EventDispatcherInterface $dispatcher = null): void
    {
        $passport = $event->getPassport();
        if (!$passport->hasBadge(UserBadge::class)) {
            return;
        }

        $request = $this->requestStack->getMainRequest();
        $request->attributes->set(SecurityRequestAttributes::LAST_USERNAME, $passport->getBadge(UserBadge::class)->getUserIdentifier());

        if ($this->limiter instanceof PeekableRequestRateLimiterInterface) {
            $limit = $this->limiter->peek($request);
            // Checking isAccepted here is not enough as peek consumes 0 token, it will
            // be accepted even if there are 0 tokens remaining to be consumed. We check both
            // anyway for safety in case third party implementations behave unexpectedly.
            if ($limit->isAccepted() && 0 !== $limit->getRemainingTokens()) {
                // Remember an untouched window, a successful login then has nothing to reset.
                $request->attributes->set(self::UNTOUCHED_LIMITER_ATTRIBUTE, $limit->getRemainingTokens() === $limit->getLimit());

                return;
            }
        } elseif (($limit = $this->limiter->consume($request))->isAccepted()) {
            return;
        }

        if ($dispatcher && class_exists(RateLimitExceededEvent::class)) {
            // peek() accepts a hit that finds no token left, but the event reports a rejection
            $rejectedLimit = $limit->isAccepted() ? new RateLimit(0, $limit->getRetryAfter(), false, $limit->getLimit(), $limit->getResetAt()) : $limit;
            $dispatcher->dispatch(new RateLimitExceededEvent($rejectedLimit, $this->limiterName));
        }

        throw new TooManyLoginAttemptsAuthenticationException(ceil(($limit->getRetryAfter()->getTimestamp() - time()) / 60));
    }

    public function onSuccessfulLogin(LoginSuccessEvent $event): void
    {
        if ($this->limiter instanceof PeekableRequestRateLimiterInterface && $event->getRequest()->attributes->get(self::UNTOUCHED_LIMITER_ATTRIBUTE, false)) {
            return;
        }

        $this->limiter->reset($event->getRequest());
    }

    public function onFailedLogin(LoginFailureEvent $event): void
    {
        if ($this->limiter instanceof PeekableRequestRateLimiterInterface) {
            $this->limiter->consume($event->getRequest());
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckPassportEvent::class => ['checkPassport', 2080],
            LoginFailureEvent::class => 'onFailedLogin',
            LoginSuccessEvent::class => 'onSuccessfulLogin',
        ];
    }
}
