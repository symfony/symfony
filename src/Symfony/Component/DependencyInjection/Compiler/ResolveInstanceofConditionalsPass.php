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

use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

/**
 * Applies instanceof conditionals to definitions.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ResolveInstanceofConditionalsPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        foreach ($container->getAutoconfiguredInstanceof() as $interface => $definition) {
            if ($definition->getArguments()) {
                throw new InvalidArgumentException(sprintf('Autoconfigured instanceof for type "%s" defines arguments but these are not supported and should be removed.', $interface));
            }
        }

        foreach ($container->getDefinitions() as $id => $definition) {
            $container->setDefinition($id, $this->processDefinition($container, $id, $definition));
        }
    }

    private function processDefinition(ContainerBuilder $container, string $id, Definition $definition): Definition
    {
        $instanceofConditionals = $definition->getInstanceofConditionals();
        $autoconfiguredInstanceof = $definition->isAutoconfigured() ? $container->getAutoconfiguredInstanceof() : [];
        if (!$instanceofConditionals && !$autoconfiguredInstanceof) {
            return $definition;
        }

        if (!$class = $container->getParameterBag()->resolveValue($definition->getClass())) {
            return $definition;
        }

        $conditionals = $this->mergeConditionals($autoconfiguredInstanceof, $instanceofConditionals, $container);

        $definition->setInstanceofConditionals([]);
        $shared = null;
        $instanceofTags = [];
        $instanceofCalls = [];
        $instanceofBindings = [];
        $reflectionClass = null;
        $parent = $definition instanceof ChildDefinition ? $definition->getParent() : null;

        foreach ($conditionals as $interface => $instanceofDefs) {
            if ($interface !== $class && !(null === $reflectionClass ? $reflectionClass = ($container->getReflectionClass($class, false) ?: false) : $reflectionClass)) {
                continue;
            }

            if ($interface !== $class && !is_subclass_of($class, $interface)) {
                continue;
            }

            foreach ($instanceofDefs as $key => $instanceofDef) {
                /** @var ChildDefinition $instanceofDef */
                $instanceofDef = clone $instanceofDef;
                $instanceofDef->setAbstract(true)->setParent($parent ?: '.abstract.instanceof.'.$id);
                $parent = '.instanceof.'.$interface.'.'.$key.'.'.$id;
                $container->setDefinition($parent, $instanceofDef);
                $instanceofTags[] = $instanceofDef->getTags();
                $instanceofBindings = $instanceofDef->getBindings() + $instanceofBindings;

                foreach ($instanceofDef->getMethodCalls() as $methodCall) {
                    $instanceofCalls[] = $methodCall;
                }

                $instanceofDef->setTags([]);
                $instanceofDef->setMethodCalls([]);
                $instanceofDef->setBindings([]);

                if (isset($instanceofDef->getChanges()['shared'])) {
                    $shared = $instanceofDef->isShared();
                }
            }
        }

        if ($parent) {
            $bindings = $definition->getBindings();
            $abstract = $container->setDefinition('.abstract.instanceof.'.$id, $definition);
            $definition->setBindings([]);
            $definition = serialize($definition);

            if (Definition::class === \get_class($abstract)) {
                // cast Definition to ChildDefinition
                $definition = substr_replace($definition, '53', 2, 2);
                $definition = substr_replace($definition, 'Child', 44, 0);
            }
            /** @var ChildDefinition $definition */
            $definition = unserialize($definition);
            $definition->setParent($parent);

            if (null !== $shared && !isset($definition->getChanges()['shared'])) {
                $definition->setShared($shared);
            }

            // Don't add tags to service decorators
            if (null === $definition->getDecoratedService()) {
                $i = \count($instanceofTags);
                while (0 <= --$i) {
                    foreach ($instanceofTags[$i] as $k => $v) {
                        foreach ($v as $v) {
                            if ($definition->hasTag($k) && \in_array($v, $definition->getTag($k))) {
                                continue;
                            }
                            $definition->addTag($k, $v);
                        }
                    }
                }
            }

            $definition->setMethodCalls(array_merge($instanceofCalls, $definition->getMethodCalls()));
            $definition->setBindings($bindings + $instanceofBindings);

            // reset fields with "merge" behavior
            $abstract
                ->setBindings([])
                ->setArguments([])
                ->setMethodCalls([])
                ->setDecoratedService(null)
                ->setTags([])
                ->setAbstract(true);
        }

        return $definition;
    }

    private function mergeConditionals(array $autoconfiguredInstanceof, array $instanceofConditionals, ContainerBuilder $container): array
    {
        // make each value an array of ChildDefinition
        $conditionals = array_map(function ($childDef) { return [$childDef]; }, $autoconfiguredInstanceof);

        foreach ($instanceofConditionals as $interface => $instanceofDef) {
            // make sure the interface/class exists (but don't validate automaticInstanceofConditionals)
            if (!$container->getReflectionClass($interface)) {
                throw new RuntimeException(sprintf('"%s" is set as an "instanceof" conditional, but it does not exist.', $interface));
            }

            if (!isset($autoconfiguredInstanceof[$interface])) {
                $conditionals[$interface] = [];
            }

            $conditionals[$interface][] = $instanceofDef;
        }

        return $conditionals;
    }
}
