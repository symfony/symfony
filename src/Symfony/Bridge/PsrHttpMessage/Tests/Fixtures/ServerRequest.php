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
    private readonly UriInterface $uri;

    public function __construct(
        string $version = '1.1',
        array $headers = [],
        ?StreamInterface $body = null,
        private readonly string $requestTarget = '/',
        private readonly string $method = 'GET',
        UriInterface|string|null $uri = null,
        private readonly array $server = [],
        private readonly array $cookies = [],
        private readonly array $query = [],
        private readonly array $uploadedFiles = [],
        private readonly array|object|null $data = null,
        private readonly array $attributes = [],
    ) {
        parent::__construct($version, $headers, $body);

        if (!$uri instanceof UriInterface) {
            $uri = new Uri((string) $uri);
        }

        $this->uri = $uri;
    }

    public function getRequestTarget(): string
    {
        return $this->requestTarget;
    }

    public function withRequestTarget($requestTarget): never
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function withMethod($method): never
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    public function withUri(UriInterface $uri, $preserveHost = false): never
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

    public function withCookieParams(array $cookies): never
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    public function getQueryParams(): array
    {
        return $this->query;
    }

    public function withQueryParams(array $query): never
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    public function withUploadedFiles(array $uploadedFiles): never
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    public function getParsedBody(): array|object|null
    {
        return $this->data;
    }

    public function withParsedBody($data): never
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getAttribute($name, mixed $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    public function withAttribute($name, $value): never
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    public function withoutAttribute($name): never
    {
        throw new \BadMethodCallException('Not implemented.');
    }
}
