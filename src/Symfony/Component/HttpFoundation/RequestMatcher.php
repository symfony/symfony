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
    /**
     * @var string|null
     */
    private $path;

    /**
     * @var string|null
     */
    private $host;

    /**
     * @var string[]
     */
    private $methods = array();

    /**
     * @var string[]
     */
    private $ips = array();

    /**
     * @var array
     */
    private $attributes = array();

    /**
     * @var string[]
     */
    private $schemes = array();

    /**
     * @param string|null          $path
     * @param string|null          $host
     * @param string|string[]|null $methods
     * @param string|string[]|null $ips
     * @param array                $attributes
     * @param string|string[]|null $schemes
     */
    public function __construct($path = null, $host = null, $methods = null, $ips = null, array $attributes = array(), $schemes = null)
    {
        $this->matchPath($path);
        $this->matchHost($host);
        $this->matchMethod($methods);
        $this->matchIps($ips);
        $this->matchScheme($schemes);

        foreach ($attributes as $k => $v) {
            $this->matchAttribute($k, $v);
        }
    }

    /**
     * Adds a check for the HTTP scheme.
     *
     * @param string|string[]|null $scheme An HTTP scheme or an array of HTTP schemes
     */
    public function matchScheme($scheme)
    {
        $this->schemes = null !== $scheme ? array_map('strtolower', (array) $scheme) : array();
    }

    /**
     * Adds a check for the URL host name.
     *
     * @param string|null $regexp A Regexp
     */
    public function matchHost($regexp)
    {
        $this->host = $regexp;
    }

    /**
     * Adds a check for the URL path info.
     *
     * @param string|null $regexp A Regexp
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
        $this->matchIps($ip);
    }

    /**
     * Adds a check for the client IP.
     *
     * @param string|string[]|null $ips A specific IP address or a range specified using IP/netmask like 192.168.1.0/24
     */
    public function matchIps($ips)
    {
        $this->ips = null !== $ips ? (array) $ips : array();
    }

    /**
     * Adds a check for the HTTP method.
     *
     * @param string|string[]|null $method An HTTP method or an array of HTTP methods
     */
    public function matchMethod($method)
    {
        $this->methods = null !== $method ? array_map('strtoupper', (array) $method) : array();
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
        if ($this->schemes && !in_array($request->getScheme(), $this->schemes, true)) {
            return false;
        }

        if ($this->methods && !in_array($request->getMethod(), $this->methods, true)) {
            return false;
        }

        foreach ($this->attributes as $key => $pattern) {
            if (!preg_match('{'.$pattern.'}', $request->attributes->get($key))) {
                return false;
            }
        }

        if (null !== $this->path && !preg_match('{'.$this->path.'}', rawurldecode($request->getPathInfo()))) {
            return false;
        }

        if (null !== $this->host && !preg_match('{'.$this->host.'}i', $request->getHost())) {
            return false;
        }

        if (IpUtils::checkIp($request->getClientIp(), $this->ips)) {
            return true;
        }

        // Note to future implementors: add additional checks above the
        // foreach above or else your check might not be run!
        return 0 === count($this->ips);
    }
}
