<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\DoctrineBundle;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;

/**
 * Connection
 */
class ConnectionFactory
{
    private $typesConfig = array();
    private $initialized = false;

    /**
     * @param ContainerInterface $container
     * @param array $typesConfig
     */
    public function __construct(array $typesConfig)
    {
        $this->typesConfig = $typesConfig;
    }

    /**
     * Create a connection by name.
     *
     * @param  string $connectionName
     * @return Doctrine\DBAL\Connection
     */
    public function createConnection(array $params, Configuration $config = null, EventManager $eventManager = null)
    {
        if (!$this->initialized) {
            $this->initializeTypes();
            $this->initialized = true;
        }

        return DriverManager::getConnection($params, $config, $eventManager);
    }

    private function initializeTypes()
    {
        foreach ($this->typesConfig as $type => $className) {
            if (Type::hasType($type)) {
                Type::overrideType($type, $className);
            } else {
                Type::addType($type, $className);
            }
        }
    }
}