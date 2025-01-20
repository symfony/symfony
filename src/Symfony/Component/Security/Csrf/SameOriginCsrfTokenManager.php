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

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * This CSRF token manager uses a combination of cookie and headers to validate non-persistent tokens.
 *
 * This manager is designed to be stateless and compatible with HTTP-caching.
 *
 * First, we validate the source of the request using the Origin/Referer headers. This relies
 * on the app being able to know its own target origin. Don't miss configuring your reverse proxy to
 * send the X-Forwarded-* / Forwarded headers if you're behind one.
 *
 * Then, we validate the request using a cookie and a CsrfToken. If the cookie is found, it should
 * contain the same value as the CsrfToken. A JavaScript snippet on the client side is responsible
 * for performing this double-submission. The token value should be regenerated on every request
 * using a cryptographically secure random generator.
 *
 * If either double-submit or Origin/Referer headers are missing, it typically indicates that
 * JavaScript is disabled on the client side, or that the JavaScript snippet was not properly
 * implemented, or that the Origin/Referer headers were filtered out.
 *
 * Requests lacking both double-submit and origin information are deemed insecure.
 *
 * When a session is found, a behavioral check is added to ensure that the validation method does not
 * downgrade from double-submit to origin checks. This prevents attackers from exploiting potentially
 * less secure validation methods once a more secure method has been confirmed as functional.
 *
 * On HTTPS connections, the cookie is prefixed with "__Host-" to prevent it from being forged on an
 * HTTP channel. On the JS side, the cookie should be set with samesite=strict to strengthen the CSRF
 * protection. The cookie is always cleared on the response to prevent any further use of the token.
 *
 * The $checkHeader argument allows the token to be checked in a header instead of or in addition to a
 * cookie. This makes it harder for an attacker to forge a request, though it may also pose challenges
 * when setting the header depending on the client-side framework in use.
 *
 * When a fallback CSRF token manager is provided, only tokens listed in the $tokenIds argument will be
 * managed by this manager. All other tokens will be delegated to the fallback manager.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class SameOriginCsrfTokenManager implements CsrfTokenManagerInterface
{
    public const TOKEN_MIN_LENGTH = 24;

    public const CHECK_NO_HEADER = 0;
    public const CHECK_HEADER = 1;
    public const CHECK_ONLY_HEADER = 2;

    /**
     * @param self::CHECK_* $checkHeader
     * @param string[]      $tokenIds
     */
    public function __construct(
        private RequestStack $requestStack,
        private ?LoggerInterface $logger = null,
        private ?CsrfTokenManagerInterface $fallbackCsrfTokenManager = null,
        private array $tokenIds = [],
        private int $checkHeader = self::CHECK_NO_HEADER,
        private string $cookieName = 'csrf-token',
    ) {
        if (!$cookieName) {
            throw new \InvalidArgumentException('The cookie name cannot be empty.');
        }

        if (!preg_match('/^[-a-zA-Z0-9_]+$/D', $cookieName)) {
            throw new \InvalidArgumentException('The cookie name contains invalid characters.');
        }

        $this->tokenIds = array_flip($tokenIds);
    }

    public function getToken(string $tokenId): CsrfToken
    {
        if (!isset($this->tokenIds[$tokenId]) && $this->fallbackCsrfTokenManager) {
            return $this->fallbackCsrfTokenManager->getToken($tokenId);
        }

        return new CsrfToken($tokenId, $this->cookieName);
    }

    public function refreshToken(string $tokenId): CsrfToken
    {
        if (!isset($this->tokenIds[$tokenId]) && $this->fallbackCsrfTokenManager) {
            return $this->fallbackCsrfTokenManager->refreshToken($tokenId);
        }

        return new CsrfToken($tokenId, $this->cookieName);
    }

    public function removeToken(string $tokenId): ?string
    {
        if (!isset($this->tokenIds[$tokenId]) && $this->fallbackCsrfTokenManager) {
            return $this->fallbackCsrfTokenManager->removeToken($tokenId);
        }

        return null;
    }

    public function isTokenValid(CsrfToken $token): bool
    {
        if (!isset($this->tokenIds[$token->getId()]) && $this->fallbackCsrfTokenManager) {
            return $this->fallbackCsrfTokenManager->isTokenValid($token);
        }

        if (!$request = $this->requestStack->getCurrentRequest()) {
            $this->logger?->error('CSRF validation failed: No request found.');

            return false;
        }

        if (\strlen($token->getValue()) < self::TOKEN_MIN_LENGTH && $token->getValue() !== $this->cookieName) {
            $this->logger?->warning('Invalid double-submit CSRF token.');

            return false;
        }

        if (false === $isValidOrigin = $this->isValidOrigin($request)) {
            $this->logger?->warning('CSRF validation failed: origin info doesn\'t match.');

            return false;
        }

        if (false === $isValidDoubleSubmit = $this->isValidDoubleSubmit($request, $token->getValue())) {
            return false;
        }

        if (null === $isValidOrigin && null === $isValidDoubleSubmit) {
            $this->logger?->warning('CSRF validation failed: double-submit and origin info not found.');

            return false;
        }

        // Opportunistically lookup at the session for a previous CSRF validation strategy
        $session = $request->hasPreviousSession() ? $request->getSession() : null;
        $usageIndexValue = $session instanceof Session ? $usageIndexReference = &$session->getUsageIndex() : 0;
        $usageIndexReference = \PHP_INT_MIN;
        $previousCsrfProtection = (int) $session?->get($this->cookieName);
        $usageIndexReference = $usageIndexValue;
        $shift = $request->isMethodSafe() ? 8 : 0;

        if ($previousCsrfProtection) {
            if (!$isValidOrigin && (1 & ($previousCsrfProtection >> $shift))) {
                $this->logger?->warning('CSRF validation failed: origin info was used in a previous request but is now missing.');

                return false;
            }

            if (!$isValidDoubleSubmit && (2 & ($previousCsrfProtection >> $shift))) {
                $this->logger?->warning('CSRF validation failed: double-submit info was used in a previous request but is now missing.');

                return false;
            }
        }

        if ($isValidOrigin && $isValidDoubleSubmit) {
            $csrfProtection = 3;
            $this->logger?->debug('CSRF validation accepted using both origin and double-submit info.');
        } elseif ($isValidOrigin) {
            $csrfProtection = 1;
            $this->logger?->debug('CSRF validation accepted using origin info.');
        } else {
            $csrfProtection = 2;
            $this->logger?->debug('CSRF validation accepted using double-submit info.');
        }

        if (1 & $csrfProtection) {
            // Persist valid origin for both safe and non-safe requests
            $previousCsrfProtection |= 1 & (1 << 8);
        }

        $request->attributes->set($this->cookieName, ($csrfProtection << $shift) | $previousCsrfProtection);

        return true;
    }

    public function clearCookies(Request $request, Response $response): void
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

    public function persistStrategy(Request $request): void
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

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->clearCookies($event->getRequest(), $event->getResponse());
        $this->persistStrategy($event->getRequest());
    }

    /**
     * @return bool|null Whether the origin is valid, null if missing
     */
    private function isValidOrigin(Request $request): ?bool
    {
        $target = $request->getSchemeAndHttpHost().'/';
        $source = 'null';

        foreach (['Origin', 'Referer'] as $header) {
            if (!$request->headers->has($header)) {
                continue;
            }
            $source = $request->headers->get($header);

            if (str_starts_with($source.'/', $target)) {
                return true;
            }
        }

        return 'null' === $source ? null : false;
    }

    /**
     * @return bool|null Whether the double-submit is valid, null if missing
     */
    private function isValidDoubleSubmit(Request $request, string $token): ?bool
    {
        if ($this->cookieName === $token) {
            return null;
        }

        if ($this->checkHeader && $request->headers->get($this->cookieName, $token) !== $token) {
            $this->logger?->warning('CSRF validation failed: wrong token found in header info.');

            return false;
        }

        $cookieName = ($request->isSecure() ? '__Host-' : '').$this->cookieName;

        if (self::CHECK_ONLY_HEADER === $this->checkHeader) {
            if (!$request->headers->has($this->cookieName)) {
                return null;
            }

            $request->cookies->set($cookieName.'_'.$token, $this->cookieName); // Ensure clearCookie() can remove any cookie filtered by a reverse-proxy

            return true;
        }

        if (($request->cookies->all()[$cookieName.'_'.$token] ?? null) !== $this->cookieName && !($this->checkHeader && $request->headers->has($this->cookieName))) {
            return null;
        }

        return true;
    }
}
