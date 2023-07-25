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

use Psr\Http\Message\RequestInterface;
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

    /**
     * @param UriInterface|string|null $uri
     * @param array|object|null        $data
     */
    public function __construct(string $version = '1.1', array $headers = [], StreamInterface $body = null, string $requestTarget = '/', string $method = 'GET', $uri = null, array $server = [], array $cookies = [], array $query = [], array $uploadedFiles = [], $data = null, array $attributes = [])
    {
        parent::__construct($version, $headers, $body);

        if (!$uri instanceof UriInterface) {
            $uri = new Uri((string) $uri);
        }

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
     */
    public function withRequestTarget($requestTarget): RequestInterface
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * {@inheritdoc}
     */
    public function withMethod($method): RequestInterface
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    /**
     * {@inheritdoc}
     */
    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    /**
     * {@inheritdoc}
     *
     * @return static
     */
    public function withUri(UriInterface $uri, $preserveHost = false): RequestInterface
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
    public function withCookieParams(array $cookies): ServerRequestInterface
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
    public function withQueryParams(array $query): ServerRequestInterface
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
    public function withUploadedFiles(array $uploadedFiles): ServerRequestInterface
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
    public function withParsedBody($data): ServerRequestInterface
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
    public function withAttribute($name, $value): ServerRequestInterface
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    /**
     * {@inheritdoc}
     *
     * @return static
     */
    public function withoutAttribute($name): ServerRequestInterface
    {
        throw new \BadMethodCallException('Not implemented.');
    }
}
