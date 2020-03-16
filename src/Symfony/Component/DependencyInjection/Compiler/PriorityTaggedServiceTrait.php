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
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

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
     * @param string|TaggedIteratorArgument $tagName
     *
     * @return Reference[]
     */
    private function findAndSortTaggedServices($tagName, ContainerBuilder $container): array
    {
        $indexAttribute = $defaultIndexMethod = $needsIndexes = $defaultPriorityMethod = null;

        if ($tagName instanceof TaggedIteratorArgument) {
            $indexAttribute = $tagName->getIndexAttribute();
            $defaultIndexMethod = $tagName->getDefaultIndexMethod();
            $needsIndexes = $tagName->needsIndexes();
            $defaultPriorityMethod = $tagName->getDefaultPriorityMethod();
            $tagName = $tagName->getTag();
        }

        $i = 0;
        $services = [];

        foreach ($container->findTaggedServiceIds($tagName, true) as $serviceId => $attributes) {
            $defaultPriority = null;
            $defaultIndex = null;

            foreach ($attributes as $attribute) {
                $index = $priority = null;

                if (isset($attribute['priority'])) {
                    $priority = $attribute['priority'];
                } elseif (null === $defaultPriority && $defaultPriorityMethod) {
                    $defaultPriority = PriorityTaggedServiceUtil::getDefaultPriority($container, $serviceId, $defaultPriorityMethod, $tagName);
                }
                $priority = $priority ?? $defaultPriority ?? $defaultPriority = 0;

                if (null === $indexAttribute && !$needsIndexes) {
                    $services[] = [$priority, ++$i, null, $serviceId];
                    continue 2;
                }

                if (null !== $indexAttribute && isset($attribute[$indexAttribute])) {
                    $index = $attribute[$indexAttribute];
                } elseif (null === $defaultIndex && $defaultIndexMethod) {
                    $defaultIndex = PriorityTaggedServiceUtil::getDefaultIndex($container, $serviceId, $defaultIndexMethod, $tagName, $indexAttribute);
                }
                $index = $index ?? $defaultIndex ?? $defaultIndex = $serviceId;

                $services[] = [$priority, ++$i, $index, $serviceId];
            }
        }

        uasort($services, static function ($a, $b) { return $b[0] <=> $a[0] ?: $a[1] <=> $b[1]; });

        $refs = [];
        foreach ($services as [, , $index, $serviceId]) {
            if (null === $index) {
                $refs[] = new Reference($serviceId);
            } else {
                $refs[$index] = new Reference($serviceId);
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
     * Gets the index defined by the default index method.
     */
    public static function getDefaultIndex(ContainerBuilder $container, string $serviceId, string $defaultIndexMethod, string $tagName, string $indexAttribute): ?string
    {
        $class = $container->getDefinition($serviceId)->getClass();
        $class = $container->getParameterBag()->resolveValue($class) ?: null;

        if (!($r = $container->getReflectionClass($class)) || !$r->hasMethod($defaultIndexMethod)) {
            return null;
        }

        if (!($rm = $r->getMethod($defaultIndexMethod))->isStatic()) {
            throw new InvalidArgumentException(sprintf('Either method "%s::%s()" should be static or tag "%s" on service "%s" is missing attribute "%s".', $class, $defaultIndexMethod, $tagName, $serviceId, $indexAttribute));
        }

        if (!$rm->isPublic()) {
            throw new InvalidArgumentException(sprintf('Either method "%s::%s()" should be public or tag "%s" on service "%s" is missing attribute "%s".', $class, $defaultIndexMethod, $tagName, $serviceId, $indexAttribute));
        }

        $defaultIndex = $rm->invoke(null);

        if (!\is_string($defaultIndex)) {
            throw new InvalidArgumentException(sprintf('Either method "%s::%s()" should return a string (got "%s") or tag "%s" on service "%s" is missing attribute "%s".', $class, $defaultIndexMethod, \gettype($defaultIndex), $tagName, $serviceId, $indexAttribute));
        }

        return $defaultIndex;
    }

    /**
     * Gets the priority defined by the default priority method.
     */
    public static function getDefaultPriority(ContainerBuilder $container, string $serviceId, string $defaultPriorityMethod, string $tagName): ?int
    {
        $class = $container->getDefinition($serviceId)->getClass();
        $class = $container->getParameterBag()->resolveValue($class) ?: null;

        if (!($r = $container->getReflectionClass($class)) || !$r->hasMethod($defaultPriorityMethod)) {
            return null;
        }

        if (!($rm = $r->getMethod($defaultPriorityMethod))->isStatic()) {
            throw new InvalidArgumentException(sprintf('Either method "%s::%s()" should be static or tag "%s" on service "%s" is missing attribute "priority".', $class, $defaultPriorityMethod, $tagName, $serviceId));
        }

        if (!$rm->isPublic()) {
            throw new InvalidArgumentException(sprintf('Either method "%s::%s()" should be public or tag "%s" on service "%s" is missing attribute "priority".', $class, $defaultPriorityMethod, $tagName, $serviceId));
        }

        $defaultPriority = $rm->invoke(null);

        if (!\is_int($defaultPriority)) {
            throw new InvalidArgumentException(sprintf('Method "%s::%s()" should return an integer (got "%s") or tag "%s" on service "%s" is missing attribute "priority".', $class, $defaultPriorityMethod, \gettype($defaultPriority), $tagName, $serviceId));
        }

        return $defaultPriority;
    }
}
