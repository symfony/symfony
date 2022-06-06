<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Stamp;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class RouterContextStamp implements StampInterface
{
    private $baseUrl;
    private $method;
    private $host;
    private $scheme;
    private $httpPort;
    private $httpsPort;
    private $pathInfo;
    private $queryString;

    public function __construct(string $baseUrl, string $method, string $host, string $scheme, int $httpPort, int $httpsPort, string $pathInfo, string $queryString)
    {
        $this->baseUrl = $baseUrl;
        $this->method = $method;
        $this->host = $host;
        $this->scheme = $scheme;
        $this->httpPort = $httpPort;
        $this->httpsPort = $httpsPort;
        $this->pathInfo = $pathInfo;
        $this->queryString = $queryString;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getHttpPort(): int
    {
        return $this->httpPort;
    }

    public function getHttpsPort(): int
    {
        return $this->httpsPort;
    }

    public function getPathInfo(): string
    {
        return $this->pathInfo;
    }

    public function getQueryString(): string
    {
        return $this->queryString;
    }
}
