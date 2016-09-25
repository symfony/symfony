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
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\Security\Core\Exception\InvalidArgumentException;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;

/**
 * Accesses tokens in a set of cookies. A changeset records edits made to
 * tokens. The changeset can be retrieved as a list of cookies to be used in a
 * response's headers to "persist" the changes.
 *
 * @author Oliver Hoff <oliver@hofff.com>
 */
class CookieTokenStorage implements TokenStorageInterface
{
    /**
     * @var string
     */
    const COOKIE_DELIMITER = '_';

    /**
     * @var array
     */
    private $transientTokens = array();

    /**
     * @var array
     */
    private $resolvedTokens = array();

    /**
     * @var array
     */
    private $refreshTokens = array();

    /**
     * @var ParameterBag
     */
    private $cookies;

    /**
     * @var bool
     */
    private $secure;

    /**
     * @var string
     */
    private $secret;

    /**
     * @var int
     */
    private $ttl;

    /**
     * @param ParameterBag $cookies
     * @param bool         $secure
     * @param string       $secret
     * @param int          $ttl
     */
    public function __construct(ParameterBag $cookies, $secure, $secret, $ttl = null)
    {
        $this->cookies = $cookies;
        $this->secure = (bool) $secure;
        $this->secret = (string) $secret;
        $this->ttl = $ttl === null ? 60 * 60 : (int) $ttl;

        if ('' === $this->secret) {
            throw new InvalidArgumentException('Secret must be a non-empty string');
        }

        if ($this->ttl < 60) {
            throw new InvalidArgumentException('TTL must be an integer greater than or equal to 60');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getToken($tokenId)
    {
        $token = $this->resolveToken($tokenId);

        if ('' === $token) {
            throw new TokenNotFoundException();
        }

        return $token;
    }

    /**
     * {@inheritdoc}
     */
    public function hasToken($tokenId)
    {
        return '' !== $this->resolveToken($tokenId);
    }

    /**
     * {@inheritdoc}
     */
    public function setToken($tokenId, $token)
    {
        $token = (string) $token;

        if ('' === $token) {
            throw new InvalidArgumentException('Empty tokens are not allowed');
        }

        $this->updateToken($tokenId, $token);
    }

    /**
     * {@inheritdoc}
     */
    public function removeToken($tokenId)
    {
        $token = $this->resolveToken($tokenId);

        $this->updateToken($tokenId, '');

        return '' === $token ? null : $token;
    }

    /**
     * @return array
     */
    public function createCookies()
    {
        $cookies = array();

        foreach ($this->transientTokens as $tokenId => $token) {
            // FIXME empty tokens are handled by the http foundations cookie class
            // and are recognized as a "delete" cookie
            // the problem is the that the value of deleted cookies get set to
            // the string "deleted" and not the empty string
            $cookies[] = $this->createTokenCookie($tokenId, $token);
            $cookies[] = $this->createVerificationCookie($tokenId, $token);
        }

        foreach ($this->refreshTokens as $tokenId => $token) {
            if (isset($this->transientTokens[$tokenId])) {
                continue;
            }

            $cookies[] = $this->createVerificationCookie($tokenId, $token);
        }

        return $cookies;
    }

    /**
     * @param string $tokenId
     * @param bool   $excludeTransient
     *
     * @return string
     */
    protected function resolveToken($tokenId, $excludeTransient = false)
    {
        if (!$excludeTransient && isset($this->transientTokens[$tokenId])) {
            return $this->transientTokens[$tokenId];
        }

        if (isset($this->resolvedTokens[$tokenId])) {
            return $this->resolvedTokens[$tokenId];
        }

        $this->resolvedTokens[$tokenId] = '';

        $token = $this->getTokenCookieValue($tokenId);
        if ('' === $token) {
            return '';
        }

        $parts = explode(self::COOKIE_DELIMITER, $this->getVerificationCookieValue($tokenId), 2);
        if (count($parts) != 2) {
            return '';
        }

        list($expires, $hash) = $parts;
        $time = time();
        if (!ctype_digit($expires) || $expires < $time) {
            return '';
        }
        if (!hash_equals($this->generateVerificationHash($tokenId, $token, $expires), $hash)) {
            return '';
        }

        $time += $this->ttl / 2;
        if ($expires < $time) {
            $this->refreshTokens[$tokenId] = $token;
        }

        return $this->resolvedTokens[$tokenId] = $token;
    }

    /**
     * @param string $tokenId
     * @param string $token
     */
    protected function updateToken($tokenId, $token)
    {
        if ($token === $this->resolveToken($tokenId, true)) {
            unset($this->transientTokens[$tokenId]);
        } else {
            $this->transientTokens[$tokenId] = $token;
        }
    }

    /**
     * @param string $tokenId
     *
     * @return string
     */
    protected function getTokenCookieValue($tokenId)
    {
        $name = $this->generateTokenCookieName($tokenId);

        return $this->cookies->get($name, '');
    }

    /**
     * @param string $tokenId
     * @param string $token
     *
     * @return Cookie
     */
    protected function createTokenCookie($tokenId, $token)
    {
        $name = $this->generateTokenCookieName($tokenId);

        return new Cookie($name, $token, 0, null, null, $this->secure, false);
    }

    /**
     * @param string $tokenId
     *
     * @return string
     */
    protected function generateTokenCookieName($tokenId)
    {
        $encodedTokenId = rtrim(strtr(base64_encode($tokenId), '+/', '-_'), '=');

        return sprintf('_csrf/%s/%s', $this->secure ? 'secure' : 'insecure', $encodedTokenId);
    }

    /**
     * @param string $tokenId
     *
     * @return string
     */
    protected function getVerificationCookieValue($tokenId)
    {
        $name = $this->generateVerificationCookieName($tokenId);

        return $this->cookies->get($name, '');
    }

    /**
     * @param string $tokenId
     * @param string $token
     *
     * @return Cookie
     */
    protected function createVerificationCookie($tokenId, $token)
    {
        $name = $this->generateVerificationCookieName($tokenId);
        $value = $this->generateVerificationCookieValue($tokenId, $token);

        return new Cookie($name, $value, 0, null, null, $this->secure, true);
    }

    /**
     * @param string $tokenId
     *
     * @return string
     */
    protected function generateVerificationCookieName($tokenId)
    {
        return $this->generateTokenCookieName($tokenId).'/verify';
    }

    /**
     * @param string $tokenId
     * @param string $token
     *
     * @return string
     */
    protected function generateVerificationCookieValue($tokenId, $token)
    {
        if ('' === $token) {
            return '';
        }

        $expires = time() + $this->ttl;
        $hash = $this->generateVerificationHash($tokenId, $token, $expires);

        return $expires.self::COOKIE_DELIMITER.$hash;
    }

    /**
     * @param string $tokenId
     * @param string $token
     * @param int    $expires
     *
     * @return string
     */
    protected function generateVerificationHash($tokenId, $token, $expires)
    {
        return hash_hmac('sha256', $tokenId.$token.$expires, $this->secret);
    }
}
