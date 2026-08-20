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
use Symfony\Component\VarExporter\DeepCloner;

/**
 * Applies instanceof conditionals to definitions.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ResolveInstanceofConditionalsPass implements CompilerPassInterface
{
    /**
     * @var array<string, list<string>> lowercased canonical type name => rule keys as registered
     */
    private array $loadedRuleTypes = [];

    /**
     * @var array<string, true> rule keys whose type was not loaded when the pass started
     */
    private array $unresolvedRuleTypes = [];

    /**
     * @var array<string, array<string, ChildDefinition>> resolved class name => rules that can match it
     */
    private array $matchingRules = [];

    public function process(ContainerBuilder $container): void
    {
        try {
            foreach ($container->getAutoconfiguredInstanceof() as $interface => $definition) {
                if ($definition->getArguments()) {
                    throw new InvalidArgumentException(\sprintf('Autoconfigured instanceof for type "%s" defines arguments but these are not supported and should be removed.', $interface));
                }

                if (class_exists($interface, false) || interface_exists($interface, false)) {
                    $this->loadedRuleTypes[strtolower((new \ReflectionClass($interface))->name)][] = $interface;
                } else {
                    $this->unresolvedRuleTypes[$interface] = true;
                }
            }

            $tagsToKeep = [];

            if ($container->hasParameter('container.behavior_describing_tags')) {
                $tagsToKeep = $container->getParameter('container.behavior_describing_tags');
            }

            foreach ($container->getDefinitions() as $id => $definition) {
                $container->setDefinition($id, $this->processDefinition($container, $id, $definition, $tagsToKeep));
            }
        } finally {
            $this->loadedRuleTypes = $this->unresolvedRuleTypes = $this->matchingRules = [];
        }

        if ($container->hasParameter('container.behavior_describing_tags')) {
            $container->getParameterBag()->remove('container.behavior_describing_tags');
        }
    }

    private function processDefinition(ContainerBuilder $container, string $id, Definition $definition, array $tagsToKeep): Definition
    {
        $instanceofConditionals = $definition->getInstanceofConditionals();
        $autoconfiguredInstanceof = $definition->isAutoconfigured() ? $container->getAutoconfiguredInstanceof() : [];
        if (!$instanceofConditionals && !$autoconfiguredInstanceof) {
            return $definition;
        }

        if (!$class = $container->getParameterBag()->resolveValue($definition->getClass())) {
            return $definition;
        }

        $autoconfiguredInstanceof = $this->filterAutoconfigured($container, $class, $autoconfiguredInstanceof);
        $conditionals = $this->mergeConditionals($autoconfiguredInstanceof, $instanceofConditionals, $container);

        $definition->setInstanceofConditionals([]);
        $shared = null;
        $instanceofTags = [];
        $instanceofCalls = [];
        $instanceofBindings = [];
        $reflectionClass = null;
        $parent = $definition instanceof ChildDefinition ? $definition->getParent() : null;

        foreach ($conditionals as $interface => $instanceofDefs) {
            if ($interface !== $class && !($reflectionClass ??= $container->getReflectionClass($class, false) ?: false)) {
                continue;
            }

            if ($interface !== $class && !is_subclass_of($class, $interface)) {
                continue;
            }

            foreach ($instanceofDefs as $key => $instanceofDef) {
                /** @var ChildDefinition $instanceofDef */
                $instanceofDef = clone $instanceofDef;
                $instanceofDef->setAbstract(true)->setParent($parent ?: '.abstract.instanceof.'.$id);
                $parent = '.instanceof.'.strtr($interface, "\0\r\n", '---').'.'.$key.'.'.$id;
                $container->setDefinition($parent, $instanceofDef);
                $instanceofTags[] = [$interface, $instanceofDef->getTags()];
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
            $definition = (new DeepCloner($definition))->cloneAs(ChildDefinition::class);
            $definition->setParent($parent);

            if (null !== $shared && !isset($definition->getChanges()['shared'])) {
                $definition->setShared($shared);
            }

            // Tags are added from the most specific type to the least specific one
            if (1 < \count($instanceofTags)) {
                $depths = [];
                foreach ($instanceofTags as [$interface]) {
                    $depths[$interface] ??= \count(class_parents($interface) ?: []) + \count(class_implements($interface) ?: []);
                }
                uasort($instanceofTags, static fn ($a, $b) => $depths[$a[0]] <=> $depths[$b[0]]);
                $instanceofTags = array_values($instanceofTags);
            }

            // Don't add tags to service decorators
            $i = \count($instanceofTags);
            while (0 <= --$i) {
                [$interface, $tags] = $instanceofTags[$i];
                foreach ($tags as $k => $v) {
                    if (null === $definition->getDecoratedService() || $interface === $definition->getClass() || \in_array($k, $tagsToKeep, true)) {
                        foreach ($v as $v) {
                            if (self::isLazyTagAttributes($v)) {
                                if (!($r = $container->getReflectionClass($class, false)) || !self::canComputeTagAttributes($r, $v)) {
                                    continue;
                                }
                                $v = self::resolveTagAttributes($v, $class, $k, $interface);
                            }
                            if ($definition->hasTag($k) && \in_array($v, $definition->getTag($k), true)) {
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

        if ($definition->isSynthetic()) {
            // Ignore container.excluded tag on synthetic services
            $definition->clearTag('container.excluded');
        }

        return $definition;
    }

    private function mergeConditionals(array $autoconfiguredInstanceof, array $instanceofConditionals, ContainerBuilder $container): array
    {
        // make each value an array of ChildDefinition
        $conditionals = array_map(static fn ($childDef) => [$childDef], $autoconfiguredInstanceof);

        foreach ($instanceofConditionals as $interface => $instanceofDef) {
            // make sure the interface/class exists (but don't validate automaticInstanceofConditionals)
            if (!$container->getReflectionClass($interface)) {
                throw new RuntimeException(\sprintf('"%s" is set as an "instanceof" conditional, but it does not exist.', $interface));
            }

            if (!isset($autoconfiguredInstanceof[$interface])) {
                $conditionals[$interface] = [];
            }

            $conditionals[$interface][] = $instanceofDef;
        }

        return $conditionals;
    }

    /**
     * Narrows the autoconfiguration rules to those that can match the class; rules whose
     * type is not loaded yet are always kept so that the caller's is_subclass_of() check
     * decides them (e.g. nonexistent types, late-defined class aliases).
     */
    private function filterAutoconfigured(ContainerBuilder $container, string $class, array $autoconfiguredInstanceof): array
    {
        if (!$autoconfiguredInstanceof || !$this->loadedRuleTypes) {
            return $autoconfiguredInstanceof;
        }

        if (null !== $rules = $this->matchingRules[$class] ?? null) {
            return $rules;
        }

        $candidates = $this->unresolvedRuleTypes;

        // a rule keyed by the exact class name applies even when the class doesn't exist
        if (isset($autoconfiguredInstanceof[$class])) {
            $candidates[$class] = true;
        }

        if ($container->getReflectionClass($class, false)) {
            foreach (class_implements($class) ?: [] as $type) {
                foreach ($this->loadedRuleTypes[strtolower($type)] ?? [] as $ruleKey) {
                    $candidates[$ruleKey] = true;
                }
            }
            foreach (class_parents($class) ?: [] as $type) {
                foreach ($this->loadedRuleTypes[strtolower($type)] ?? [] as $ruleKey) {
                    $candidates[$ruleKey] = true;
                }
            }
        }

        // array_intersect_key() preserves the registration order of the rules
        return $this->matchingRules[$class] = array_intersect_key($autoconfiguredInstanceof, $candidates);
    }

    /**
     * Whether a tag attribute-set is a lazily-computed one to be resolved per concrete class: either a
     * [\Closure] (a closure wrapped in an array so it survives addTag()) or a [class-string, method] callable.
     */
    private static function isLazyTagAttributes(mixed $attributes): bool
    {
        if (!\is_array($attributes)) {
            return false;
        }

        if ([0] === array_keys($attributes) && $attributes[0] instanceof \Closure) {
            return true;
        }

        return [0, 1] === array_keys($attributes)
            && \is_string($attributes[0])
            && \is_string($attributes[1])
            && (class_exists($attributes[0]) || interface_exists($attributes[0]));
    }

    /**
     * Computes the attributes of a tag whose attribute-set is a wrapped closure or a [class-string, method] callable.
     */
    private static function resolveTagAttributes(array $attributes, string $class, string $tag, string $interface): array
    {
        if ($attributes[0] instanceof \Closure) {
            $resolved = $attributes[0]($class);
            $source = \sprintf('The closure passed to the "%s" tag of "%s"', $tag, $interface);
        } else {
            [$declaringClass, $method] = $attributes;
            if (!is_a($class, $declaringClass, true)) {
                throw new InvalidArgumentException(\sprintf('Cannot tag "%s" through "%s::%s()" because it is not a subtype of "%s".', $class, $declaringClass, $method, $declaringClass));
            }
            if (!method_exists($class, $method)) {
                throw new InvalidArgumentException(\sprintf('Cannot tag "%s" through "%s::%s()" because that method does not exist.', $class, $declaringClass, $method));
            }
            $resolved = $class::$method();
            $source = \sprintf('The "%s::%s()" method computing the "%s" tag attributes', $declaringClass, $method, $tag);
        }

        if (!\is_array($resolved)) {
            throw new InvalidArgumentException($source.\sprintf(' must return an array of attributes, "%s" returned.', get_debug_type($resolved)));
        }

        return $resolved;
    }

    /**
     * Whether lazily-computed attributes can be resolved for a class: being instantiable is not required
     * (private constructors and enums are fine), but a closure cannot run on an abstract class or an
     * interface, and a [class-string, method] callable cannot when the method resolves to an abstract
     * declaration there. Skipping beats throwing because base types legitimately match the instanceof rules.
     * Interfaces need their own check: ReflectionClass::isAbstract() misses those declaring no methods.
     */
    private static function canComputeTagAttributes(\ReflectionClass $r, array $attributes): bool
    {
        if ($r->isInterface()) {
            return false;
        }

        if ($attributes[0] instanceof \Closure) {
            return !$r->isAbstract();
        }

        return !$r->hasMethod($attributes[1]) || !$r->getMethod($attributes[1])->isAbstract();
    }
}
