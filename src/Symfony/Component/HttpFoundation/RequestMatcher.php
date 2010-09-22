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
 * RequestMatcher compares a pre-defined set of checks against a Request instance.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class RequestMatcher implements RequestMatcherInterface
{
    protected $path;
    protected $host;
    protected $methods;
    protected $ip;

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
        $this->methods = array_map(function ($m) { return strtolower($m); }, is_array($method) ? $method : array($method));
    }

    /**
     * {@inheritdoc}
     */
    public function matches(Request $request)
    {
        if (null !== $this->methods && !in_array(strtolower($request->getMethod()), $this->methods)) {
            return false;
        }

        if (null !== $this->path && !preg_match($this->path, $request->getPathInfo())) {
            return false;
        }

        if (null !== $this->host && !preg_match($this->host, $request->getHost())) {
            return false;
        }

        if (null !== $this->ip && !$this->checkIp($this->host, $request->getClientIp())) {
            return false;
        }

        return true;
    }

    protected function checkIp($ip)
    {
        if (false !== strpos($this->ip, '/')) {
            list($address, $netmask) = $this->ip;

            if ($netmask <= 0) {
                return false;
            }
        } else {
            $address = $this->ip;
            $netmask = 1;
        }

        return 0 === substr_compare(sprintf('%032b', ip2long($ip)), sprintf('%032b', ip2long($address)), 0, $netmask);
    }
}
