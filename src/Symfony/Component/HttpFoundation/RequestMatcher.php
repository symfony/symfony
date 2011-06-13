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
    private $path;
    private $host;
    private $methods;
    private $ip;
    private $attributes;

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
     * @param string|array $method An HTTP method or an array of HTTP methods
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

        if (null !== $this->path) {
            if (null !== $session = $request->getSession()) {
                $path = strtr($this->path, array('{_locale}' => $session->getLocale(), '#' => '\\#'));
            } else {
                $path = str_replace('#', '\\#', $this->path);
            }

            if (!preg_match('#'.$path.'#', $request->getPathInfo())) {
                return false;
            }
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
        // IPv6 address
        if (false !== strpos($ip, ':')) {
            return $this->checkIp6($ip);
        } else {
            return $this->checkIp4($ip);
        }
    }

    protected function checkIp4($ip)
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

    /**
     * @author David Soria Parra <dsp at php dot net>
     * @see https://github.com/dsp/v6tools
     */
    protected function checkIp6($ip)
    {
        list($address, $netmask) = explode('/', $this->ip);

        $bytes_addr = unpack("n*", inet_pton($address));
        $bytes_test = unpack("n*", inet_pton($ip));

        for ($i = 1, $ceil = ceil($netmask / 16); $i <= $ceil; $i++) {
            $left = $netmask - 16 * ($i-1);
            $left = ($left <= 16) ?: 16;
            $mask = ~(0xffff >> $left) & 0xffff;
            if (($bytes_addr[$i] & $mask) != ($bytes_test[$i] & $mask)) {
                return false;
            }
        }

        return true;
    }
}
