<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\Dsn\Configuration;

/**
 * A "URL like" DSN string.
 *
 * Example:
 * - memcached://user:password@127.0.0.1?weight=50
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class Url extends Dsn
{
    use UserPasswordTrait;

    /**
     * @var string
     */
    private $host;

    /**
     * @var int|null
     */
    private $port;

    /**
     * @var string|null
     */
    private $path;

    public function __construct(?string $scheme, string $host, ?int $port = null, ?string $path = null, array $parameters = [], array $authentication = [])
    {
        $this->host = $host;
        $this->port = $port;
        $this->path = $path;
        $this->setAuthentication($authentication);
        parent::__construct($scheme, $parameters);
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }
}
