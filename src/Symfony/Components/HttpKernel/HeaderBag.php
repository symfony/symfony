<?php

namespace Symfony\Components\HttpKernel;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * HeaderBag is a ParameterBag optimized to hold header HTTP headers.
 *
 * @package    Symfony
 * @subpackage Components_HttpKernel
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class HeaderBag extends ParameterBag
{
    protected $cacheControl;
    protected $type;

    /**
     * Constructor.
     *
     * @param array  $parameters An array of HTTP headers
     * @param string $type       The type (null, request, or response)
     */
    public function __construct(array $parameters = array(), $type = null)
    {
        $this->replace($parameters);

        if (null !== $type && !in_array($type, array('request', 'response'))) {
            throw new \InvalidArgumentException(sprintf('The "%s" type is not supported by the HeaderBag constructor.', $type));
        }
        $this->type = $type;
    }

    /**
     * Replaces the current HTTP headers by a new set.
     *
     * @param array  $parameters An array of HTTP headers
     */
    public function replace(array $parameters = array())
    {
        $this->cacheControl = null;
        $this->parameters = array();
        foreach ($parameters as $key => $value) {
            $this->parameters[strtr(strtolower($key), '_', '-')] = $value;
        }
    }

    /**
     * Returns a header value by name.
     *
     * @param string $key     The key
     * @param mixed  $default The default value
     */
    public function get($key, $default = null)
    {
        $key = strtr(strtolower($key), '_', '-');

        return array_key_exists($key, $this->parameters) ? $this->parameters[$key] : $default;
    }

    /**
     * Sets a header by name.
     *
     * @param string  $key     The key
     * @param mixed   $value   The value
     * @param Boolean $replace Whether to replace the actual value of not (true by default)
     */
    public function set($key, $value, $replace = true)
    {
        $key = strtr(strtolower($key), '_', '-');

        if (false === $replace) {
            $current = $this->get($key, '');
            $value = ($current ? $current.', ' : '').$value;
        }

        $this->parameters[$key] = $value;
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
        return array_key_exists(strtr(strtolower($key), '_', '-'), $this->parameters);
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
        return in_array($value, explode(', ', $this->get($key, '')));
    }

    /**
     * Deletes a header.
     *
     * @param string $key The HTTP header name
     */
    public function delete($key)
    {
        unset($this->parameters[strtr(strtolower($key), '_', '-')]);
    }

    /**
     * Returns an instance able to manage the Cache-Control header.
     *
     * @return Symfony\Components\HttpKernel\Cache\CacheControl A CacheControl instance
     */
    public function getCacheControl()
    {
        if (null === $this->cacheControl) {
            $this->cacheControl = new CacheControl($this, $this->get('Cache-Control'), $this->type);
        }

        return $this->cacheControl;
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
