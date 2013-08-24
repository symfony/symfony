<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing;

use Symfony\Component\HttpFoundation\Request;

/**
 * Holds information about the current request.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 *
 * @since v2.0.0
 */
class RequestContext
{
    private $baseUrl;
    private $pathInfo;
    private $method;
    private $host;
    private $scheme;
    private $httpPort;
    private $httpsPort;
    private $queryString;

    /**
     * @var array
     */
    private $parameters = array();

    /**
     * Constructor.
     *
     * @param string  $baseUrl      The base URL
     * @param string  $method       The HTTP method
     * @param string  $host         The HTTP host name
     * @param string  $scheme       The HTTP scheme
     * @param integer $httpPort     The HTTP port
     * @param integer $httpsPort    The HTTPS port
     * @param string  $path         The path
     * @param string  $queryString  The query string
     *
     * @api
     *
     * @since v2.3.0
     */
    public function __construct($baseUrl = '', $method = 'GET', $host = 'localhost', $scheme = 'http', $httpPort = 80, $httpsPort = 443, $path = '/', $queryString = '')
    {
        $this->baseUrl = $baseUrl;
        $this->method = strtoupper($method);
        $this->host = $host;
        $this->scheme = strtolower($scheme);
        $this->httpPort = $httpPort;
        $this->httpsPort = $httpsPort;
        $this->pathInfo = $path;
        $this->queryString = $queryString;
    }

    /**
     * @since v2.1.0
     */
    public function fromRequest(Request $request)
    {
        $this->setBaseUrl($request->getBaseUrl());
        $this->setPathInfo($request->getPathInfo());
        $this->setMethod($request->getMethod());
        $this->setHost($request->getHost());
        $this->setScheme($request->getScheme());
        $this->setHttpPort($request->isSecure() ? $this->httpPort : $request->getPort());
        $this->setHttpsPort($request->isSecure() ? $request->getPort() : $this->httpsPort);
        $this->setQueryString($request->server->get('QUERY_STRING'));
    }

    /**
     * Gets the base URL.
     *
     * @return string The base URL
     *
     * @since v2.0.0
     */
    public function getBaseUrl()
    {
        return $this->baseUrl;
    }

    /**
     * Sets the base URL.
     *
     * @param string $baseUrl The base URL
     *
     * @api
     *
     * @since v2.0.0
     */
    public function setBaseUrl($baseUrl)
    {
        $this->baseUrl = $baseUrl;
    }

    /**
     * Gets the path info.
     *
     * @return string The path info
     *
     * @since v2.2.0
     */
    public function getPathInfo()
    {
        return $this->pathInfo;
    }

    /**
     * Sets the path info.
     *
     * @param string $pathInfo The path info
     *
     * @since v2.2.0
     */
    public function setPathInfo($pathInfo)
    {
        $this->pathInfo = $pathInfo;
    }

    /**
     * Gets the HTTP method.
     *
     * The method is always an uppercased string.
     *
     * @return string The HTTP method
     *
     * @since v2.0.0
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * Sets the HTTP method.
     *
     * @param string $method The HTTP method
     *
     * @api
     *
     * @since v2.0.0
     */
    public function setMethod($method)
    {
        $this->method = strtoupper($method);
    }

    /**
     * Gets the HTTP host.
     *
     * @return string The HTTP host
     *
     * @since v2.0.0
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * Sets the HTTP host.
     *
     * @param string $host The HTTP host
     *
     * @api
     *
     * @since v2.0.0
     */
    public function setHost($host)
    {
        $this->host = $host;
    }

    /**
     * Gets the HTTP scheme.
     *
     * @return string The HTTP scheme
     *
     * @since v2.0.0
     */
    public function getScheme()
    {
        return $this->scheme;
    }

    /**
     * Sets the HTTP scheme.
     *
     * @param string $scheme The HTTP scheme
     *
     * @api
     *
     * @since v2.0.0
     */
    public function setScheme($scheme)
    {
        $this->scheme = strtolower($scheme);
    }

    /**
     * Gets the HTTP port.
     *
     * @return string The HTTP port
     *
     * @since v2.0.0
     */
    public function getHttpPort()
    {
        return $this->httpPort;
    }

    /**
     * Sets the HTTP port.
     *
     * @param string $httpPort The HTTP port
     *
     * @api
     *
     * @since v2.0.0
     */
    public function setHttpPort($httpPort)
    {
        $this->httpPort = $httpPort;
    }

    /**
     * Gets the HTTPS port.
     *
     * @return string The HTTPS port
     *
     * @since v2.0.0
     */
    public function getHttpsPort()
    {
        return $this->httpsPort;
    }

    /**
     * Sets the HTTPS port.
     *
     * @param string $httpsPort The HTTPS port
     *
     * @api
     *
     * @since v2.0.0
     */
    public function setHttpsPort($httpsPort)
    {
        $this->httpsPort = $httpsPort;
    }

    /**
     * Gets the query string.
     *
     * @return string The query string
     *
     * @since v2.3.0
     */
    public function getQueryString()
    {
        return $this->queryString;
    }

    /**
     * Sets the query string.
     *
     * @param string $queryString The query string
     *
     * @api
     *
     * @since v2.3.0
     */
    public function setQueryString($queryString)
    {
        $this->queryString = $queryString;
    }

    /**
     * Returns the parameters.
     *
     * @return array The parameters
     *
     * @since v2.0.0
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * Sets the parameters.
     *
     * This method implements a fluent interface.
     *
     * @param array $parameters The parameters
     *
     * @return Route The current Route instance
     *
     * @since v2.0.0
     */
    public function setParameters(array $parameters)
    {
        $this->parameters = $parameters;

        return $this;
    }

    /**
     * Gets a parameter value.
     *
     * @param string $name A parameter name
     *
     * @return mixed The parameter value
     *
     * @since v2.0.0
     */
    public function getParameter($name)
    {
        return isset($this->parameters[$name]) ? $this->parameters[$name] : null;
    }

    /**
     * Checks if a parameter value is set for the given parameter.
     *
     * @param string $name A parameter name
     *
     * @return Boolean true if the parameter value is set, false otherwise
     *
     * @since v2.0.0
     */
    public function hasParameter($name)
    {
        return array_key_exists($name, $this->parameters);
    }

    /**
     * Sets a parameter value.
     *
     * @param string $name      A parameter name
     * @param mixed  $parameter The parameter value
     *
     * @api
     *
     * @since v2.0.0
     */
    public function setParameter($name, $parameter)
    {
        $this->parameters[$name] = $parameter;
    }
}
