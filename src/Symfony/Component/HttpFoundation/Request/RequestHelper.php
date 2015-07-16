<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Request;

use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Request;

/**
 * Request helper with methods helpful for general request data.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Yosef Deray <yderay@gmail.com>
 */
class RequestHelper
{
    const HEADER_FORWARDED = 'forwarded';
    const HEADER_CLIENT_IP = 'client_ip';
    const HEADER_CLIENT_HOST = 'client_host';
    const HEADER_CLIENT_PROTO = 'client_proto';
    const HEADER_CLIENT_PORT = 'client_port';

    protected $httpMethodParameterOverride = false;

    /**
     * @var string[]
     */
    protected $trustedProxies = array();

    /**
     * @var string[]
     */
    protected $trustedHostPatterns = array();

    /**
     * @var string[]
     */
    protected $trustedHosts = array();

    /**
     * Names for headers that can be trusted when
     * using trusted proxies.
     *
     * The FORWARDED header is the standard as of rfc7239.
     *
     * The other headers are non-standard, but widely used
     * by popular reverse proxies (like Apache mod_proxy or Amazon EC2).
     */
    protected $trustedHeaders = array(
        self::HEADER_FORWARDED => 'FORWARDED',
        self::HEADER_CLIENT_IP => 'X_FORWARDED_FOR',
        self::HEADER_CLIENT_HOST => 'X_FORWARDED_HOST',
        self::HEADER_CLIENT_PROTO => 'X_FORWARDED_PROTO',
        self::HEADER_CLIENT_PORT => 'X_FORWARDED_PORT',
    );

    /**
     * Enables support for the _method request parameter to determine the intended HTTP method.
     *
     * Be warned that enabling this feature might lead to CSRF issues in your code.
     * Check that you are using CSRF tokens when required.
     * If the HTTP method parameter override is enabled, an html-form with method "POST" can be altered
     * and used to send a "PUT" or "DELETE" request via the _method request parameter.
     * If these methods are not protected against CSRF, this presents a possible vulnerability.
     *
     * The HTTP method can only be overridden when the real HTTP method is POST.
     */
    public function enableHttpMethodParameterOverride()
    {
        $this->httpMethodParameterOverride = true;

        return $this;
    }

    /**
     * Checks whether support for the _method request parameter is enabled.
     *
     * @return bool True when the _method request parameter is enabled, false otherwise
     */
    public function getHttpMethodParameterOverride()
    {
        return $this->httpMethodParameterOverride;
    }

    /**
     * Sets a list of trusted proxies.
     *
     * You should only list the reverse proxies that you manage directly.
     *
     * @param array $proxies A list of trusted proxies
     *
     * @return $this
     */
    public function setTrustedProxies(array $proxies)
    {
        $this->trustedProxies = $proxies;

        return $this;
    }

    /**
     * Gets the list of trusted proxies.
     *
     * @return array An array of trusted proxies.
     */
    public function getTrustedProxies()
    {
        return $this->trustedProxies;
    }

    /**
     * Sets a list of trusted host patterns.
     *
     * You should only list the hosts you manage using regexs.
     *
     * @param array $hostPatterns A list of trusted host patterns
     *
     * @return $this
     */
    public function setTrustedHosts(array $hostPatterns)
    {
        $this->trustedHostPatterns = array_map(function ($hostPattern) {
            return sprintf('#%s#i', $hostPattern);
        }, $hostPatterns);
        // we need to reset trusted hosts on trusted host patterns change
        $this->trustedHosts = array();

        return $this;
    }

    /**
     * Gets the list of trusted host patterns.
     *
     * @return array An array of trusted host patterns.
     */
    public function getTrustedHosts()
    {
        return $this->trustedHostPatterns;
    }

    /**
     * Sets the name for trusted headers.
     *
     * The following header keys are supported:
     *
     *  * Request::HEADER_CLIENT_IP:    defaults to X-Forwarded-For   (see getClientIp())
     *  * Request::HEADER_CLIENT_HOST:  defaults to X-Forwarded-Host  (see getHost())
     *  * Request::HEADER_CLIENT_PORT:  defaults to X-Forwarded-Port  (see getPort())
     *  * Request::HEADER_CLIENT_PROTO: defaults to X-Forwarded-Proto (see getScheme() and isSecure())
     *
     * Setting an empty value allows to disable the trusted header for the given key.
     *
     * @param string $key   The header key
     * @param string $value The header name
     *
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function setTrustedHeaderName($key, $value)
    {
        if (!array_key_exists($key, $this->trustedHeaders)) {
            throw new \InvalidArgumentException(sprintf('Unable to set the trusted header name for key "%s".', $key));
        }

        $this->trustedHeaders[$key] = $value;

        return $this;
    }

    /**
     * Gets the trusted proxy header name.
     *
     * @param string $key The header key
     *
     * @return string The header name
     *
     * @throws \InvalidArgumentException
     */
    public function getTrustedHeaderName($key)
    {
        if (!array_key_exists($key, $this->trustedHeaders)) {
            throw new \InvalidArgumentException(sprintf('Unable to get the trusted header name for key "%s".', $key));
        }

        return $this->trustedHeaders[$key];
    }

    /**
     * Returns the client IP addresses.
     *
     * In the returned array the most trusted IP address is first, and the
     * least trusted one last. The "real" client IP address is the last one,
     * but this is also the least trusted one. Trusted proxies are stripped.
     *
     * Use this method carefully; you should use getClientIp() instead.
     *
     * @param Request $request
     *
     * @return array The client IP addresses
     *
     * @see getClientIp()
     */
    public function getClientIps(Request $request)
    {
        $clientIps = array();
        $ip = $request->server->get('REMOTE_ADDR');

        if (!$this->isFromTrustedProxy($request)) {
            return array($ip);
        }

        if ($this->trustedHeaders[self::HEADER_FORWARDED] && $request->headers->has($this->trustedHeaders[self::HEADER_FORWARDED])) {
            $forwardedHeader = $request->headers->get($this->trustedHeaders[self::HEADER_FORWARDED]);
            preg_match_all('{(for)=("?\[?)([a-z0-9\.:_\-/]*)}', $forwardedHeader, $matches);
            $clientIps = $matches[3];
        } elseif ($this->trustedHeaders[self::HEADER_CLIENT_IP] && $request->headers->has($this->trustedHeaders[self::HEADER_CLIENT_IP])) {
            $clientIps = array_map('trim', explode(',', $request->headers->get($this->trustedHeaders[self::HEADER_CLIENT_IP])));
        }

        $clientIps[] = $ip; // Complete the IP chain with the IP the request actually came from
        $ip = $clientIps[0]; // Fallback to this when the client IP falls into the range of trusted proxies

        foreach ($clientIps as $key => $clientIp) {
            // Remove port (unfortunately, it does happen)
            if (preg_match('{((?:\d+\.){3}\d+)\:\d+}', $clientIp, $match)) {
                $clientIps[$key] = $clientIp = $match[1];
            }

            if (IpUtils::checkIp($clientIp, $this->trustedProxies)) {
                unset($clientIps[$key]);
            }
        }

        // Now the IP chain contains only untrusted proxies and the client IP
        return $clientIps ? array_reverse($clientIps) : array($ip);
    }

    /**
     * Returns the client IP address.
     *
     * This method can read the client IP address from the "X-Forwarded-For" header
     * when trusted proxies were set via "setTrustedProxies()". The "X-Forwarded-For"
     * header value is a comma+space separated list of IP addresses, the left-most
     * being the original client, and each successive proxy that passed the request
     * adding the IP address where it received the request from.
     *
     * If your reverse proxy uses a different header name than "X-Forwarded-For",
     * ("Client-Ip" for instance), configure it via "setTrustedHeaderName()" with
     * the "client-ip" key.
     *
     * @param Request $request
     *
     * @return string The client IP address
     *
     * @see getClientIps()
     * @see http://en.wikipedia.org/wiki/X-Forwarded-For
     */
    public function getClientIp(Request $request)
    {
        $ipAddresses = $this->getClientIps($request);

        return $ipAddresses[0];
    }

    /**
     * Returns current script name.
     *
     * @param Request $request
     *
     * @return string
     */
    public function getScriptName(Request $request)
    {
        return $request->server->get('SCRIPT_NAME', $request->server->get('ORIG_SCRIPT_NAME', ''));
    }

    /**
     * Checks whether the request is secure or not.
     *
     * This method can read the client port from the "X-Forwarded-Proto" header
     * when trusted proxies were set via "setTrustedProxies()".
     *
     * The "X-Forwarded-Proto" header must contain the protocol: "https" or "http".
     *
     * If your reverse proxy uses a different header name than "X-Forwarded-Proto"
     * ("SSL_HTTPS" for instance), configure it via "setTrustedHeaderName()" with
     * the "client-proto" key.
     *
     * @param Request $request
     *
     * @return bool
     */
    public function isSecure(Request $request)
    {
        if ($this->isFromTrustedProxy($request) && $this->trustedHeaders[self::HEADER_CLIENT_PROTO] && $proto = $request->headers->get($this->trustedHeaders[self::HEADER_CLIENT_PROTO])) {
            return in_array(strtolower(current(explode(',', $proto))), array('https', 'on', 'ssl', '1'));
        }

        $https = $request->server->get('HTTPS');

        return !empty($https) && 'off' !== strtolower($https);
    }

    /**
     * Returns the host name.
     *
     * This method can read the client port from the "X-Forwarded-Host" header
     * when trusted proxies were set via "setTrustedProxies()".
     *
     * The "X-Forwarded-Host" header must contain the client host name.
     *
     * If your reverse proxy uses a different header name than "X-Forwarded-Host",
     * configure it via "setTrustedHeaderName()" with the "client-host" key.
     *
     * @param Request $request
     *
     * @return string
     */
    public function getHost(Request $request)
    {
        if ($this->isFromTrustedProxy($request) && $this->trustedHeaders[self::HEADER_CLIENT_HOST] && $host = $request->headers->get($this->trustedHeaders[self::HEADER_CLIENT_HOST])) {
            $elements = explode(',', $host);

            $host = $elements[count($elements) - 1];
        } elseif (!$host = $request->headers->get('HOST')) {
            if (!$host = $request->server->get('SERVER_NAME')) {
                $host = $request->server->get('SERVER_ADDR', '');
            }
        }

        // trim and remove port number from host
        // host is lowercase as per RFC 952/2181
        $host = strtolower(preg_replace('/:\d+$/', '', trim($host)));

        // as the host can come from the user (HTTP_HOST and depending on the configuration, SERVER_NAME too can come from the user)
        // check that it does not contain forbidden characters (see RFC 952 and RFC 2181)
        // use preg_replace() instead of preg_match() to prevent DoS attacks with long host names
        if ($host && '' !== preg_replace('/(?:^\[)?[a-zA-Z0-9-:\]_]+\.?/', '', $host)) {
            throw new \UnexpectedValueException(sprintf('Invalid Host "%s"', $host));
        }

        if (count($this->trustedHostPatterns) > 0) {
            // to avoid host header injection attacks, you should provide a list of trusted host patterns

            if (in_array($host, $this->trustedHosts)) {
                return $host;
            }

            foreach ($this->trustedHostPatterns as $pattern) {
                if (preg_match($pattern, $host)) {
                    $this->trustedHosts[] = $host;

                    return $host;
                }
            }

            throw new \UnexpectedValueException(sprintf('Untrusted Host "%s"', $host));
        }

        return $host;
    }

    /**
     * Returns the port on which the request is made.
     *
     * This method can read the client port from the "X-Forwarded-Port" header
     * when trusted proxies were set via "setTrustedProxies()".
     *
     * The "X-Forwarded-Port" header must contain the client port.
     *
     * If your reverse proxy uses a different header name than "X-Forwarded-Port",
     * configure it via "setTrustedHeaderName()" with the "client-port" key.
     *
     * @param Request $request
     *
     * @return string
     */
    public function getPort(Request $request)
    {
        if ($this->isFromTrustedProxy($request)) {
            if ($this->trustedHeaders[self::HEADER_CLIENT_PORT] && $port = $request->headers->get($this->trustedHeaders[self::HEADER_CLIENT_PORT])) {
                return $port;
            }

            if ($this->trustedHeaders[self::HEADER_CLIENT_PROTO] && 'https' === $request->headers->get($this->trustedHeaders[self::HEADER_CLIENT_PROTO], 'http')) {
                return 443;
            }
        }

        if ($host = $request->headers->get('HOST')) {
            if ($host[0] === '[') {
                $pos = strpos($host, ':', strrpos($host, ']'));
            } else {
                $pos = strrpos($host, ':');
            }

            if (false !== $pos) {
                return (int) substr($host, $pos + 1);
            }

            return $this->isSecure($request) ? 443 : 80;
        }

        return $request->server->get('SERVER_PORT');
    }

    /**
     * Returns the user.
     *
     * @param Request $request
     *
     * @return null|string
     */
    public function getUser(Request $request)
    {
        return $request->headers->get('PHP_AUTH_USER');
    }

    /**
     * Returns the password.
     *
     * @param Request $request
     *
     * @return null|string
     */
    public function getPassword(Request $request)
    {
        return $request->headers->get('PHP_AUTH_PW');
    }

    /**
     * Gets the user info.
     *
     * @param Request $request
     *
     * @return string A user name and, optionally, scheme-specific information about how to gain authorization to access the server
     */
    public function getUserInfo(Request $request)
    {
        $userinfo = $this->getUser($request);

        $pass = $this->getPassword($request);
        if ('' != $pass) {
            $userinfo .= ":$pass";
        }

        return $userinfo;
    }

    /**
     * Sets the request method.
     *
     * @param Request $request
     * @param string  $method
     */
    public function setMethod(Request $request, $method)
    {
        $request->server->set('REQUEST_METHOD', $method);
    }

    /**
     * Gets the request "intended" method.
     *
     * If the X-HTTP-Method-Override header is set, and if the method is a POST,
     * then it is used to determine the "real" intended HTTP method.
     *
     * The _method request parameter can also be used to determine the HTTP method,
     * but only if enableHttpMethodParameterOverride() has been called.
     *
     * The method is always an uppercased string.
     *
     * @param Request $request
     *
     * @return string The request method
     *
     * @see getRealMethod()
     */
    public function getMethod(Request $request)
    {
        $method = strtoupper($request->server->get('REQUEST_METHOD', 'GET'));

        if ('POST' === $method) {
            if ($tmpMethod = $request->headers->get('X-HTTP-METHOD-OVERRIDE')) {
                $method = strtoupper($tmpMethod);
            } elseif ($this->httpMethodParameterOverride) {
                $method = strtoupper($request->request->get('_method', $request->query->get('_method', 'POST')));
            }
        }

        return $method;
    }

    /**
     * Checks if the request method is of specified type.
     *
     * @param Request $request
     * @param string  $method  Uppercase request method (GET, POST etc).
     *
     * @return bool
     */
    public function isMethod(Request $request, $method)
    {
        return $this->getMethod($request) === strtoupper($method);
    }

    /**
     * Checks whether the method is safe or not.
     *
     * @param Request $request
     *
     * @return bool
     */
    public function isMethodSafe(Request $request)
    {
        return in_array($this->getMethod($request), array('GET', 'HEAD'));
    }

    /**
     * Gets the "real" request method.
     *
     * @param Request $request
     *
     * @return string The request method
     *
     * @see getMethod()
     */
    public function getRealMethod(Request $request)
    {
        return strtoupper($request->server->get('REQUEST_METHOD', 'GET'));
    }

    /**
     * Returns true if the request is a XMLHttpRequest.
     *
     * It works if your JavaScript library sets an X-Requested-With HTTP header.
     * It is known to work with common JavaScript frameworks:
     *
     * @link http://en.wikipedia.org/wiki/List_of_Ajax_frameworks#JavaScript
     *
     * @param Request $request
     *
     * @return bool true if the request is an XMLHttpRequest, false otherwise
     */
    public function isXmlHttpRequest(Request $request)
    {
        return 'XMLHttpRequest' == $request->headers->get('X-Requested-With');
    }

    private function isFromTrustedProxy(Request $request)
    {
        return $this->trustedProxies && IpUtils::checkIp($request->server->get('REMOTE_ADDR'), $this->trustedProxies);
    }
}