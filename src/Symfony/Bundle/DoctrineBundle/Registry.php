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
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Bridge\Doctrine\ManagerRegistry;
use Doctrine\ORM\ORMException;

/**
 * References all Doctrine connections and entity managers in a given Container.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Registry extends ManagerRegistry implements RegistryInterface
{
    public function __construct(ContainerInterface $container, array $connections, array $entityManagers, $defaultConnection, $defaultEntityManager)
    {
        $this->setContainer($container);

        parent::__construct('ORM', $connections, $entityManagers, $defaultConnection, $defaultEntityManager, 'Doctrine\ORM\Proxy\Proxy');
    }

    /**
     * Gets the default entity manager name.
     *
     * @return string The default entity manager name
     *
     * @deprecated
     */
    public function getDefaultEntityManagerName()
    {
        return $this->getDefaultManagerName();
    }

    /**
     * Gets a named entity manager.
     *
     * @param string $name The entity manager name (null for the default one)
     *
     * @return EntityManager
     *
     * @deprecated
     */
    public function getEntityManager($name = null)
    {
        return $this->getManager($name);
    }

    /**
     * Gets an array of all registered entity managers
     *
     * @return array An array of EntityManager instances
     *
     * @deprecated
     */
    public function getEntityManagers()
    {
        return $this->getManagers();
    }

    /**
     * Resets a named entity manager.
     *
     * This method is useful when an entity manager has been closed
     * because of a rollbacked transaction AND when you think that
     * it makes sense to get a new one to replace the closed one.
     *
     * Be warned that you will get a brand new entity manager as
     * the existing one is not useable anymore. This means that any
     * other object with a dependency on this entity manager will
     * hold an obsolete reference. You can inject the registry instead
     * to avoid this problem.
     *
     * @param string $name The entity manager name (null for the default one)
     *
     * @return EntityManager
     */
    public function resetEntityManager($name = null)
    {
        $this->resetManager($name);
    }

    /**
     * Resolves a registered namespace alias to the full namespace.
     *
     * This method looks for the alias in all registered entity managers.
     *
     * @param string $alias The alias
     *
     * @return string The full namespace
     */
    public function getEntityNamespace($alias)
    {
        return $this->getAliasNamespace($alias);
    }

    /**
     * Resolves a registered namespace alias to the full namespace.
     *
     * This method looks for the alias in all registered entity managers.
     *
     * @param string $alias The alias
     *
     * @return string The full namespace
     *
     * @see Configuration::getEntityNamespace
     */
    public function getAliasNamespace($alias)
    {
        foreach (array_keys($this->getManagers()) as $name) {
            try {
                return $this->getManager($name)->getConfiguration()->getEntityNamespace($alias);
            } catch (ORMException $e) {
            }
        }

        throw ORMException::unknownEntityNamespace($alias);
    }

    /**
     * Gets all connection names.
     *
     * @return array An array of connection names
     */
    public function getEntityManagerNames()
    {
        return $this->getManagerNames();
    }

    /**
     * Gets the entity manager associated with a given class.
     *
     * @param string $class A Doctrine Entity class name
     *
     * @return EntityManager|null
     */
    public function getEntityManagerForClass($class)
    {
        return $this->getManagerForClass($class);
    }
}
