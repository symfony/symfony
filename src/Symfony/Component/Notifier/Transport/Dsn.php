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

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 5.0
 */
final class Dsn
{
    private $scheme;
    private $host;
    private $user;
    private $password;
    private $port;
    private $options;

    public function __construct(string $scheme, string $host, ?string $user = null, ?string $password = null, ?int $port = null, array $options = [])
    {
        $this->scheme = $scheme;
        $this->host = $host;
        $this->user = $user;
        $this->password = $password;
        $this->port = $port;
        $this->options = $options;
    }

    public static function fromString(string $dsn): self
    {
        if (false === $parsedDsn = parse_url($dsn)) {
            throw new InvalidArgumentException(sprintf('The "%s" notifier DSN is invalid.', $dsn));
        }

        if (!isset($parsedDsn['scheme'])) {
            throw new InvalidArgumentException(sprintf('The "%s" notifier DSN must contain a scheme.', $dsn));
        }

        if (!isset($parsedDsn['host'])) {
            throw new InvalidArgumentException(sprintf('The "%s" notifier DSN must contain a host (use "default" by default).', $dsn));
        }

        $user = isset($parsedDsn['user']) ? urldecode($parsedDsn['user']) : null;
        $password = isset($parsedDsn['pass']) ? urldecode($parsedDsn['pass']) : null;
        $port = $parsedDsn['port'] ?? null;
        parse_str($parsedDsn['query'] ?? '', $query);

        return new self($parsedDsn['scheme'], $parsedDsn['host'], $user, $password, $port, $query);
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

    public function getOption(string $key, $default = null)
    {
        return $this->options[$key] ?? $default;
    }
}
