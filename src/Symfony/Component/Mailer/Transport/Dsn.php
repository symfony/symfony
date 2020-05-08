<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Transport;

use Symfony\Component\Dsn\Configuration\Url;
use Symfony\Component\Dsn\DsnParser;
use Symfony\Component\Dsn\Exception\DsnTypeNotSupported;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;

/**
 * @author Konstantin Myakshin <molodchick@gmail.com>
 */
final class Dsn extends Url
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
        parent::__construct($scheme, $host, $port, null, $options, ['user' => $user, 'password' => $password]);
    }

    public static function fromString(string $dsnString): self
    {
        $dsn = DsnParser::parseSimple($dsnString);
        if (!$dsn instanceof Url) {
            throw new InvalidArgumentException(sprintf('The "%s" mailer DSN is invalid.', $dsnString), 0, DsnTypeNotSupported::onlyUrl($dsnString));
        }

        return self::fromUrlDsn($dsn);
    }

    public static function fromUrlDsn(Url $dsn): self
    {
        return new self($dsn->getScheme(), $dsn->getHost(), $dsn->getUser(), $dsn->getPassword(), $dsn->getPort(), $dsn->getParameters());
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getHost(): string
    {
        return $this->host;
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
