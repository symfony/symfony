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
 * Cookie represents an HTTP cookie.
 *
 * @package    Symfony
 * @subpackage Components_BrowserKit
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Cookie
{
    protected $name;
    protected $value;
    protected $expire;
    protected $path;
    protected $domain;
    protected $secure;

    /**
     * Sets a cookie.
     *
     * @param  string  $name   The cookie name
     * @param  string  $value  The value of the cookie
     * @param  string  $expire The time the cookie expires
     * @param  string  $path   The path on the server in which the cookie will be available on
     * @param  string  $domain The domain that the cookie is available
     * @param  bool    $secure Indicates that the cookie should only be transmitted over a secure HTTPS connection from the client
     */
    public function __construct($name, $value, $expire = 0, $path = '/', $domain = '', $secure = false)
    {
        $this->name   = $name;
        $this->value  = $value;
        $this->expire = (integer) $expire;
        $this->path   = empty($path) ? '/' : $path;
        $this->domain = $domain;
        $this->secure = (Boolean) $secure;
    }

    /**
     * Gets the name of the cookie.
     *
     * @return string The cookie name
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Gets the value of the cookie.
     *
     * @return string The cookie value
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Gets the expire time of the cookie.
     *
     * @return string The cookie expire time
     */
    public function getExpireTime()
    {
        return $this->expire;
    }

    /**
     * Gets the path of the cookie.
     *
     * @return string The cookie path
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Gets the domain of the cookie.
     *
     * @return string The cookie domain
     */
    public function getDomain()
    {
        return $this->domain;
    }

    /**
     * Returns the secure flag of the cookie.
     *
     * @return Boolean The cookie secure flag
     */
    public function isSecure()
    {
        return $this->secure;
    }

    /**
     * Returns true if the cookie has expired.
     *
     * @return Boolean true if the cookie has expired, false otherwise
     */
    public function isExpired()
    {
        return $this->expire && $this->expire < time();
    }
}
