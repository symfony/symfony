<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Middleware;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\ConnectionLossAwareHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Based on https://github.com/Baldinof/roadrunner-bundle/blob/3.x/src/Integration/Doctrine/DoctrineORMMiddleware.php.
 */
class ConnectionKeepAlive implements Middleware
{
    public function __construct(private ManagerRegistry $managerRegistry, private ContainerInterface $container)
    {
    }

    public function wrap(Driver $driver): Driver
    {
        return new class($driver, $this->managerRegistry, $this->container) extends AbstractDriverMiddleware {
            public function __construct(private Driver $driver, private readonly ManagerRegistry $managerRegistry, private readonly ContainerInterface $container)
            {
                parent::__construct($driver);
            }

            public function connect(array $params): DriverConnection
            {
                $connectionServices = $this->managerRegistry->getConnectionNames();

                foreach ($connectionServices as $connectionServiceName) {
                    if (!$this->container->initialized($connectionServiceName)) {
                        continue;
                    }

                    $connection = $this->container->get($connectionServiceName);

                    if (!$connection instanceof Connection) {
                        continue;
                    }

                    if ($connection->isConnected()) {
                        ConnectionLossAwareHandler::reconnectOnFailure($connection);
                    }

                    $managerNames = $this->managerRegistry->getManagerNames();

                    foreach ($managerNames as $managerName) {
                        if (!$this->container->initialized($managerName)) {
                            continue;
                        }

                        $manager = $this->container->get($managerName);

                        if (!$manager instanceof EntityManagerInterface) {
                            continue;
                        }

                        if (!$manager->isOpen()) {
                            $this->managerRegistry->resetManager($managerName);
                        }
                    }
                }

                return parent::connect($params);
            }
        };
    }
}
