<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @method InstanceofConfigurator instanceof($fqcn)
 */
class ServicesConfigurator extends AbstractConfigurator
{
    const FACTORY = 'services';

    private $defaults;
    private $container;
    private $loader;
    private $instanceof;

    public function __construct(ContainerBuilder $container, PhpFileLoader $loader, array &$instanceof)
    {
        $this->defaults = new Definition();
        $this->container = $container;
        $this->loader = $loader;
        $this->instanceof = &$instanceof;
        $instanceof = [];
    }

    /**
     * Defines a set of defaults for following service definitions.
     *
     * @return DefaultsConfigurator
     */
    final public function defaults()
    {
        return new DefaultsConfigurator($this, $this->defaults = new Definition());
    }

    /**
     * Defines an instanceof-conditional to be applied to following service definitions.
     *
     * @param string $fqcn
     *
     * @return InstanceofConfigurator
     */
    final protected function setInstanceof($fqcn)
    {
        $this->instanceof[$fqcn] = $definition = new ChildDefinition('');

        return new InstanceofConfigurator($this, $definition, $fqcn);
    }

    /**
     * Registers a service.
     *
     * @param string      $id
     * @param string|null $class
     *
     * @return ServiceConfigurator
     */
    final public function set($id, $class = null)
    {
        $defaults = $this->defaults;
        $allowParent = !$defaults->getChanges() && empty($this->instanceof);

        $definition = new Definition();
        $definition->setPublic($defaults->isPublic());
        $definition->setAutowired($defaults->isAutowired());
        $definition->setAutoconfigured($defaults->isAutoconfigured());
        $definition->setBindings($defaults->getBindings());
        $definition->setChanges([]);

        $configurator = new ServiceConfigurator($this->container, $this->instanceof, $allowParent, $this, $definition, $id, $defaults->getTags());

        return null !== $class ? $configurator->class($class) : $configurator;
    }

    /**
     * Creates an alias.
     *
     * @param string $id
     * @param string $referencedId
     *
     * @return AliasConfigurator
     */
    final public function alias($id, $referencedId)
    {
        $ref = static::processValue($referencedId, true);
        $alias = new Alias((string) $ref, $this->defaults->isPublic());
        $this->container->setAlias($id, $alias);

        return new AliasConfigurator($this, $alias);
    }

    /**
     * Registers a PSR-4 namespace using a glob pattern.
     *
     * @param string $namespace
     * @param string $resource
     *
     * @return PrototypeConfigurator
     */
    final public function load($namespace, $resource)
    {
        $allowParent = !$this->defaults->getChanges() && empty($this->instanceof);

        return new PrototypeConfigurator($this, $this->loader, $this->defaults, $namespace, $resource, $allowParent);
    }

    /**
     * Gets an already defined service definition.
     *
     * @param string $id
     *
     * @return ServiceConfigurator
     *
     * @throws ServiceNotFoundException if the service definition does not exist
     */
    final public function get($id)
    {
        $allowParent = !$this->defaults->getChanges() && empty($this->instanceof);
        $definition = $this->container->getDefinition($id);

        return new ServiceConfigurator($this->container, $definition->getInstanceofConditionals(), $allowParent, $this, $definition, $id, []);
    }

    /**
     * Registers a service.
     *
     * @param string      $id
     * @param string|null $class
     *
     * @return ServiceConfigurator
     */
    final public function __invoke($id, $class = null)
    {
        return $this->set($id, $class);
    }
}
