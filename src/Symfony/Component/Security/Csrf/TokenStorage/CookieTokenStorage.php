<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Csrf\TokenStorage;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\Exception\RuntimeException;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;

/**
 * Token storage that uses a Cookie object.
 *
 * @author Oliver Hoff <oliver@hofff.com>
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class CookieTokenStorage implements ClearableTokenStorageInterface
{
    const COOKIE_NAMESPACE = '_csrf_';
    const TRANSIENT_ATTRIBUTE_NAME = '_csrf_tokens';

    private $requestStack;
    private $secret;
    private $ttl;
    private $namespace;

    public function __construct(RequestStack $requestStack, string $secret, string $namespace = self::COOKIE_NAMESPACE, int $ttl = 3600)
    {
        $this->requestStack = $requestStack;
        $this->secret = $secret;
        $this->namespace = $namespace;
        $this->ttl = $ttl;
    }

    /**
     * {@inheritdoc}
     */
    public function getToken($tokenId)
    {
        if (null === $request = $this->requestStack->getMasterRequest()) {
            throw new TokenNotFoundException('The CSRF token with ID '.$tokenId.' cannot exist outside a request.');
        }

        $transientTokens = $request->attributes->get(self::TRANSIENT_ATTRIBUTE_NAME, []);
        if (isset($transientTokens[$tokenId])) {
            return $transientTokens[$tokenId];
        }

        if (!$cookie = $request->cookies->get($cookieName = $this->getCookieName($tokenId))) {
            throw new TokenNotFoundException('The CSRF token with ID '.$tokenId.' does not exist.');
        }

        $parts = explode('/', (string) $cookie, 4);
        if (4 != \count($parts)) {
            throw new TokenNotFoundException('The CSRF token with ID '.$tokenId.' is invalid.');
        }
        list($expires, $nonce, $signature, $token) = $parts;

        // expired token
        if ((int) $expires < time()) {
            throw new TokenNotFoundException('The CSRF token with ID '.$tokenId.' is expired.');
        }

        // invalid signature
        if (!hash_equals($this->getSignature($tokenId, $token, $nonce, $expires), $signature)) {
            throw new TokenNotFoundException('The CSRF token with ID '.$tokenId.' has an invalid signature.');
        }

        // reschedule the token to refresh it TTL
        $transientTokens[$tokenId] = $token;
        $request->attributes->set(self::TRANSIENT_ATTRIBUTE_NAME, $transientTokens);

        return $token;
    }

    /**
     * {@inheritdoc}
     */
    public function setToken($tokenId, $token)
    {
        if (null === $request = $this->requestStack->getMasterRequest()) {
            throw new RuntimeException('The Cookie CSRF token cannot exist outside a request.');
        }

        $request->attributes->set(self::TRANSIENT_ATTRIBUTE_NAME, [$tokenId => $token] + $request->attributes->get(self::TRANSIENT_ATTRIBUTE_NAME, []));
    }

    /**
     * {@inheritdoc}
     */
    public function hasToken($tokenId)
    {
        try {
            $this->getToken($tokenId);

            return true;
        } catch (TokenNotFoundException $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeToken($tokenId)
    {
        try {
            $token = $this->getToken($tokenId);
        } catch (TokenNotFoundException $e) {
            $token = null;
        }
        $this->setToken($tokenId, '');

        return $token;
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        if (null === $request = $this->requestStack->getMasterRequest()) {
            return;
        }

        $request->attributes->set(self::TRANSIENT_ATTRIBUTE_NAME, []);
        foreach ($request->cookies->keys() as $key) {
            if (0 === strpos($key, $this->namespace.'/')) {
                $tokenId = substr($key, strrpos($key, '/'));
                $this->removeToken($tokenId);
            }
        }
    }

    public function sendCookies(Response $response): void
    {
        if (null === $request = $this->requestStack->getMasterRequest()) {
            return;
        }

        $isSecure = $request->isSecure();
        foreach ($request->attributes->get(self::TRANSIENT_ATTRIBUTE_NAME, []) as $tokenId => $token) {
            $value = '' === $token ? null : sprintf('%d/%s/%s/%s', $expires = time() + $this->ttl, $nonce = strtr(base64_encode(random_bytes(6)), '/', '_'), $this->getSignature($tokenId, $token, $nonce, $expires), $token);
            $response->headers->setCookie(new Cookie($cookieName = $this->getCookieName($tokenId), $value, $expires ?? 1, null, null, $isSecure, true, false, Cookie::SAMESITE_LAX));
        }
    }

    private function getCookieName(string $tokenId): string
    {
        if (null === $request = $this->requestStack->getMasterRequest()) {
            throw new RuntimeException('The Cookie CSRF token cannot exist outside a request.');
        }

        // The cookie name contains the host to allows subdomain using the same tokenId
        return sprintf('%s/%s', $this->namespace, substr(hash_hmac('sha256', $tokenId.$request->getHost(), $this->secret), 0, 9));
    }

    private function getSignature(string $tokenId, string $token, string $nonce, int $expires): string
    {
        return hash_hmac('sha256', $tokenId.$token.$nonce.$expires, $this->secret);
    }
}
