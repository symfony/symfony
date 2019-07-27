<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine;

use Doctrine\Common\Persistence\ManagerRegistry as ManagerRegistryInterface;
use Doctrine\ORM\EntityManager;

/**
 * References Doctrine connections and entity managers.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface RegistryInterface extends ManagerRegistryInterface
{
    /**
     * Gets the default entity manager name.
     *
     * @return string The default entity manager name
     */
    public function getDefaultEntityManagerName();

    /**
     * Gets a named entity manager.
     *
     * @return EntityManager
     */
    public function getEntityManager(string $name = null);

    /**
     * Gets an array of all registered entity managers.
     *
     * @return array An array of EntityManager instances
     */
    public function getEntityManagers();

    /**
     * Resets a named entity manager.
     *
     * This method is useful when an entity manager has been closed
     * because of a rollbacked transaction AND when you think that
     * it makes sense to get a new one to replace the closed one.
     *
     * Be warned that you will get a brand new entity manager as
     * the existing one is not usable anymore. This means that any
     * other object with a dependency on this entity manager will
     * hold an obsolete reference. You can inject the registry instead
     * to avoid this problem.
     *
     * @return EntityManager
     */
    public function resetEntityManager(string $name = null);

    /**
     * Resolves a registered namespace alias to the full namespace.
     *
     * This method looks for the alias in all registered entity managers.
     *
     * @return string The full namespace
     *
     * @see Configuration::getEntityNamespace
     */
    public function getEntityNamespace(string $alias);

    /**
     * Gets all connection names.
     *
     * @return array An array of connection names
     */
    public function getEntityManagerNames();

    /**
     * Gets the entity manager associated with a given class.
     *
     * @return EntityManager|null
     */
    public function getEntityManagerForClass(string $class);
}
