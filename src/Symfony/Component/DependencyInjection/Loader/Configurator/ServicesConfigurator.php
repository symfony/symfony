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
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ServicesConfigurator extends AbstractConfigurator
{
    public const FACTORY = 'services';

    private Definition $defaults;
    private array $instanceof;
    private string $anonymousHash;
    private int $anonymousCount;

    public function __construct(
        private ContainerBuilder $container,
        private PhpFileLoader $loader,
        array &$instanceof,
        private ?string $path = null,
        int &$anonymousCount = 0,
    ) {
        $this->defaults = new Definition();
        $this->instanceof = &$instanceof;
        $this->anonymousHash = ContainerBuilder::hash($path ?: mt_rand());
        $this->anonymousCount = &$anonymousCount;
        $instanceof = [];
    }

    /**
     * Defines a set of defaults for following service definitions.
     */
    final public function defaults(): DefaultsConfigurator
    {
        return new DefaultsConfigurator($this, $this->defaults = new Definition(), $this->path);
    }

    /**
     * Defines an instanceof-conditional to be applied to following service definitions.
     */
    final public function instanceof(string $fqcn): InstanceofConfigurator
    {
        $this->instanceof[$fqcn] = $definition = new ChildDefinition('');

        return new InstanceofConfigurator($this, $definition, $fqcn, $this->path);
    }

    /**
     * Registers a service.
     *
     * @param string|null $id    The service id, or null to create an anonymous service
     * @param string|null $class The class of the service, or null when $id is also the class name
     */
    final public function set(?string $id, ?string $class = null): ServiceConfigurator
    {
        $defaults = $this->defaults;
        $definition = new Definition();

        if (null === $id) {
            if (!$class) {
                throw new \LogicException('Anonymous services must have a class name.');
            }

            $id = \sprintf('.%d_%s', ++$this->anonymousCount, preg_replace('/^.*\\\\/', '', $class).'~'.$this->anonymousHash);
        } elseif (!$defaults->isPublic() || !$defaults->isPrivate()) {
            $definition->setPublic($defaults->isPublic() && !$defaults->isPrivate());
        }

        $definition->setAutowired($defaults->isAutowired());
        $definition->setAutoconfigured($defaults->isAutoconfigured());
        // deep clone, to avoid multiple process of the same instance in the passes
        $definition->setBindings(unserialize(serialize($defaults->getBindings())));
        $definition->setChanges([]);

        $configurator = new ServiceConfigurator($this->container, $this->instanceof, true, $this, $definition, $id, $defaults->getTags(), $this->path);

        return null !== $class ? $configurator->class($class) : $configurator;
    }

    /**
     * Removes an already defined service definition or alias.
     *
     * @return $this
     */
    final public function remove(string $id): static
    {
        $this->container->removeDefinition($id);
        $this->container->removeAlias($id);

        return $this;
    }

    /**
     * Creates an alias.
     */
    final public function alias(string $id, string $referencedId): AliasConfigurator
    {
        $ref = static::processValue($referencedId, true);
        $alias = new Alias((string) $ref);
        if (!$this->defaults->isPublic() || !$this->defaults->isPrivate()) {
            $alias->setPublic($this->defaults->isPublic());
        }
        $this->container->setAlias($id, $alias);

        return new AliasConfigurator($this, $alias);
    }

    /**
     * Registers a PSR-4 namespace using a glob pattern.
     */
    final public function load(string $namespace, string $resource): PrototypeConfigurator
    {
        return new PrototypeConfigurator($this, $this->loader, $this->defaults, $namespace, $resource, true, $this->path);
    }

    /**
     * Gets an already defined service definition.
     *
     * @throws ServiceNotFoundException if the service definition does not exist
     */
    final public function get(string $id): ServiceConfigurator
    {
        $definition = $this->container->getDefinition($id);

        return new ServiceConfigurator($this->container, $definition->getInstanceofConditionals(), true, $this, $definition, $id, []);
    }

    /**
     * Registers a stack of decorator services.
     *
     * @param InlineServiceConfigurator[]|ReferenceConfigurator[] $services
     */
    final public function stack(string $id, array $services): AliasConfigurator
    {
        foreach ($services as $i => $service) {
            if ($service instanceof InlineServiceConfigurator) {
                $definition = $service->definition->setInstanceofConditionals($this->instanceof);

                $changes = $definition->getChanges();
                $definition->setAutowired((isset($changes['autowired']) ? $definition : $this->defaults)->isAutowired());
                $definition->setAutoconfigured((isset($changes['autoconfigured']) ? $definition : $this->defaults)->isAutoconfigured());
                $definition->setBindings(array_merge($this->defaults->getBindings(), $definition->getBindings()));
                $definition->setChanges($changes);

                $services[$i] = $definition;
            } elseif (!$service instanceof ReferenceConfigurator) {
                throw new InvalidArgumentException(\sprintf('"%s()" expects a list of definitions as returned by "%s()" or "%s()", "%s" given at index "%s" for service "%s".', __METHOD__, InlineServiceConfigurator::FACTORY, ReferenceConfigurator::FACTORY, $service instanceof AbstractConfigurator ? $service::FACTORY.'()' : get_debug_type($service), $i, $id));
            }
        }

        $alias = $this->alias($id, '');
        $alias->definition = $this->set($id)
            ->parent('')
            ->args($services)
            ->tag('container.stack')
            ->definition;

        return $alias;
    }

    /**
     * Registers a service.
     */
    final public function __invoke(string $id, ?string $class = null): ServiceConfigurator
    {
        return $this->set($id, $class);
    }

    public function __destruct()
    {
        $this->loader->registerAliasesForSinglyImplementedInterfaces();
    }
}
