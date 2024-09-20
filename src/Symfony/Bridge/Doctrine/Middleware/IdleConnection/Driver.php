<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Middleware\IdleConnection;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

final class Driver extends AbstractDriverMiddleware
{
    public function __construct(
        DriverInterface $driver,
        private \ArrayObject $connectionExpiries,
        private readonly int $ttl,
        private readonly string $connectionName,
    ) {
        parent::__construct($driver);
    }

    public function connect(array $params): ConnectionInterface
    {
        $timestamp = time();
        $connection = parent::connect($params);
        $this->connectionExpiries[$this->connectionName] = $timestamp + $this->ttl;

        return $connection;
    }
}
