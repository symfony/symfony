<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation;

/**
 * RequestMatcher compares a pre-defined set of checks against a Request instance.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RequestMatcher implements RequestMatcherInterface
{
    protected $path;
    protected $host;
    protected $methods;
    protected $ip;
    protected $attributes;

    public function __construct($path = null, $host = null, $methods = null, $ip = null, array $attributes = array())
    {
        $this->path = $path;
        $this->host = $host;
        $this->methods = $methods;
        $this->ip = $ip;
        $this->attributes = $attributes;
    }

    /**
     * Adds a check for the URL host name.
     *
     * @param string $regexp A Regexp
     */
    public function matchHost($regexp)
    {
        $this->host = $regexp;
    }

    /**
     * Adds a check for the URL path info.
     *
     * @param string $regexp A Regexp
     */
    public function matchPath($regexp)
    {
        $this->path = $regexp;
    }

    /**
     * Adds a check for the client IP.
     *
     * @param string $ip A specific IP address or a range specified using IP/netmask like 192.168.1.0/24
     */
    public function matchIp($ip)
    {
        $this->ip = $ip;
    }

    /**
     * Adds a check for the HTTP method.
     *
     * @param string|array An HTTP method or an array of HTTP methods
     */
    public function matchMethod($method)
    {
        $this->methods = array_map(
            function ($m)
            {
                return strtolower($m);
            },
            is_array($method) ? $method : array($method)
        );
    }

    /**
     * Adds a check for request attribute.
     *
     * @param string $key    The request attribute name
     * @param string $regexp A Regexp
     */
    public function matchAttribute($key, $regexp)
    {
        $this->attributes[$key] = $regexp;
    }

    /**
     * {@inheritdoc}
     */
    public function matches(Request $request)
    {
        if (null !== $this->methods && !in_array(strtolower($request->getMethod()), $this->methods)) {
            return false;
        }

        foreach ($this->attributes as $key => $pattern) {
            if (!preg_match('#'.str_replace('#', '\\#', $pattern).'#', $request->attributes->get($key))) {
                return false;
            }
        }

        if (null !== $this->path && !preg_match('#'.str_replace('#', '\\#', $this->path).'#', $request->getPathInfo())) {
            return false;
        }

        if (null !== $this->host && !preg_match('#'.str_replace('#', '\\#', $this->host).'#', $request->getHost())) {
            return false;
        }

        if (null !== $this->ip && !$this->checkIp($request->getClientIp())) {
            return false;
        }

        return true;
    }

    protected function checkIp($ip)
    {
        if (false !== strpos($this->ip, '/')) {
            list($address, $netmask) = explode('/', $this->ip);

            if ($netmask < 1 || $netmask > 32) {
                return false;
            }
        } else {
            $address = $this->ip;
            $netmask = 32;
        }

        return 0 === substr_compare(sprintf('%032b', ip2long($ip)), sprintf('%032b', ip2long($address)), 0, $netmask);
    }
}
