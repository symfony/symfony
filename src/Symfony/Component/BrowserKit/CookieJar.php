<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\BrowserKit;

/**
 * CookieJar.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
class CookieJar
{
    protected $cookieJar = array();

    /**
     * Sets a cookie.
     *
     * @param Cookie $cookie A Cookie instance
     *
     * @api
     */
    public function set(Cookie $cookie)
    {
        $this->cookieJar[$cookie->getName()] = $cookie;
    }

    /**
     * Gets a cookie by name.
     *
     * @param string $name The cookie name
     *
     * @return Cookie|null A Cookie instance or null if the cookie does not exist
     *
     * @api
     */
    public function get($name)
    {
        $this->flushExpiredCookies();

        return isset($this->cookieJar[$name]) ? $this->cookieJar[$name] : null;
    }

    /**
     * Removes a cookie by name.
     *
     * @param string $name The cookie name
     *
     * @api
     */
    public function expire($name)
    {
        unset($this->cookieJar[$name]);
    }

    /**
     * Removes all the cookies from the jar.
     *
     * @api
     */
    public function clear()
    {
        $this->cookieJar = array();
    }

    /**
     * Updates the cookie jar from a Response object.
     *
     * @param Response $response A Response object
     * @param string   $url    The base URL
     */
    public function updateFromResponse(Response $response, $uri = null)
    {
        foreach ($response->getHeader('Set-Cookie', false) as $cookie) {
            $this->set(Cookie::fromString($cookie), $uri);
        }
    }

    /**
     * Returns not yet expired cookies.
     *
     * @return array An array of cookies
     */
    public function all()
    {
        $this->flushExpiredCookies();

        return $this->cookieJar;
    }

    /**
     * Returns not yet expired cookie values for the given URI.
     *
     * @param string $uri A URI
     *
     * @return array An array of cookie values
     */
    public function allValues($uri)
    {
        $this->flushExpiredCookies();

        $parts = parse_url($uri);

        $cookies = array();
        foreach ($this->cookieJar as $cookie) {
            if ($cookie->getDomain() && $cookie->getDomain() != substr($parts['host'], -strlen($cookie->getDomain()))) {
                continue;
            }

            if ($cookie->getPath() != substr($parts['path'], 0, strlen($cookie->getPath()))) {
                continue;
            }

            if ($cookie->isSecure() && 'https' != $parts['scheme']) {
                continue;
            }

            $cookies[$cookie->getName()] = $cookie->getValue();
        }

        return $cookies;
    }

    /**
     * Removes all expired cookies.
     */
    public function flushExpiredCookies()
    {
        $cookies = $this->cookieJar;
        foreach ($cookies as $name => $cookie) {
            if ($cookie->isExpired()) {
                unset($this->cookieJar[$name]);
            }
        }
    }
}
