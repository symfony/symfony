<?php

namespace Symfony\Component\BrowserKit;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Request object.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Request
{
    protected $uri;
    protected $method;
    protected $parameters;
    protected $files;
    protected $cookies;
    protected $server;

    /**
     * Constructor.
     *
     * @param string $uri        The request URI
     * @param array  $method     The HTTP method request
     * @param array  $parameters The request parameters
     * @param array  $files      An array of uploaded files
     * @param array  $cookies    An array of cookies
     * @param array  $server     An array of server parameters
     */
    public function __construct($uri, $method, array $parameters = array(), array $files = array(), array $cookies = array(), array $server = array())
    {
        $this->uri = $uri;
        $this->method = $method;
        $this->parameters = $parameters;
        $this->files = $files;
        $this->cookies = $cookies;
        $this->server = $server;
    }

    /**
     * Gets the request URI.
     *
     * @return string The request URI
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * Gets the request HTTP method.
     *
     * @return string The request HTTP method
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * Gets the request parameters.
     *
     * @return array The request parameters
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * Gets the request server files.
     *
     * @return array The request files
     */
    public function getFiles()
    {
        return $this->files;
    }

    /**
     * Gets the request cookies.
     *
     * @return array The request cookies
     */
    public function getCookies()
    {
        return $this->cookies;
    }

    /**
     * Gets the request server parameters.
     *
     * @return array The request server parameters
     */
    public function getServer()
    {
        return $this->server;
    }
}
