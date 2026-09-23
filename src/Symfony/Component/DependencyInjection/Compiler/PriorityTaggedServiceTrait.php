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

use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\TypedReference;

/**
 * Trait that allows a generic method to find and sort service by priority option in the tag.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
trait PriorityTaggedServiceTrait
{
    /**
     * Finds all services with the given tag name and order them by their priority.
     *
     * The order of additions must be respected for services having the same priority,
     * and knowing that the \SplPriorityQueue class does not respect the FIFO method,
     * we should not use that class.
     *
     * @see https://bugs.php.net/53710
     * @see https://bugs.php.net/60926
     *
     * @return Reference[]
     */
    private function findAndSortTaggedServices(string|TaggedIteratorArgument $tagName, ContainerBuilder $container, array $exclude = []): array
    {
        $indexAttribute = $defaultIndexMethod = $needsIndexes = $defaultPriorityMethod = null;

        if ($tagName instanceof TaggedIteratorArgument) {
            $indexAttribute = $tagName->getIndexAttribute();
            $defaultIndexMethod = $tagName->getDefaultIndexMethod(false);
            $needsIndexes = $tagName->needsIndexes();
            $defaultPriorityMethod = $tagName->getDefaultPriorityMethod(false) ?? 'getDefaultPriority';
            $exclude = array_merge($exclude, $tagName->getExclude());
            $tagName = $tagName->getTag();
        }

        $parameterBag = $container->getParameterBag();
        $services = [];
        $constrained = false;
        $classById = [];

        foreach ($container->findTaggedServiceIds($tagName, true) as $serviceId => $attributes) {
            if (\in_array($serviceId, $exclude, true)) {
                continue;
            }

            $defaultPriority = $defaultAttributePriority = null;
            $defaultIndex = $defaultAttributeIndex = null;
            $attributeConstraints = [];
            $firstAttribute = true;
            $indexes = [];
            $definition = $container->getDefinition($serviceId);
            $class = $definition->getClass();
            $class = $container->getParameterBag()->resolveValue($class) ?: null;
            $classById[$serviceId] = $class;
            $reflector = null !== $class ? $container->getReflectionClass($class) : null;
            $phpAttributes = $definition->isAutoconfigured() && !$definition->hasTag('container.ignore_attributes') ? $reflector?->getAttributes(AsTaggedItem::class) : [];

            foreach ($phpAttributes ??= [] as $i => $attribute) {
                $attribute = $attribute->newInstance();
                $phpAttributes[$i] = [
                    'priority' => $attribute->priority,
                    'before' => $attribute->before,
                    'after' => $attribute->after,
                    $indexAttribute ?? '' => $attribute->index,
                ];
                if ($firstAttribute) {
                    $firstAttribute = false;
                    $defaultAttributePriority = $attribute->priority;
                    $defaultAttributeIndex = $attribute->index;
                }
                $attributeConstraints['before'] ??= $attribute->before;
                $attributeConstraints['after'] ??= $attribute->after;
            }
            if (1 >= \count($phpAttributes)) {
                $phpAttributes = [];
            }

            // For decorated services, walk the decoration chain to find #[AsTaggedItem] on the original service
            $innerClass = null;
            $innerDef = $definition;
            while ($innerId = $innerDef->getTag('container.decorator')[0]['inner'] ?? null) {
                if (!$container->has($innerId)) {
                    break;
                }
                $innerDef = $container->findDefinition($innerId);
                $innerClass = $container->getParameterBag()->resolveValue($innerDef->getClass()) ?: null;
            }
            $innerReflector = null !== $innerClass ? $container->getReflectionClass($innerClass) : null;

            $attributes = array_values($attributes);
            for ($i = 0; $i < \count($attributes); ++$i) {
                if (!($attribute = $attributes[$i]) && $phpAttributes) {
                    array_splice($attributes, $i--, 1, $phpAttributes);
                    continue;
                }

                $index = $priority = null;

                if (isset($attribute['priority'])) {
                    $priority = $attribute['priority'];
                } elseif (null === $defaultPriority && $defaultPriorityMethod && $reflector) {
                    $defaultPriority = PriorityTaggedServiceUtil::getDefault($serviceId, $reflector, $defaultPriorityMethod, $tagName, 'priority') ?? $defaultAttributePriority;
                    if (null === $defaultPriority && null !== $innerReflector) {
                        $defaultPriority = PriorityTaggedServiceUtil::getDefault($serviceId, $innerReflector, $defaultPriorityMethod, $tagName, 'priority');
                    }
                }
                // null stays null: a service that declared no priority is placed by its "before"/"after" constraints
                $declaredPriority = $priority ?? $defaultPriority;
                $priority = $declaredPriority ?? 0;

                $entryConstraints = [];
                foreach (['before', 'after'] as $direction) {
                    $targets = \array_key_exists($direction, $attribute) ? $attribute[$direction] : ($attributeConstraints[$direction] ?? null);

                    if ($targets = (array) ($targets ?? [])) {
                        $entryConstraints[$direction] = $targets;
                        $constrained = true;
                    }
                }

                if (null === $indexAttribute && !$defaultIndexMethod && !$needsIndexes) {
                    $services[] = [$priority, $i, null, $serviceId, null, $entryConstraints, $declaredPriority];
                    continue 2;
                }

                if (null !== $indexAttribute && isset($attribute[$indexAttribute])) {
                    $index = $parameterBag->resolveValue($attribute[$indexAttribute]);
                }
                if (null === $index && null === $defaultIndex && $defaultPriorityMethod && $reflector) {
                    $defaultIndex = PriorityTaggedServiceUtil::getDefault($serviceId, $reflector, $defaultIndexMethod ?? 'getDefaultName', $tagName, $indexAttribute) ?? $defaultAttributeIndex;
                    if (null === $defaultIndex && null !== $innerReflector) {
                        $defaultIndex = PriorityTaggedServiceUtil::getDefault($serviceId, $innerReflector, $defaultIndexMethod ?? 'getDefaultName', $tagName, $indexAttribute);
                        if (null === $defaultIndex) {
                            foreach ($innerReflector->getAttributes(AsTaggedItem::class) as $innerAttr) {
                                $defaultIndex = $innerAttr->newInstance()->index;
                                break;
                            }
                        }
                    }
                }
                $index ??= $defaultIndex ??= $definition->getTag('container.decorator')[0]['id'] ?? $serviceId;

                if (isset($indexes[$index])) {
                    continue;
                }
                $indexes[$index] = true;

                $services[] = [$priority, $i, $index, $serviceId, $class, $entryConstraints, $declaredPriority];
            }
        }

        uasort($services, static fn ($a, $b) => $b[0] <=> $a[0] ?: $a[1] <=> $b[1]);

        if ($constrained) {
            $services = PriorityTaggedServiceUtil::applyConstraints($services, $classById, $tagName);
        }

        $refs = [];
        foreach ($services as [, , $index, $serviceId, $class]) {
            $reference = match (true) {
                !$class => new Reference($serviceId),
                $index === $serviceId => new TypedReference($serviceId, $class),
                default => new TypedReference($serviceId, $class, ContainerBuilder::EXCEPTION_ON_INVALID_REFERENCE, $index),
            };

            if (null === $index) {
                $refs[] = $reference;
            } else {
                $refs[$index] = $reference;
            }
        }

        return $refs;
    }
}

/**
 * @internal
 */
class PriorityTaggedServiceUtil
{
    /**
     * @param array<array{0: int, 1: int, 2: string|null, 3: string, 4: string|null, 5: array, 6: int|null}> $services
     * @param array<string, string|null>                                                                     $classById
     *
     * @return list<array{0: int, 1: int, 2: string|null, 3: string, 4: string|null, 5: array, 6: int|null}>
     */
    public static function applyConstraints(array $services, array $classById, string $tagName): array
    {
        $entries = [];
        $priorities = [];
        $aliases = [];
        $keysById = [];
        $constraints = [];

        // each tag gets its own key so that the other tags of a service keep the place the priority sort gave them
        foreach ($services as $service) {
            [, , , $serviceId, , $serviceConstraints, $priority] = $service;

            for ($n = 0, $key = $serviceId; isset($entries[$key]); ++$n) {
                $key = $serviceId.'#'.$n;
            }

            $entries[$key] = $service;
            $priorities[$key] = $priority;
            $keysById[$serviceId][] = $key;

            if ($serviceConstraints) {
                $constraints[$key] = $serviceConstraints;
            }

            if (null !== $class = $classById[$serviceId] ?? null) {
                $aliases[$class][] = $key;
            }
        }

        // a service id always designates its own tags, whatever class it happens to share a name with
        $aliases = $keysById + $aliases;

        try {
            $keys = array_keys(BeforeAfterSorter::sortWithPriorities($priorities, $constraints, $aliases));
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException(\sprintf('Invalid "before"/"after" constraints on tag "%s": ', $tagName).lcfirst($e->getMessage()), previous: $e);
        }

        $sorted = [];

        foreach ($keys as $key) {
            $sorted[] = $entries[$key];
        }

        return $sorted;
    }

    public static function getDefault(string $serviceId, \ReflectionClass $r, string $defaultMethod, string $tagName, ?string $indexAttribute): string|int|null
    {
        if ($r->isInterface() || !$r->hasMethod($defaultMethod)) {
            return null;
        }

        $class = $r->name;

        if (null !== $indexAttribute) {
            $service = $class !== $serviceId ? \sprintf('service "%s"', $serviceId) : 'on the corresponding service';
            $message = [\sprintf('Either method "%s::%s()" should ', $class, $defaultMethod), \sprintf(' or tag "%s" on %s is missing attribute "%s".', $tagName, $service, $indexAttribute)];
        } else {
            $message = [\sprintf('Method "%s::%s()" should ', $class, $defaultMethod), '.'];
        }

        if (!($rm = $r->getMethod($defaultMethod))->isStatic()) {
            throw new InvalidArgumentException(implode('be static', $message));
        }

        if (!$rm->isPublic()) {
            throw new InvalidArgumentException(implode('be public', $message));
        }

        trigger_deprecation('symfony/dependency-injection', '8.1', 'Calling "%s::%s()" to get the "%s" index is deprecated, use the #[AsTaggedItem] attribute instead.', $class, $defaultMethod, $indexAttribute);

        $default = $rm->invoke(null);

        if ('priority' === $indexAttribute) {
            if (!\is_int($default)) {
                throw new InvalidArgumentException(implode(\sprintf('return int (got "%s")', get_debug_type($default)), $message));
            }

            return $default;
        }

        if (\is_int($default)) {
            $default = (string) $default;
        }

        if (!\is_string($default)) {
            throw new InvalidArgumentException(implode(\sprintf('return string|int (got "%s")', get_debug_type($default)), $message));
        }

        return $default;
    }
}
