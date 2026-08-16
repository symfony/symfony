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
            $phpAttributes = $definition->isAutoconfigured() && !$definition->hasTag('container.ignore_attributes') ? $this->container->getReflectionClass($class)?->getAttributes(AsTaggedItem::class) : [];

            foreach ($phpAttributes ??= [] as $i => $attribute) {
                $attribute = $attribute->newInstance();
                $phpAttributes[$i] = [
                    'priority' => $attribute->priority,
                    $indexAttribute => $attribute->index,
                ];
                if (null === $defaultPriority) {
                    $defaultPriority = $attribute->priority ?? 0;
                    $defaultIndex = $attribute->index;
                }
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

                $priority = $attribute['priority'] ?? $defaultPriority ?? 0;
                $index = isset($attribute[$indexAttribute]) ? $parameterBag->resolveValue($attribute[$indexAttribute]) : ($defaultIndex ?? $class);

                $resources[] = [$priority, $i, $index, $class];
            }
        }

        uasort($resources, static fn ($a, $b) => $b[0] <=> $a[0] ?: $a[1] <=> $b[1]);

        $classMap = [];
        foreach ($resources as [, , $index, $class]) {
            $classMap[$index] ??= $class;
        }

        $value->setValues($classMap);

        return $value;
    }
}
