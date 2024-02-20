<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AccessToken\Credentials;

use Symfony\Component\AccessToken\Exception\InvalidArgumentException;

/**
 * @author Konstantin Myakshin <molodchick@gmail.com>
 * @author Pierre Rineau <pierre.rineau@processus.org>
 */
final class Dsn
{
    public function __construct(
        private readonly string $scheme,
        private readonly string $host,
        private readonly ?string $path = null,
        private readonly ?string $user = null,
        #[\SensitiveParameter]
        private readonly ?string $password = null,
        private readonly ?int $port = null,
        private readonly array $query = [],
    ) {}

    public static function fromString(#[\SensitiveParameter] string $dsn): self
    {
        if (false === $params = parse_url($dsn)) {
            throw new InvalidArgumentException('The access token DSN is invalid.');
        }

        if (!isset($params['scheme'])) {
            throw new InvalidArgumentException('The access token DSN must contain a scheme.');
        }

        if (!isset($params['host'])) {
            throw new InvalidArgumentException('The access token DSN must contain a host (use "default" by default).');
        }

        $path = '' !== ($params['path'] ?? '') ? $params['path'] : null;
        $user = '' !== ($params['user'] ?? '') ? rawurldecode($params['user']) : null;
        $password = '' !== ($params['pass'] ?? '') ? rawurldecode($params['pass']) : null;
        $port = $params['port'] ?? null;
        parse_str($params['query'] ?? '', $query);

        return new self(
            scheme: $params['scheme'],
            host: $params['host'],
            path: $path,
            user: $user,
            password: $password,
            port: $port,
            query: $query,
        );
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function getUser(): ?string
    {
        return $this->user;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getPort(?int $default = null): ?int
    {
        return $this->port ?? $default;
    }

    public function getOption(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function toEndpointUrl(array $excludeParams = []): string
    {
        $path = $this->path ?? '';

        $queryString = '';
        if ($this->query && $values = array_diff_key($this->query, array_flip($excludeParams))) {
            $queryString = (str_contains($path, '?') ? '&' : '?') . http_build_query($values);
        }

        return 'https://' . $this->host . ($this->port ? ':' . $this->port : '') . '/' . ltrim($path, '/') . $queryString;
    }
}
