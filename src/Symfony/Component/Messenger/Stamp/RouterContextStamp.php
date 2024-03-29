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
    public function __construct(
        private string $baseUrl,
        private string $method,
        private string $host,
        private string $scheme,
        private int $httpPort,
        private int $httpsPort,
        private string $pathInfo,
        private string $queryString,
    ) {
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
