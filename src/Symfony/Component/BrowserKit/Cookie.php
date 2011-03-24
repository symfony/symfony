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
 * Cookie represents an HTTP cookie.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
class Cookie
{
    const DATE_FORMAT = 'D, d-M-Y H:i:s T';

    protected $name;
    protected $value;
    protected $expires;
    protected $path;
    protected $domain;
    protected $secure;
    protected $httponly;

    /**
     * Sets a cookie.
     *
     * @param  string  $name     The cookie name
     * @param  string  $value    The value of the cookie
     * @param  string  $expires  The time the cookie expires
     * @param  string  $path     The path on the server in which the cookie will be available on
     * @param  string  $domain   The domain that the cookie is available
     * @param  bool    $secure   Indicates that the cookie should only be transmitted over a secure HTTPS connection from the client
     * @param  bool    $httponly The cookie httponly flag
     *
     * @api
     */
    public function __construct($name, $value, $expires = null, $path = '/', $domain = '', $secure = false, $httponly = false)
    {
        $this->name     = $name;
        $this->value    = $value;
        $this->expires  = null === $expires ? null : (integer) $expires;
        $this->path     = empty($path) ? '/' : $path;
        $this->domain   = $domain;
        $this->secure   = (Boolean) $secure;
        $this->httponly = (Boolean) $httponly;
    }

    /**
     * Returns the HTTP representation of the Cookie.
     *
     * @return string The HTTP representation of the Cookie
     *
     * @api
     */
    public function __toString()
    {
        $cookie = sprintf('%s=%s', $this->name, urlencode($this->value));

        if (null !== $this->expires) {
            $cookie .= '; expires='.substr(\DateTime::createFromFormat('U', $this->expires, new \DateTimeZone('UTC'))->format(static::DATE_FORMAT), 0, -5);
        }

        if ('' !== $this->domain) {
            $cookie .= '; domain='.$this->domain;
        }

        if ('/' !== $this->path) {
            $cookie .= '; path='.$this->path;
        }

        if ($this->secure) {
            $cookie .= '; secure';
        }

        if ($this->httponly) {
            $cookie .= '; httponly';
        }

        return $cookie;
    }

    /**
     * Creates a Cookie instance from a Set-Cookie header value.
     *
     * @param string $cookie A Set-Cookie header value
     * @param string $url    The base URL
     *
     * @return Cookie A Cookie instance
     *
     * @api
     */
    static public function fromString($cookie, $url = null)
    {
        $parts = explode(';', $cookie);

        if (false === strpos($parts[0], '=')) {
            throw new \InvalidArgumentException('The cookie string "%s" is not valid.');
        }

        list($name, $value) = explode('=', array_shift($parts), 2);

        $values = array(
            'name'     => trim($name),
            'value'    => urldecode(trim($value)),
            'expires'  =>  null,
            'path'     => '/',
            'domain'   => '',
            'secure'   => false,
            'httponly' => false,
        );

        if (null !== $url) {
            if ((false === $parts = parse_url($url)) || !isset($parts['host']) || !isset($parts['path'])) {
                throw new \InvalidArgumentException(sprintf('The URL "%s" is not valid.', $url));
            }

            $values['domain'] = $parts['host'];
            $values['path'] = substr($parts['path'], 0, strrpos($parts['path'], '/'));
        }

        foreach ($parts as $part) {
            $part = trim($part);

            if ('secure' === strtolower($part)) {
                $values['secure'] = true;

                continue;
            }

            if ('httponly' === strtolower($part)) {
                $values['httponly'] = true;

                continue;
            }

            if (2 === count($elements = explode('=', $part, 2))) {
                if ('expires' === $elements[0]) {
                    if (false === $date = \DateTime::createFromFormat(static::DATE_FORMAT, $elements[1], new \DateTimeZone('UTC'))) {
                        throw new \InvalidArgumentException(sprintf('The expires part of cookie is not valid (%s).', $elements[1]));
                    }

                    $elements[1] = $date->getTimestamp();
                }

                $values[strtolower($elements[0])] = $elements[1];
            }
        }

        return new static(
            $values['name'],
            $values['value'],
            $values['expires'],
            $values['path'],
            $values['domain'],
            $values['secure'],
            $values['httponly']
        );
    }

    /**
     * Gets the name of the cookie.
     *
     * @return string The cookie name
     *
     * @api
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Gets the value of the cookie.
     *
     * @return string The cookie value
     *
     * @api
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Gets the expires time of the cookie.
     *
     * @return string The cookie expires time
     *
     * @api
     */
    public function getExpiresTime()
    {
        return $this->expires;
    }

    /**
     * Gets the path of the cookie.
     *
     * @return string The cookie path
     *
     * @api
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Gets the domain of the cookie.
     *
     * @return string The cookie domain
     *
     * @api
     */
    public function getDomain()
    {
        return $this->domain;
    }

    /**
     * Returns the secure flag of the cookie.
     *
     * @return Boolean The cookie secure flag
     *
     * @api
     */
    public function isSecure()
    {
        return $this->secure;
    }

    /**
     * Returns the httponly flag of the cookie.
     *
     * @return Boolean The cookie httponly flag
     *
     * @api
     */
    public function isHttpOnly()
    {
        return $this->httponly;
    }

    /**
     * Returns true if the cookie has expired.
     *
     * @return Boolean true if the cookie has expired, false otherwise
     *
     * @api
     */
    public function isExpired()
    {
        return (null !== $this->expires) && $this->expires < time();
    }
}
