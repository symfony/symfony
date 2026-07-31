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
            $defaultIndexMethod = $tagName->getDefaultIndexMethod();
            $needsIndexes = $tagName->needsIndexes();
            $defaultPriorityMethod = $tagName->getDefaultPriorityMethod() ?? 'getDefaultPriority';
            $exclude = array_merge($exclude, $tagName->getExclude());
            $tagName = $tagName->getTag();
        }

        $parameterBag = $container->getParameterBag();
        $services = [];

        foreach ($container->findTaggedServiceIds($tagName, true) as $serviceId => $attributes) {
            if (\in_array($serviceId, $exclude, true)) {
                continue;
            }

            $defaultPriority = $defaultAttributePriority = null;
            $defaultIndex = $defaultAttributeIndex = null;
            $definition = $container->getDefinition($serviceId);
            $class = $definition->getClass();
            $class = $container->getParameterBag()->resolveValue($class) ?: null;
            $reflector = null !== $class ? $container->getReflectionClass($class) : null;
            $phpAttributes = $definition->isAutoconfigured() && !$definition->hasTag('container.ignore_attributes') ? $reflector?->getAttributes(AsTaggedItem::class) : [];

            foreach ($phpAttributes ??= [] as $i => $attribute) {
                $attribute = $attribute->newInstance();
                $phpAttributes[$i] = [
                    'priority' => $attribute->priority,
                    $indexAttribute ?? '' => $attribute->index,
                ];
                if (null === $defaultAttributePriority) {
                    $defaultAttributePriority = $attribute->priority ?? 0;
                    $defaultAttributeIndex = $attribute->index;
                }
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
                $priority ??= $defaultPriority ??= 0;

                if (null === $indexAttribute && !$defaultIndexMethod && !$needsIndexes) {
                    $services[] = [$priority, $i, null, $serviceId, null];
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

                $services[] = [$priority, $i, $index, $serviceId, $class];
            }
        }

        uasort($services, static fn ($a, $b) => $b[0] <=> $a[0] ?: $a[1] <=> $b[1]);

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
