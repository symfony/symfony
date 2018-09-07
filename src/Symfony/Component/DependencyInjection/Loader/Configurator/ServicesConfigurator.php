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
 */
class ServicesConfigurator extends AbstractConfigurator
{
    const FACTORY = 'services';

    private $defaults;
    private $container;
    private $loader;
    private $instanceof;
    private $anonymousHash;
    private $anonymousCount;

    public function __construct(ContainerBuilder $container, PhpFileLoader $loader, array &$instanceof, string $path = null, int &$anonymousCount = 0)
    {
        $this->defaults = new Definition();
        $this->container = $container;
        $this->loader = $loader;
        $this->instanceof = &$instanceof;
        $this->anonymousHash = ContainerBuilder::hash($path ?: mt_rand());
        $this->anonymousCount = &$anonymousCount;
        $instanceof = array();
    }

    /**
     * Defines a set of defaults for following service definitions.
     */
    final public function defaults(): DefaultsConfigurator
    {
        return new DefaultsConfigurator($this, $this->defaults = new Definition());
    }

    /**
     * Defines an instanceof-conditional to be applied to following service definitions.
     */
    final public function instanceof(string $fqcn): InstanceofConfigurator
    {
        $this->instanceof[$fqcn] = $definition = new ChildDefinition('');

        return new InstanceofConfigurator($this, $definition, $fqcn);
    }

    /**
     * Registers a service.
     *
     * @param string|null $id    The service id, or null to create an anonymous service
     * @param string|null $class The class of the service, or null when $id is also the class name
     */
    final public function set(?string $id, string $class = null): ServiceConfigurator
    {
        $defaults = $this->defaults;
        $allowParent = !$defaults->getChanges() && empty($this->instanceof);

        $definition = new Definition();

        if (null === $id) {
            if (!$class) {
                throw new \LogicException('Anonymous services must have a class name.');
            }

            $id = sprintf('.%d_%s', ++$this->anonymousCount, preg_replace('/^.*\\\\/', '', $class).'~'.$this->anonymousHash);
            $definition->setPublic(false);
        } else {
            $definition->setPublic($defaults->isPublic());
        }

        $definition->setAutowired($defaults->isAutowired());
        $definition->setAutoconfigured($defaults->isAutoconfigured());
        $definition->setBindings($defaults->getBindings());
        $definition->setChanges(array());

        $configurator = new ServiceConfigurator($this->container, $this->instanceof, $allowParent, $this, $definition, $id, $defaults->getTags());

        return null !== $class ? $configurator->class($class) : $configurator;
    }

    /**
     * Creates an alias.
     */
    final public function alias(string $id, string $referencedId): AliasConfigurator
    {
        $ref = static::processValue($referencedId, true);
        $alias = new Alias((string) $ref, $this->defaults->isPublic());
        $this->container->setAlias($id, $alias);

        return new AliasConfigurator($this, $alias);
    }

    /**
     * Registers a PSR-4 namespace using a glob pattern.
     */
    final public function load(string $namespace, string $resource): PrototypeConfigurator
    {
        $allowParent = !$this->defaults->getChanges() && empty($this->instanceof);

        return new PrototypeConfigurator($this, $this->loader, $this->defaults, $namespace, $resource, $allowParent);
    }

    /**
     * Gets an already defined service definition.
     *
     * @throws ServiceNotFoundException if the service definition does not exist
     */
    final public function get(string $id): ServiceConfigurator
    {
        $allowParent = !$this->defaults->getChanges() && empty($this->instanceof);
        $definition = $this->container->getDefinition($id);

        return new ServiceConfigurator($this->container, $definition->getInstanceofConditionals(), $allowParent, $this, $definition, $id, array());
    }

    /**
     * Registers a service.
     */
    final public function __invoke(string $id, string $class = null): ServiceConfigurator
    {
        return $this->set($id, $class);
    }
}
