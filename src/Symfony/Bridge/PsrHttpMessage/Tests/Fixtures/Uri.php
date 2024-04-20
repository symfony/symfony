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

use Psr\Http\Message\UriInterface;

/**
 * @author Rougin Royce Gutib <rougingutib@gmail.com>
 */
class Uri implements UriInterface
{
    private readonly string $scheme;
    private readonly string $userInfo;
    private readonly string $host;
    private readonly ?string $port;
    private readonly string $path;
    private readonly string $query;
    private readonly string $fragment;

    public function __construct(
        private readonly string $uriString,
    ) {
        $parts = parse_url($uriString);

        $this->scheme = $parts['scheme'] ?? '';
        $this->userInfo = $parts['user'] ?? '';
        $this->host = $parts['host'] ?? '';
        $this->port = $parts['port'] ?? null;
        $this->path = $parts['path'] ?? '';
        $this->query = $parts['query'] ?? '';
        $this->fragment = $parts['fragment'] ?? '';
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getAuthority(): string
    {
        if (!$this->host) {
            return '';
        }

        $authority = $this->host;

        if ($this->userInfo) {
            $authority = $this->userInfo.'@'.$authority;
        }

        $authority .= ':'.$this->port;

        return $authority;
    }

    public function getUserInfo(): string
    {
        return $this->userInfo;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function getFragment(): string
    {
        return $this->fragment;
    }

    public function withScheme($scheme): never
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    public function withUserInfo($user, $password = null): never
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    public function withHost($host): never
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    public function withPort($port): never
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    public function withPath($path): never
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    public function withQuery($query): never
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    public function withFragment($fragment): never
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    public function __toString(): string
    {
        return $this->uriString;
    }
}
