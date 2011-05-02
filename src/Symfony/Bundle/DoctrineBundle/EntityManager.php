<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineBundle;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager as BaseEntityManager;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\Configuration;
use Doctrine\Common\EventManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Doctrine Entity Manager that knows how to reload itself.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class EntityManager extends BaseEntityManager
{
    private $name;
    private $registry;

    /**
     * Sets the registry under which this entity manager is known.
     *
     * @param Registry $registry
     */
    public function setRegistry(Registry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Sets the name under which the entity manager is known by the registry.
     *
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Returns a new (aka reloaded) entity manager for the current one.
     *
     * This method also replaces the entity manager.
     */
    public function reload()
    {
        $this->close();

        return $this->registry->reloadEntityManager(isset($this->name) ? $this->name : $this);
    }

    // should be removed if https://github.com/doctrine/doctrine2/pull/51 is merged upstream
    public static function create($conn, Configuration $config, EventManager $eventManager = null)
    {
        if (!$config->getMetadataDriverImpl()) {
            throw ORMException::missingMappingDriverImpl();
        }

        if (is_array($conn)) {
            $conn = \Doctrine\DBAL\DriverManager::getConnection($conn, $config, ($eventManager ?: new EventManager()));
        } else if ($conn instanceof Connection) {
            if ($eventManager !== null && $conn->getEventManager() !== $eventManager) {
                 throw ORMException::mismatchedEventManager();
            }
        } else {
            throw new \InvalidArgumentException("Invalid argument: " . $conn);
        }

        return new static($conn, $config, $conn->getEventManager());
    }
}
