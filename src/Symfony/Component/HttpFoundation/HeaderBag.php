<?php

namespace Symfony\Component\HttpFoundation;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * HeaderBag is a container for HTTP headers.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class HeaderBag
{
    protected $headers;
    protected $cacheControl;
    protected $type;

    /**
     * Constructor.
     *
     * @param array  $headers An array of HTTP headers
     * @param string $type       The type (null, request, or response)
     */
    public function __construct(array $headers = array(), $type = null)
    {
        $this->replace($headers);

        if (null !== $type && !in_array($type, array('request', 'response'))) {
            throw new \InvalidArgumentException(sprintf('The "%s" type is not supported by the HeaderBag constructor.', $type));
        }
        $this->type = $type;
    }

    /**
     * Returns the headers.
     *
     * @return array An array of headers
     */
    public function all()
    {
        return $this->headers;
    }

    /**
     * Returns the parameter keys.
     *
     * @return array An array of parameter keys
     */
    public function keys()
    {
        return array_keys($this->headers);
    }

    /**
     * Replaces the current HTTP headers by a new set.
     *
     * @param array  $headers An array of HTTP headers
     */
    public function replace(array $headers = array())
    {
        $this->cacheControl = null;
        $this->headers = array();
        foreach ($headers as $key => $values) {
            $this->set($key, $values);
        }
    }

    /**
     * Returns a header value by name.
     *
     * @param string  $key   The header name
     * @param Boolean $first Whether to return the first value or all header values
     *
     * @return string|array The first header value if $first is true, an array of values otherwise
     */
    public function get($key, $first = true)
    {
        $key = strtr(strtolower($key), '_', '-');

        if (!array_key_exists($key, $this->headers)) {
            return $first ? null : array();
        }

        if ($first) {
            return count($this->headers[$key]) ? $this->headers[$key][0] : '';
        } else {
            return $this->headers[$key];
        }
    }

    /**
     * Sets a header by name.
     *
     * @param string       $key     The key
     * @param string|array $values  The value or an array of values
     * @param Boolean      $replace Whether to replace the actual value of not (true by default)
     */
    public function set($key, $values, $replace = true)
    {
        $key = strtr(strtolower($key), '_', '-');

        if (!is_array($values)) {
            $values = array($values);
        }

        if (true === $replace || !isset($this->headers[$key])) {
            $this->headers[$key] = $values;
        } else {
            $this->headers[$key] = array_merge($this->headers[$key], $values);
        }
    }

    /**
     * Returns true if the HTTP header is defined.
     *
     * @param string $key The HTTP header
     *
     * @return Boolean true if the parameter exists, false otherwise
     */
    public function has($key)
    {
        return array_key_exists(strtr(strtolower($key), '_', '-'), $this->headers);
    }

    /**
     * Returns true if the given HTTP header contains the given value.
     *
     * @param string $key   The HTTP header name
     * @param string $value The HTTP value
     *
     * @return Boolean true if the value is contained in the header, false otherwise
     */
    public function contains($key, $value)
    {
        return in_array($value, $this->get($key, false));
    }

    /**
     * Deletes a header.
     *
     * @param string $key The HTTP header name
     */
    public function delete($key)
    {
        unset($this->headers[strtr(strtolower($key), '_', '-')]);
    }

    /**
     * Returns an instance able to manage the Cache-Control header.
     *
     * @return CacheControl A CacheControl instance
     */
    public function getCacheControl()
    {
        if (null === $this->cacheControl) {
            $this->cacheControl = new CacheControl($this, $this->get('Cache-Control'), $this->type);
        }

        return $this->cacheControl;
    }

    /**
     * Sets a cookie.
     *
     * @param  string $name     The cookie name
     * @param  string $value    The value of the cookie
     * @param  string $domain   The domain that the cookie is available
     * @param  string $expire   The time the cookie expires
     * @param  string $path     The path on the server in which the cookie will be available on
     * @param  bool   $secure   Indicates that the cookie should only be transmitted over a secure HTTPS connection from the client
     * @param  bool   $httponly When TRUE the cookie will not be made accessible to JavaScript, preventing XSS attacks from stealing cookies
     *
     * @throws \InvalidArgumentException When the cookie expire parameter is not valid
     */
    public function setCookie($name, $value, $domain = null, $expires = null, $path = '/', $secure = false, $httponly = true)
    {
        // from PHP source code
        if (preg_match("/[=,; \t\r\n\013\014]/", $name)) {
            throw new \InvalidArgumentException(sprintf('The cookie name "%s" contains invalid characters.', $name));
        }

        if (preg_match("/[,; \t\r\n\013\014]/", $value)) {
            throw new \InvalidArgumentException(sprintf('The cookie value "%s" contains invalid characters.', $name));
        }

        if (!$name) {
            throw new \InvalidArgumentException('The cookie name cannot be empty');
        }

        $cookie = sprintf('%s=%s', $name, urlencode($value));

        if ('request' === $this->type) {
            return $this->set('Cookie', $cookie);
        }

        if (null !== $expires) {
            if (is_numeric($expires)) {
                $expires = (int) $expires;
            } elseif ($expires instanceof \DateTime) {
                $expires = $expires->getTimestamp();
            } else {
                $expires = strtotime($expires);
                if (false === $expires || -1 == $expires) {
                    throw new \InvalidArgumentException(sprintf('The "expires" cookie parameter is not valid.', $expires));
                }
            }

            $cookie .= '; expires='.substr(\DateTime::createFromFormat('U', $expires, new \DateTimeZone('UTC'))->format('D, d-M-Y H:i:s T'), 0, -5);
        }

        if ($domain) {
            $cookie .= '; domain='.$domain;
        }

        if ('/' !== $path) {
            $cookie .= '; path='.$path;
        }

        if ($secure) {
            $cookie .= '; secure';
        }

        if ($httponly) {
            $cookie .= '; httponly';
        }

        $this->set('Set-Cookie', $cookie, false);
    }

    /**
     * Returns the HTTP header value converted to a date.
     *
     * @param string    $key     The parameter key
     * @param \DateTime $default The default value
     *
     * @return \DateTime The filtered value
     */
    public function getDate($key, \DateTime $default = null)
    {
        if (null === $value = $this->get($key)) {
            return $default;
        }

        if (false === $date = \DateTime::createFromFormat(DATE_RFC2822, $value)) {
            throw new \RuntimeException(sprintf('The %s HTTP header is not parseable (%s).', $key, $value));
        }

        return $date;
    }

    /**
     * Normalizes a HTTP header name.
     *
     * @param  string $key The HTTP header name
     *
     * @return string The normalized HTTP header name
     */
    static public function normalizeHeaderName($key)
    {
        return strtr(strtolower($key), '_', '-');
    }
}
