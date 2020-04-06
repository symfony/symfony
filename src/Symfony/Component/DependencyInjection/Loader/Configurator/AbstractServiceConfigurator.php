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

use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

abstract class AbstractServiceConfigurator extends AbstractConfigurator
{
    protected $parent;
    protected $id;
    private $defaultTags = [];

    public function __construct(ServicesConfigurator $parent, Definition $definition, string $id = null, array $defaultTags = [])
    {
        $this->parent = $parent;
        $this->definition = $definition;
        $this->id = $id;
        $this->defaultTags = $defaultTags;
    }

    public function __destruct()
    {
        // default tags should be added last
        foreach ($this->defaultTags as $name => $attributes) {
            foreach ($attributes as $attributes) {
                $this->definition->addTag($name, $attributes);
            }
        }
        $this->defaultTags = [];
    }

    /**
     * Registers a service.
     */
    final public function set(?string $id, string $class = null): ServiceConfigurator
    {
        $this->__destruct();

        return $this->parent->set($id, $class);
    }

    /**
     * Creates an alias.
     */
    final public function alias(string $id, string $referencedId): AliasConfigurator
    {
        $this->__destruct();

        return $this->parent->alias($id, $referencedId);
    }

    /**
     * Registers a PSR-4 namespace using a glob pattern.
     */
    final public function load(string $namespace, string $resource): PrototypeConfigurator
    {
        $this->__destruct();

        return $this->parent->load($namespace, $resource);
    }

    /**
     * Gets an already defined service definition.
     *
     * @throws ServiceNotFoundException if the service definition does not exist
     */
    final public function get(string $id): ServiceConfigurator
    {
        $this->__destruct();

        return $this->parent->get($id);
    }

    /**
     * Registers a stack of decorator services.
     *
     * @param InlineServiceConfigurator[]|ReferenceConfigurator[] $services
     */
    final public function stack(string $id, array $services): AliasConfigurator
    {
        $this->__destruct();

        return $this->parent->stack($id, $services);
    }

    /**
     * Registers a service.
     */
    final public function __invoke(string $id, string $class = null): ServiceConfigurator
    {
        $this->__destruct();

        return $this->parent->set($id, $class);
    }
}
