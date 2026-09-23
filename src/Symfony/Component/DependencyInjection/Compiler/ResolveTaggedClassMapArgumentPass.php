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

use Symfony\Component\DependencyInjection\Argument\TaggedClassMapArgument;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * Resolves all TaggedClassMapArgument arguments.
 *
 * Based on {@see PriorityTaggedServiceTrait::findAndSortTaggedServices()}, but resolves
 * tagged resources to their classes instead of services to references.
 */
final class ResolveTaggedClassMapArgumentPass extends AbstractRecursivePass
{
    protected bool $skipScalars = true;

    protected function processValue(mixed $value, bool $isRoot = false): mixed
    {
        if (!$value instanceof TaggedClassMapArgument) {
            return parent::processValue($value, $isRoot);
        }

        $indexAttribute = $value->getIndexAttribute();
        $exclude = $value->getExclude();
        $parameterBag = $this->container->getParameterBag();
        $resources = [];
        $classById = [];
        $constrained = false;

        foreach ($this->container->findTaggedResourceIds($value->getTag(), false) as $resourceId => $attributes) {
            $definition = $this->container->getDefinition($resourceId);

            if ($definition->isAbstract()) {
                continue;
            }

            $class = $definition->getClass();

            if (\in_array($class, $exclude, true)) {
                continue;
            }

            $defaultIndex = $defaultPriority = null;
            $defaultConstraints = [];
            $firstAttribute = true;
            $classById[$resourceId] = $class;
            $phpAttributes = $definition->isAutoconfigured() && !$definition->hasTag('container.ignore_attributes') ? $this->container->getReflectionClass($class)?->getAttributes(AsTaggedItem::class) : [];

            foreach ($phpAttributes ??= [] as $i => $attribute) {
                $attribute = $attribute->newInstance();
                $phpAttributes[$i] = [
                    'priority' => $attribute->priority,
                    'before' => $attribute->before,
                    'after' => $attribute->after,
                    $indexAttribute => $attribute->index,
                ];
                if ($firstAttribute) {
                    $firstAttribute = false;
                    $defaultPriority = $attribute->priority;
                    $defaultIndex = $attribute->index;
                }
                $defaultConstraints['before'] ??= $attribute->before;
                $defaultConstraints['after'] ??= $attribute->after;
            }
            if (1 >= \count($phpAttributes)) {
                $phpAttributes = [];
            }

            $attributes = array_values($attributes);
            for ($i = 0; $i < \count($attributes); ++$i) {
                if (!($attribute = $attributes[$i]) && $phpAttributes) {
                    array_splice($attributes, $i--, 1, $phpAttributes);
                    continue;
                }

                // null stays null: a resource that declared no priority is placed by its "before"/"after" constraints
                $declaredPriority = $attribute['priority'] ?? $defaultPriority;
                $index = isset($attribute[$indexAttribute]) ? $parameterBag->resolveValue($attribute[$indexAttribute]) : ($defaultIndex ?? $class);

                $constraints = [];
                foreach (['before', 'after'] as $direction) {
                    $targets = \array_key_exists($direction, $attribute) ? $attribute[$direction] : ($defaultConstraints[$direction] ?? null);

                    if ($targets = (array) ($targets ?? [])) {
                        $constraints[$direction] = $targets;
                        $constrained = true;
                    }
                }

                $resources[] = [$declaredPriority ?? 0, $i, $index, $resourceId, $class, $constraints, $declaredPriority];
            }
        }

        uasort($resources, static fn ($a, $b) => $b[0] <=> $a[0] ?: $a[1] <=> $b[1]);

        if ($constrained) {
            $resources = PriorityTaggedServiceUtil::applyConstraints($resources, $classById, $value->getTag());
        }

        $classMap = [];
        foreach ($resources as [, , $index, , $class]) {
            $classMap[$index] ??= $class;
        }

        $value->setValues($classMap);

        return $value;
    }
}
