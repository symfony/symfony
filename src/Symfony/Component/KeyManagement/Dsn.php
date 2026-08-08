<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement;

use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;

/**
 * Parses a KMS connection string of the form:
 *
 *     scheme://[user[:password]@]host[:port][/path][?option=value&...]
 *
 * Each bridge defines its own scheme (`sodium://`, `vault-transit://`, ...).
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class Dsn
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        public readonly string $scheme,
        public readonly ?string $host = null,
        public readonly ?string $user = null,
        #[\SensitiveParameter] public readonly ?string $password = null,
        public readonly ?int $port = null,
        public readonly string $path = '',
        public readonly array $options = [],
    ) {
    }

    public static function fromString(#[\SensitiveParameter] string $dsn): self
    {
        $sanitized = preg_replace('#^([\w+.-]+://)(?=[/?]|$)#', '$1__no_host__', $dsn, 1);

        if (false === $parts = parse_url($sanitized)) {
            throw new InvalidArgumentException('The KMS DSN is invalid.');
        }

        if (!isset($parts['scheme'])) {
            throw new InvalidArgumentException('The KMS DSN must contain a scheme.');
        }

        $host = $parts['host'] ?? null;
        if ('__no_host__' === $host) {
            $host = null;
        }
        $user = '' !== ($parts['user'] ?? '') ? rawurldecode($parts['user']) : null;
        $password = '' !== ($parts['pass'] ?? '') ? rawurldecode($parts['pass']) : null;
        parse_str($parts['query'] ?? '', $options);

        return new self(
            $parts['scheme'],
            $host,
            $user,
            $password,
            $parts['port'] ?? null,
            $parts['path'] ?? '',
            $options,
        );
    }

    public function getOption(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    public function getRequiredOption(string $key): mixed
    {
        if (!\array_key_exists($key, $this->options)) {
            throw new InvalidArgumentException(\sprintf('Required DSN option "%s" is missing.', $key));
        }

        return $this->options[$key];
    }
}
