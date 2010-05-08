<?php

namespace Symfony\Components\BrowserKit;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * CookieJar.
 *
 * @package    Symfony
 * @subpackage Components_BrowserKit
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class CookieJar
{
    protected $cookieJar = array();

    /**
     * Sets a cookie.
     *
     * @param Symfony\Components\BrowserKit\Cookie $cookie A Cookie instance
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
     * @return Symfony\Components\BrowserKit\Cookie|null A Cookie instance or null if the cookie does not exist
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
     */
    public function expire($name)
    {
        unset($this->cookieJar[$name]);
    }

    /**
     * Removes all the cookies from the jar.
     */
    public function clear()
    {
        $this->cookieJar = array();
    }

    /**
     * Updates the cookie jar from a Response object.
     *
     * @param Symfony\Components\BrowserKit\Response $response A Response object
     */
    public function updateFromResponse(Response $response)
    {
        foreach ($response->getCookies() as $name => $cookie) {
            $this->set(new Cookie(
                $name,
                isset($cookie['value']) ? $cookie['value'] : '',
                isset($cookie['expire']) ? $cookie['expire'] : 0,
                isset($cookie['path']) ? $cookie['path'] : '/',
                isset($cookie['domain']) ? $cookie['domain'] : '',
                isset($cookie['secure']) ? $cookie['secure'] : false
            ));
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
    public function getValues($uri)
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
