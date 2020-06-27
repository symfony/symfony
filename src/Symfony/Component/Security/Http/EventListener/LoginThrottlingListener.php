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

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\SessionLockedException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\LoginThrottlingBadge;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @final
 * @experimental in 5.2
 */
class LoginThrottlingListener implements EventSubscriberInterface
{
    private $requestStack;
    private $cache;
    private $threshold;
    private $timeout;

    /**
     * @param int $timeout in minutes
     */
    public function __construct(RequestStack $requestStack, CacheItemPoolInterface $cache, int $threshold = 3, int $timeout = 1)
    {
        $this->requestStack = $requestStack;
        $this->cache = $cache;
        $this->threshold = $threshold;
        $this->timeout = $timeout;
    }

    /**
     * Prevents authentication if the session is locked (due to too many failed attempts).
     */
    public function checkPassport(CheckPassportEvent $event): void
    {
        $passport = $event->getPassport();
        if (!$passport->hasBadge(LoginThrottlingBadge::class)) {
            return;
        }

        $request = $this->requestStack->getMasterRequest();

        $username = $passport->getBadge(LoginThrottlingBadge::class)->getUsername();
        $cacheKey = $this->generateCacheKey($username, $request);

        $cacheItem = $this->cache->getItem($cacheKey);
        if (!$cacheItem->isHit()) {
            return;
        }

        $loginAttempts = $cacheItem->get();
        if ($loginAttempts >= $this->threshold) {
            throw new SessionLockedException($this->timeout);
        }
    }

    /**
     * Increases failed attempt counter and expands expiration time.
     */
    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $passport = $event->getPassport();
        if (!$passport->hasBadge(LoginThrottlingBadge::class)) {
            return;
        }

        $request = $this->requestStack->getMasterRequest();

        $username = $passport->getBadge(LoginThrottlingBadge::class)->getUsername();
        $cacheKey = $this->generateCacheKey($username, $request);
        $cacheItem = $this->cache->getItem($cacheKey);

        $count = $cacheItem->isHit() ? $cacheItem->get() : 0;

        $cacheItem->set(++$count);
        $cacheItem->expiresAfter($this->timeout * 60);

        $this->cache->save($cacheItem);
    }

    /**
     * Resets failed attempt counter.
     */
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $passport = $event->getPassport();
        if (!$passport->hasBadge(LoginThrottlingBadge::class)) {
            return;
        }

        $request = $this->requestStack->getMasterRequest();

        $username = $passport->getBadge(LoginThrottlingBadge::class)->getUsername();
        $cacheKey = $this->generateCacheKey($username, $request);

        $cacheItem = $this->cache->getItem($cacheKey);
        if (!$cacheItem->isHit()) {
            return;
        }

        $this->cache->deleteItem($cacheKey);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckPassportEvent::class => ['checkPassport', 64],
            LoginFailureEvent::class => 'onLoginFailure',
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    private function generateCacheKey(string $username, Request $request): string
    {
        return $username.$request->getClientIp();
    }
}
