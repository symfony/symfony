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
 * A "path like" DSN string.
 *
 * Example:
 * - redis:///var/run/redis/redis.sock
 * - memcached://user:password@/var/local/run/memcached.socket?weight=25
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class Path extends Dsn
{
    use UserPasswordTrait;
    /**
     * @var string
     */
    private $path;

    public function __construct(?string $scheme, string $path, array $parameters = [], array $authentication = [])
    {
        $this->path = $path;
        $this->setAuthentication($authentication);
        parent::__construct($scheme, $parameters);
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
