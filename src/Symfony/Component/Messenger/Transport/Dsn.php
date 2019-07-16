<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport;

use Symfony\Component\Messenger\Exception\InvalidArgumentException;

/**
 * @author Konstantin Myakshin <molodchick@gmail.com>
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

    public function __construct(
        string $scheme,
        ?string $host = null,
        ?string $user = null,
        ?string $password = null,
        ?int $port = null,
        ?string $path = null,
        array $options = []
    ) {
        $this->scheme = $scheme;
        $this->host = $host;
        $this->user = $user;
        $this->password = $password;
        $this->port = $port;
        $this->path = $path;
        $this->options = $options;
    }

    public static function fromString(string $dsn, array $options = []): self
    {
        if (preg_match('#^([a-z-+]+)://$#', $dsn, $matches)) {
            return new self($matches[1]);
        }

        if ((false === $parsed = parse_url($dsn)) || empty($parsed['scheme'])) {
            throw new InvalidArgumentException(sprintf('The "%s" messenger DSN is invalid.', $dsn));
        }

        $user = isset($parsed['user']) ? urldecode($parsed['user']) : null;
        $password = isset($parsed['pass']) ? urldecode($parsed['pass']) : null;
        $port = $parsed['port'] ?? null;
        $path = $parsed['path'] ?? null;
        parse_str($parsed['query'] ?? '', $query);

        return new self(
            $parsed['scheme'],
            $parsed['host'],
            $user,
            $password,
            $port,
            $path,
            array_replace_recursive($options, $query)
        );
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getHost(): ?string
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

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function getOption(string $key)
    {
        return $this->options[$key] ?? null;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function __toString()
    {
        $port = $this->port ? ':'.$this->port : '';

        $user = '';
        if ($this->user && $this->password) {
            $user = $this->user.':'.$this->password.'@';
        } elseif ($this->user) {
            $user = $this->user.'@';
        }

        $query = $this->options ? '?'.http_build_query($this->options) : '';

        return $this->scheme.'://'.$user.$this->host.$port.$this->path.$query;
    }
}
