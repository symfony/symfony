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

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * References all Doctrine connections and entity managers in a given Container.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Registry
{
    private $container;
    private $connections;
    private $entityManagers;
    private $defaultConnection;
    private $defaultEntityManager;

    public function __construct(ContainerInterface $container, array $connections, array $entityManagers, $defaultConnection, $defaultEntityManager)
    {
        $this->container = $container;
        $this->connections = $connections;
        $this->entityManagers = $entityManagers;

        if (!isset($this->connections[$defaultConnection])) {
            throw new \LogicException(sprintf('Default connection "%s" is not defined.', $defaultConnection));
        }
        $this->defaultConnection = $defaultConnection;

        if (!isset($this->entityManagers[$defaultEntityManager])) {
            throw new \LogicException(sprintf('Default entity manager "%s" is not defined.', $defaultEntityManager));
        }
        $this->defaultEntityManager = $defaultEntityManager;
    }

    public function getDefaultConnectionName()
    {
        return $this->defaultConnection;
    }

    public function getConnection($name = null)
    {
        if (null === $name) {
            return $this->container->get($this->connections[$this->defaultConnection]);
        }

        if (!isset($this->connections[$name])) {
            throw new \InvalidArgumentException(sprintf('Doctrine Connection named "%s" does not exist.', $name));
        }

        return $this->container->get($this->connections[$name]);
    }

    public function getConnectionNames()
    {
        return $this->connections;
    }

    public function getDefaultEntityManagerName()
    {
        return $this->defaultEntityManager;
    }

    public function getEntityManager($name)
    {
        if (null === $name) {
            return $this->container->get($this->entityManagers[$this->defaultEntityManager]);
        }

        if (!isset($this->entityManagers[$name])) {
            throw new \InvalidArgumentException(sprintf('Doctrine EntityManager named "%s" does not exist.', $name));
        }

        return $this->container->get($this->entityManagers[$name]);
    }

    public function getEntityManagerNames()
    {
        return $this->entityManagers;
    }

    /**
     * @param string|EntityManager
     */
    public function reloadEntityManager($name)
    {
        $id = null;
        $em = null;
        if (is_string($name)) {
            if (!isset($this->entityManagers[$name])) {
                throw new \InvalidArgumentException(sprintf('The "%s" entity manager does not exist.', $name));
            }

            $id = $this->entityManagers[$name];
            $em = $this->container->get($id);
        } elseif (is_object($name) && $name instanceof EntityManager) {
            foreach ($this->entityManagers as $managerId) {
                if ($this->container->get($managerId) === $name) {
                    $id = $managerId;
                    $em = $name;
                    break;
                }
            }

            if (null === $em) {
                throw new \InvalidArgumentException(sprintf('Unable to reload entity manager "%s" as it is not managed by the service container.', $class));
            }
        } else {
            throw new \InvalidArgumentException('The name argument must be a string or an EntityManager instance.');
        }

        if ($em->isOpen()) {
            $em->clear();

            return $em;
        }

        // force the creation of a new entity manager
        $this->container->set($id, null);

        return $this->container->get($id);
    }
}
