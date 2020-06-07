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

namespace Symfony\Component\Dsn;

class ConnectionRegistry
{
    /**
     * @var array [dsn => Connection]
     */
    private $connections = [];

    /**
     * @var ConnectionFactoryInterface
     */
    private $factory;

    public function __construct(ConnectionFactoryInterface $factory)
    {
        $this->factory = $factory;
    }

    public function addConnection(string $dsn, object $connection)
    {
        $this->connections[$dsn] = $connection;
    }

    public function has(string $dsn): bool
    {
        return isset($this->connections[$dsn]);
    }

    public function getConnection(string $dsn): object
    {
        if ($this->has($dsn)) {
            return $this->connections[$dsn];
        }

        return $this->connections[$dsn] = $this->factory::create($dsn);
    }
}
