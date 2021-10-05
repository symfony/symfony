<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PsrHttpMessage\Tests\Fixtures;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class ServerRequest extends Message implements ServerRequestInterface
{
    private $requestTarget;
    private $method;
    private $uri;
    private $server;
    private $cookies;
    private $query;
    private $uploadedFiles;
    private $data;
    private $attributes;

    public function __construct($version = '1.1', array $headers = [], StreamInterface $body = null, $requestTarget = '/', $method = 'GET', $uri = null, array $server = [], array $cookies = [], array $query = [], array $uploadedFiles = [], $data = null, array $attributes = [])
    {
        parent::__construct($version, $headers, $body);

        $this->requestTarget = $requestTarget;
        $this->method = $method;
        $this->uri = $uri;
        $this->server = $server;
        $this->cookies = $cookies;
        $this->query = $query;
        $this->uploadedFiles = $uploadedFiles;
        $this->data = $data;
        $this->attributes = $attributes;
    }

    public function getRequestTarget(): string
    {
        return $this->requestTarget;
    }

    /**
     * {@inheritdoc}
     *
     * @return static
     */
    public function withRequestTarget($requestTarget)
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * {@inheritdoc}
     *
     * @return static
     */
    public function withMethod($method)
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    /**
     * {@inheritdoc}
     *
     * @return UriInterface
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * {@inheritdoc}
     *
     * @return static
     */
    public function withUri(UriInterface $uri, $preserveHost = false)
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    public function getServerParams(): array
    {
        return $this->server;
    }

    public function getCookieParams(): array
    {
        return $this->cookies;
    }

    /**
     * {@inheritdoc}
     *
     * @return static
     */
    public function withCookieParams(array $cookies)
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    public function getQueryParams(): array
    {
        return $this->query;
    }

    /**
     * {@inheritdoc}
     *
     * @return static
     */
    public function withQueryParams(array $query)
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    /**
     * {@inheritdoc}
     *
     * @return static
     */
    public function withUploadedFiles(array $uploadedFiles)
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    /**
     * {@inheritdoc}
     *
     * @return array|object|null
     */
    public function getParsedBody()
    {
        return $this->data;
    }

    /**
     * {@inheritdoc}
     *
     * @return static
     */
    public function withParsedBody($data)
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * {@inheritdoc}
     *
     * @return mixed
     */
    public function getAttribute($name, $default = null)
    {
        return $this->attributes[$name] ?? $default;
    }

    /**
     * {@inheritdoc}
     *
     * @return static
     */
    public function withAttribute($name, $value)
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    /**
     * {@inheritdoc}
     *
     * @return static
     */
    public function withoutAttribute($name)
    {
        throw new \BadMethodCallException('Not implemented.');
    }
}
