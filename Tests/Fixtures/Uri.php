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
    private $scheme = '';
    private $userInfo = '';
    private $host = '';
    private $port;
    private $path = '';
    private $query = '';
    private $fragment = '';
    private $uriString;

    public function __construct(string $uri = '')
    {
        $parts = parse_url($uri);

        $this->scheme = $parts['scheme'] ?? '';
        $this->userInfo = $parts['user'] ?? '';
        $this->host = $parts['host'] ?? '';
        $this->port = $parts['port'] ?? null;
        $this->path = $parts['path'] ?? '';
        $this->query = $parts['query'] ?? '';
        $this->fragment = $parts['fragment'] ?? '';
        $this->uriString = $uri;
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getAuthority(): string
    {
        if (empty($this->host)) {
            return '';
        }

        $authority = $this->host;

        if (!empty($this->userInfo)) {
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

    /**
     * {@inheritdoc}
     *
     * @return static
     */
    public function withScheme($scheme)
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    /**
     * {@inheritdoc}
     *
     * @return static
     */
    public function withUserInfo($user, $password = null)
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    /**
     * {@inheritdoc}
     *
     * @return static
     */
    public function withHost($host)
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    /**
     * {@inheritdoc}
     *
     * @return static
     */
    public function withPort($port)
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    /**
     * {@inheritdoc}
     *
     * @return static
     */
    public function withPath($path)
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    /**
     * {@inheritdoc}
     *
     * @return static
     */
    public function withQuery($query)
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    /**
     * {@inheritdoc}
     *
     * @return static
     */
    public function withFragment($fragment)
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    public function __toString(): string
    {
        return $this->uriString;
    }
}
