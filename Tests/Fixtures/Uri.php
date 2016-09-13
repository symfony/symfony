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

    public function __construct($uri = '')
    {
        $parts = parse_url($uri);

        $this->scheme = isset($parts['scheme']) ? $parts['scheme'] : '';
        $this->userInfo = isset($parts['user']) ? $parts['user'] : '';
        $this->host = isset($parts['host']) ? $parts['host'] : '';
        $this->port = isset($parts['port']) ? $parts['port'] : null;
        $this->path = isset($parts['path']) ? $parts['path'] : '';
        $this->query = isset($parts['query']) ? $parts['query'] : '';
        $this->fragment = isset($parts['fragment']) ? $parts['fragment'] : '';
        $this->uriString = $uri;
    }

    public function getScheme()
    {
        return $this->scheme;
    }

    public function getAuthority()
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

    public function getUserInfo()
    {
        return $this->userInfo;
    }

    public function getHost()
    {
        return $this->host;
    }

    public function getPort()
    {
        return $this->port;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function getQuery()
    {
        return $this->query;
    }

    public function getFragment()
    {
        return $this->fragment;
    }

    public function withScheme($scheme)
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    public function withUserInfo($user, $password = null)
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    public function withHost($host)
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    public function withPort($port)
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    public function withPath($path)
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    public function withQuery($query)
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    public function withFragment($fragment)
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    public function __toString()
    {
        return $this->uriString;
    }
}
