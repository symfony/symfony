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
     * @var array A map of tokens to be written in the response
     */
    private $transientTokens = array();

    /**
     * @var array A map of tokens extracted from cookies and verified
     */
    private $extractedTokens = array();

    /**
     * @var array
     */
    private $nonces = array();

    /**
     * @var array
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
     * @param string $cookies The raw HTTP Cookie header
     * @param bool   $secure
     * @param string $secret
     * @param int    $ttl
     */
    public function __construct($cookies, $secure, $secret, $ttl = null)
    {
        $this->cookies = self::parseCookieHeader($cookies);
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

        // we need to resolve the token first to record the nonces
        $this->resolveToken($tokenId);

        $this->transientTokens[$tokenId] = $token;
    }

    /**
     * {@inheritdoc}
     */
    public function removeToken($tokenId)
    {
        $token = $this->resolveToken($tokenId);

        $this->transientTokens[$tokenId] = '';

        return '' === $token ? null : $token;
    }

    /**
     * @return Cookie[]
     */
    public function createCookies()
    {
        $cookies = array();

        foreach ($this->transientTokens as $tokenId => $token) {
            if (isset($this->nonces[$tokenId])) {
                foreach (array_keys($this->nonces[$tokenId]) as $nonce) {
                    $cookies[] = $this->createDeleteCookie($tokenId, $nonce);
                }
            }

            if ($token !== '') {
                $cookies[] = $this->createCookie($tokenId, $token);
            }
        }

        return $cookies;
    }

    /**
     * @param string $tokenId
     *
     * @return string
     */
    protected function resolveToken($tokenId)
    {
        if (isset($this->transientTokens[$tokenId])) {
            return $this->transientTokens[$tokenId];
        }

        if (isset($this->extractedTokens[$tokenId])) {
            return $this->extractedTokens[$tokenId];
        }

        $this->extractedTokens[$tokenId] = '';

        $prefix = $this->generateCookieName($tokenId, '');
        $prefixLength = strlen($prefix);
        $cookies = $this->findCookiesByPrefix($prefix);

        // record the nonces used, so we can delete all obsolete cookies of this
        // token id, if necessary
        foreach ($cookies as $cookie) {
            $this->nonces[$tokenId][substr($cookie[0], $prefixLength)] = true;
        }

        // if there is more than one cookie for the prefix, we get cookie tossed maybe
        if (count($cookies) != 1) {
            return '';
        }

        $parts = explode(self::COOKIE_DELIMITER, $cookies[0][1], 3);
        if (count($parts) != 3) {
            return '';
        }
        list($expires, $signature, $token) = $parts;

        // expired token
        $time = time();
        if (!ctype_digit($expires) || $expires < $time) {
            return '';
        }

        // invalid signature
        $nonce = substr($cookies[0][0], $prefixLength);
        if (!hash_equals($this->generateSignature($tokenId, $token, $expires, $nonce), $signature)) {
            return '';
        }

        $time += $this->ttl / 2;
        if ($expires < $time) {
            $this->transientTokens[$tokenId] = $token;
        }

        return $this->extractedTokens[$tokenId] = $token;
    }

    /**
     * @param string $prefix
     *
     * @return array
     */
    protected function findCookiesByPrefix($prefix)
    {
        $cookies = array();
        foreach ($this->cookies as $cookie) {
            if (0 === strpos($cookie[0], $prefix)) {
                $cookies[] = $cookie;
            }
        }

        return $cookies;
    }

    /**
     * @param string $tokenId
     * @param string $nonce
     *
     * @return Cookie
     */
    protected function createDeleteCookie($tokenId, $nonce)
    {
        $name = $this->generateCookieName($tokenId, $nonce);

        return new Cookie($name, '', 0, null, null, $this->secure, true);
    }

    /**
     * @param string $tokenId
     * @param string $token
     *
     * @return Cookie
     */
    protected function createCookie($tokenId, $token)
    {
        $expires = time() + $this->ttl;
        $nonce = self::encodeBase64Url(random_bytes(6));
        $signature = $this->generateSignature($tokenId, $token, $expires, $nonce);

        $this->nonces[$tokenId][$nonce] = true;

        $name = $this->generateCookieName($tokenId, $nonce);
        $value = $expires.self::COOKIE_DELIMITER.$signature.self::COOKIE_DELIMITER.$token;

        return new Cookie($name, $value, 0, null, null, $this->secure, true);
    }

    /**
     * @param string $tokenId
     * @param string $nonce
     *
     * @return string
     */
    protected function generateCookieName($tokenId, $nonce)
    {
        return sprintf(
            '_csrf_%s_%s_%s',
            (int) $this->secure,
            self::encodeBase64Url($tokenId),
            $nonce
        );
    }

    /**
     * @param string $tokenId
     * @param string $token
     * @param int    $expires
     * @param string $nonce
     *
     * @return string
     */
    protected function generateSignature($tokenId, $token, $expires, $nonce)
    {
        return hash_hmac('sha256', $tokenId.$token.$expires.$nonce.$this->secure, $this->secret);
    }

    /**
     * @param string $header
     *
     * @return array
     */
    public static function parseCookieHeader($header)
    {
        $header = trim((string) $header);
        if ('' === $header) {
            return array();
        }

        $cookies = array();
        foreach (explode(';', $header) as $cookie) {
            if (false === strpos($cookie, '=')) {
                continue;
            }

            $cookies[] = array_map(function ($item) {
                return urldecode(trim($item, ' "'));
            }, explode('=', $cookie, 2));
        }

        return $cookies;
    }

    /**
     * @param string $data
     *
     * @return string
     */
    public static function encodeBase64Url($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
