<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\BrowserKit;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Request
{
    protected $uri;
    protected $method;
    protected $parameters;
    protected $files;
    protected $cookies;
    protected $server;
    protected $content;

    /**
     * @param string $uri        The request URI
     * @param string $method     The HTTP method request
     * @param array  $parameters The request parameters
     * @param array  $files      An array of uploaded files
     * @param array  $cookies    An array of cookies
     * @param array  $server     An array of server parameters
     * @param string $content    The raw body data
     */
    public function __construct(string $uri, string $method, array $parameters = [], array $files = [], array $cookies = [], array $server = [], string $content = null)
    {
        $this->uri = $uri;
        $this->method = $method;

        array_walk_recursive($parameters, static function (&$value) {
            $value = (string) $value;
        });

        $this->parameters = $parameters;
        $this->files = $files;
        $this->cookies = $cookies;
        $this->server = $server;
        $this->content = $content;
    }

    /**
     * Gets the request URI.
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * Gets the request HTTP method.
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Gets the request parameters.
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Gets the request server files.
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    /**
     * Gets the request cookies.
     */
    public function getCookies(): array
    {
        return $this->cookies;
    }

    /**
     * Gets the request server parameters.
     */
    public function getServer(): array
    {
        return $this->server;
    }

    /**
     * Gets the request raw body data.
     */
    public function getContent(): ?string
    {
        return $this->content;
    }
}
