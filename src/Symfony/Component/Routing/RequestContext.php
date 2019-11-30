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
 * This class implements a fluent interface.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Tobias Schultze <http://tobion.de>
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
    private $parameters = [];

    public function __construct(string $baseUrl = '', string $method = 'GET', string $host = 'localhost', string $scheme = 'http', int $httpPort = 80, int $httpsPort = 443, string $path = '/', string $queryString = '')
    {
        $this->setBaseUrl($baseUrl);
        $this->setMethod($method);
        $this->setHost($host);
        $this->setScheme($scheme);
        $this->setHttpPort($httpPort);
        $this->setHttpsPort($httpsPort);
        $this->setPathInfo($path);
        $this->setQueryString($queryString);
    }

    /**
     * Updates the RequestContext information based on a HttpFoundation Request.
     *
     * @return $this
     */
    public function fromRequest(Request $request)
    {
        $this->setBaseUrl($request->getBaseUrl());
        $this->setPathInfo($request->getPathInfo());
        $this->setMethod($request->getMethod());
        $this->setHost($request->getHost());
        $this->setScheme($request->getScheme());
        $this->setHttpPort($request->isSecure() || null === $request->getPort() ? $this->httpPort : $request->getPort());
        $this->setHttpsPort($request->isSecure() && null !== $request->getPort() ? $request->getPort() : $this->httpsPort);
        $this->setQueryString($request->server->get('QUERY_STRING', ''));

        return $this;
    }

    /**
     * Gets the base URL.
     *
     * @return string The base URL
     */
    public function getBaseUrl()
    {
        return $this->baseUrl;
    }

    /**
     * Sets the base URL.
     *
     * @return $this
     */
    public function setBaseUrl(string $baseUrl)
    {
        $this->baseUrl = $baseUrl;

        return $this;
    }

    /**
     * Gets the path info.
     *
     * @return string The path info
     */
    public function getPathInfo()
    {
        return $this->pathInfo;
    }

    /**
     * Sets the path info.
     *
     * @return $this
     */
    public function setPathInfo(string $pathInfo)
    {
        $this->pathInfo = $pathInfo;

        return $this;
    }

    /**
     * Gets the HTTP method.
     *
     * The method is always an uppercased string.
     *
     * @return string The HTTP method
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * Sets the HTTP method.
     *
     * @return $this
     */
    public function setMethod(string $method)
    {
        $this->method = strtoupper($method);

        return $this;
    }

    /**
     * Gets the HTTP host.
     *
     * The host is always lowercased because it must be treated case-insensitive.
     *
     * @return string The HTTP host
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * Sets the HTTP host.
     *
     * @return $this
     */
    public function setHost(string $host)
    {
        $this->host = strtolower($host);

        return $this;
    }

    /**
     * Gets the HTTP scheme.
     *
     * @return string The HTTP scheme
     */
    public function getScheme()
    {
        return $this->scheme;
    }

    /**
     * Sets the HTTP scheme.
     *
     * @return $this
     */
    public function setScheme(string $scheme)
    {
        $this->scheme = strtolower($scheme);

        return $this;
    }

    /**
     * Gets the HTTP port.
     *
     * @return int The HTTP port
     */
    public function getHttpPort()
    {
        return $this->httpPort;
    }

    /**
     * Sets the HTTP port.
     *
     * @return $this
     */
    public function setHttpPort(int $httpPort)
    {
        $this->httpPort = $httpPort;

        return $this;
    }

    /**
     * Gets the HTTPS port.
     *
     * @return int The HTTPS port
     */
    public function getHttpsPort()
    {
        return $this->httpsPort;
    }

    /**
     * Sets the HTTPS port.
     *
     * @return $this
     */
    public function setHttpsPort(int $httpsPort)
    {
        $this->httpsPort = $httpsPort;

        return $this;
    }

    /**
     * Gets the query string.
     *
     * @return string The query string without the "?"
     */
    public function getQueryString()
    {
        return $this->queryString;
    }

    /**
     * Sets the query string.
     *
     * @return $this
     */
    public function setQueryString(?string $queryString)
    {
        // string cast to be fault-tolerant, accepting null
        $this->queryString = (string) $queryString;

        return $this;
    }

    /**
     * Returns the parameters.
     *
     * @return array The parameters
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * Sets the parameters.
     *
     * @param array $parameters The parameters
     *
     * @return $this
     */
    public function setParameters(array $parameters)
    {
        $this->parameters = $parameters;

        return $this;
    }

    /**
     * Gets a parameter value.
     *
     * @return mixed The parameter value or null if nonexistent
     */
    public function getParameter(string $name)
    {
        return isset($this->parameters[$name]) ? $this->parameters[$name] : null;
    }

    /**
     * Checks if a parameter value is set for the given parameter.
     *
     * @return bool True if the parameter value is set, false otherwise
     */
    public function hasParameter(string $name)
    {
        return \array_key_exists($name, $this->parameters);
    }

    /**
     * Sets a parameter value.
     *
     * @param mixed $parameter The parameter value
     *
     * @return $this
     */
    public function setParameter(string $name, $parameter)
    {
        $this->parameters[$name] = $parameter;

        return $this;
    }
}
