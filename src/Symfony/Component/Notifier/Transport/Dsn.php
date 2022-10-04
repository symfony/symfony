<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Transport;

use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Exception\MissingRequiredOptionException;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class Dsn
{
    private string $scheme;
    private string $host;
    private ?string $user;
    private ?string $password;
    private ?int $port;
    private ?string $path;
    private array $options = [];
    private string $originalDsn;

    public function __construct(string $dsn)
    {
        $this->originalDsn = $dsn;

        if (false === $parsedDsn = parse_url($dsn)) {
            throw new InvalidArgumentException(sprintf('The "%s" notifier DSN is invalid.', $dsn));
        }

        if (!isset($parsedDsn['scheme'])) {
            throw new InvalidArgumentException(sprintf('The "%s" notifier DSN must contain a scheme.', $dsn));
        }
        $this->scheme = $parsedDsn['scheme'];

        if (!isset($parsedDsn['host'])) {
            throw new InvalidArgumentException(sprintf('The "%s" notifier DSN must contain a host (use "default" by default).', $dsn));
        }
        $this->host = $parsedDsn['host'];

        $this->user = '' !== ($parsedDsn['user'] ?? '') ? urldecode($parsedDsn['user']) : null;
        $this->password = '' !== ($parsedDsn['pass'] ?? '') ? urldecode($parsedDsn['pass']) : null;
        $this->port = $parsedDsn['port'] ?? null;
        $this->path = $parsedDsn['path'] ?? null;
        parse_str($parsedDsn['query'] ?? '', $this->options);
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getHost(): string
    {
        return $this->host;
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

    public function getOption(string $key, mixed $default = null)
    {
        return $this->options[$key] ?? $default;
    }

    public function getRequiredOption(string $key)
    {
        if (!\array_key_exists($key, $this->options) || '' === trim($this->options[$key])) {
            throw new MissingRequiredOptionException($key);
        }

        return $this->options[$key];
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function getOriginalDsn(): string
    {
        return $this->originalDsn;
    }
}
