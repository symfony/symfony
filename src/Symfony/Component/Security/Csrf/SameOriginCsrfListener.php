<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Csrf;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

final class SameOriginCsrfListener
{
    public function __construct(
        private readonly string $cookieName = 'csrf-token')
    {
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->clearCookies($event->getRequest(), $event->getResponse());
        $this->persistStrategy($event->getRequest());
    }

    private function clearCookies(Request $request, Response $response): void
    {
        if (!$request->attributes->has($this->cookieName)) {
            return;
        }

        $cookieName = ($request->isSecure() ? '__Host-' : '').$this->cookieName;

        foreach ($request->cookies->all() as $name => $value) {
            if ($this->cookieName === $value && str_starts_with($name, $cookieName.'_')) {
                $response->headers->clearCookie($name, '/', null, $request->isSecure(), false, 'strict');
            }
        }
    }

    private function persistStrategy(Request $request): void
    {
        if (!$request->attributes->has($this->cookieName)
            || !$request->hasSession(true)
            || !($session = $request->getSession())->isStarted()
        ) {
            return;
        }

        $usageIndexValue = $session instanceof Session ? $usageIndexReference = &$session->getUsageIndex() : 0;
        $usageIndexReference = \PHP_INT_MIN;
        $session->set($this->cookieName, $request->attributes->get($this->cookieName));
        $usageIndexReference = $usageIndexValue;
    }
}
