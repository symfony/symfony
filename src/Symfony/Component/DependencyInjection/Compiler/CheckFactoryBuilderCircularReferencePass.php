<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Detects cycles where a service's factory is `[$b, $method]`, with $b either an
 * inlined definition or a reference to another service, and $b's own
 * properties/method calls/configurator transitively require that same service
 * through constructor references.
 *
 * The soft-circular instantiation pattern relies on storing the service's instance
 * before deferred edges fire, but a factory consumes the builder's state at call
 * time: deferring $b's setup until after `$b->method(...)` would feed the factory
 * an unconfigured builder. The cycle is unresolvable; bail out instead of letting
 * the dumper produce silently wrong code or `ContainerBuilder::createService()`
 * silently return a half-built instance.
 */
class CheckFactoryBuilderCircularReferencePass implements CompilerPassInterface
{
    private ContainerBuilder $container;
    private array $visited;
    private array $path;
    private string $currentId;

    public function process(ContainerBuilder $container): void
    {
        $this->container = $container;

        try {
            foreach ($container->getDefinitions() as $id => $definition) {
                $factory = $definition->getFactory();
                if (!\is_array($factory)) {
                    continue;
                }

                $builderId = null;

                if ($factory[0] instanceof Reference) {
                    $builderId = (string) $factory[0];
                    while ($container->hasAlias($builderId)) {
                        $builderId = (string) $container->getAlias($builderId);
                    }

                    if (!$container->hasDefinition($builderId)) {
                        continue;
                    }

                    $builder = $container->getDefinition($builderId);
                } elseif ($factory[0] instanceof Definition) {
                    $builder = $factory[0];
                } else {
                    continue;
                }

                if (!$builder->getMethodCalls() && !$builder->getProperties() && null === $builder->getConfigurator()) {
                    continue;
                }

                $this->currentId = $id;
                $this->visited = [$id => true];
                $this->path = null === $builderId ? [$id] : [$id, $builderId];

                $setup = [$builder->getProperties(), $builder->getMethodCalls(), $builder->getConfigurator()];
                if ($this->setupReferencesCurrent($setup)) {
                    $this->path[] = $id;

                    throw new ServiceCircularReferenceException($id, $this->path);
                }
            }
        } finally {
            unset($this->container, $this->visited, $this->path, $this->currentId);
        }
    }

    private function setupReferencesCurrent(mixed $value): bool
    {
        if (\is_array($value)) {
            foreach ($value as $v) {
                if ($this->setupReferencesCurrent($v)) {
                    return true;
                }
            }

            return false;
        }

        if ($value instanceof Reference) {
            $id = (string) $value;
            while ($this->container->hasAlias($id)) {
                $id = (string) $this->container->getAlias($id);
            }

            if ($id === $this->currentId) {
                return true;
            }

            if (isset($this->visited[$id]) || !$this->container->hasDefinition($id)) {
                return false;
            }

            $def = $this->container->getDefinition($id);

            // Lazy services break the eager-construction chain through a proxy.
            if ($def->isLazy() || $def->isSynthetic()) {
                return false;
            }

            $this->visited[$id] = true;
            $this->path[] = $id;

            // Only constructor edges propagate the "needed during construction" reachability.
            if ($this->setupReferencesCurrent([$def->getArguments(), $def->getFactory()])) {
                return true;
            }

            array_pop($this->path);

            return false;
        }

        if ($value instanceof Definition) {
            // Inlined sub-definition: every part of it runs while the outer service
            // is still being constructed, so walk its full structure.
            return $this->setupReferencesCurrent([
                $value->getArguments(),
                $value->getFactory(),
                $value->getProperties(),
                $value->getMethodCalls(),
                $value->getConfigurator(),
            ]);
        }

        return false;
    }
}
