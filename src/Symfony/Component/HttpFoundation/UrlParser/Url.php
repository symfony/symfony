<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\UrlParser;

/**
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
final class Url implements \Stringable
{
    public function __construct(
        public string $scheme,
        public ?string $user = null,
        public ?string $password = null,
        public ?string $host = null,
        public ?int $port = null,
        public ?string $path = null,
        public ?string $query = null,
        public ?string $fragment = null
    ) {
    }

    public function isAuthenticated(): bool
    {
        return null !== $this->user || null !== $this->password;
    }

    public function isScheme(string $scheme): bool
    {
        return $this->scheme === $scheme;
    }

    public function __toString(): string
    {
        $dsn = $this->scheme.'://';

        if (null !== $this->user) {
            $dsn .= rawurlencode($this->user);
        }

        if (null !== $this->password) {
            $dsn .= ':'.rawurlencode($this->password);
        }

        if (null !== $this->user || null !== $this->password) {
            $dsn .= '@';
        }

        $dsn .= $this->host;

        if (null !== $this->port) {
            $dsn .= ':'.$this->port;
        }

        if (null !== $this->path) {
            $dsn .= $this->path;
        }

        if (null !== $this->query) {
            $dsn .= '?'.$this->query;
        }

        if (null !== $this->fragment) {
            $dsn .= '#'.$this->fragment;
        }

        return $dsn;
    }

    public function parsedQuery(): array
    {
        if (null === $this->query) {
            return [];
        }

        parse_str($this->query, $query);

        return $query;
    }
}
