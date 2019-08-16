<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock;

use Symfony\Component\Lock\Exception\InvalidArgumentException;

/**
 * @author Konstantin Myakshin <molodchick@gmail.com>
 * @author Hamza Amrouche <hamza.simperfit@gmail.com>
 */
final class Dsn
{
    private $scheme;
    private $host;
    private $user;
    private $password;
    private $port;
    private $path;
    private $options;

    public function __construct(string $scheme, string $host, ?string $user = null, ?string $password = null, ?int $port = null, ?string $path = null, array $options = [])
    {
        $this->scheme = $scheme;
        $this->host = $host;
        $this->user = $user;
        $this->password = $password;
        $this->port = $port;
        $this->path = $path;
        $this->options = $options;
    }

    public static function isValid(string $dsn)
    {
        return false !== parse_url($dsn);
    }

    public static function fromString(string $dsn, array $options): self
    {
        if (false === $parsedDsn = parse_url($dsn)) {
            throw new InvalidArgumentException(sprintf('The "%s" DSN is invalid.', $dsn));
        }

        parse_str($parsedDsn['query'] ?? '', $options);

        return new self($parsedDsn['scheme'],
            $parsedDsn['host'],
            isset($parsedDsn['user']) ? urldecode($parsedDsn['user']) : null,
            isset($parsedDsn['pass']) ? urldecode($parsedDsn['pass']) : null,
            $parsedDsn['port'] ?? null, $parsedDsn['path'] ?? null,
            $options);
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getHost(string $default = null): ?string
    {
        return $this->host ?? $default;
    }

    public function getUser(): ?string
    {
        return $this->user;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getPort(int $default = null): ?int
    {
        return $this->port ?? $default;
    }

    public function getPath(string $default = null): ?string
    {
        return $this->path ?? $default;
    }

    public function getOption(string $key, $default = null)
    {
        return $this->options[$key] ?? $default;
    }

    public function getOptions(): array
    {
        return $this->options;
    }
}
