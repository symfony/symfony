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
     * @param string|TaggedIteratorArgument $tagName
     *
     * @return Reference[]
     */
    private function findAndSortTaggedServices($tagName, ContainerBuilder $container): array
    {
        $services = [];

        $indexAttribute = $defaultIndexMethod = $needsIndexes = $defaultPriorityMethod = null;
        if ($tagName instanceof TaggedIteratorArgument) {
            $indexAttribute = $tagName->getIndexAttribute();
            $defaultIndexMethod = $tagName->getDefaultIndexMethod();
            $needsIndexes = $tagName->needsIndexes();
            $defaultPriorityMethod = $tagName->getDefaultPriorityMethod();
            $tagName = $tagName->getTag();
        }

        foreach ($container->findTaggedServiceIds($tagName, true) as $serviceId => $attributes) {
            $class = $container->getDefinition($serviceId)->getClass();
            $class = $container->getParameterBag()->resolveValue($class) ?: null;
            $defaultPriority = 0;
            $defaultIndex = null;

            if ($r = $container->getReflectionClass($class)) {
                $class = $r->name;

                if ($defaultPriorityMethod && $r->hasMethod($defaultPriorityMethod)) {
                    if (!($rm = $r->getMethod($defaultPriorityMethod))->isStatic()) {
                        throw new InvalidArgumentException(sprintf('Method "%s::%s()" should be static: tag "%s" on service "%s".', $class, $defaultPriorityMethod, $tagName, $serviceId));
                    }
                    if (!$rm->isPublic()) {
                        throw new InvalidArgumentException(sprintf('Method "%s::%s()" should be public: tag "%s" on service "%s".', $class, $defaultPriorityMethod, $tagName, $serviceId));
                    }

                    $defaultPriority = $rm->invoke(null);

                    if (!\is_int($defaultPriority)) {
                        throw new InvalidArgumentException(sprintf('Method "%s::%s()" should return an integer, got %s: tag "%s" on service "%s".', $class, $defaultPriorityMethod, \gettype($defaultPriority), $tagName, $serviceId));
                    }
                }

                if ($defaultIndexMethod && $r->hasMethod($defaultIndexMethod)) {
                    if (!($rm = $r->getMethod($defaultIndexMethod))->isStatic()) {
                        throw new InvalidArgumentException(sprintf('Method "%s::%s()" should be static: tag "%s" on service "%s" is missing "%s" attribute.', $class, $defaultIndexMethod, $tagName, $serviceId, $indexAttribute));
                    }
                    if (!$rm->isPublic()) {
                        throw new InvalidArgumentException(sprintf('Method "%s::%s()" should be public: tag "%s" on service "%s" is missing "%s" attribute.', $class, $defaultIndexMethod, $tagName, $serviceId, $indexAttribute));
                    }

                    $defaultIndex = $rm->invoke(null);

                    if (!\is_string($defaultIndex)) {
                        throw new InvalidArgumentException(sprintf('Method "%s::%s()" should return a string, got %s: tag "%s" on service "%s" is missing "%s" attribute.', $class, $defaultIndexMethod, \gettype($defaultIndex), $tagName, $serviceId, $indexAttribute));
                    }
                }
            }

            foreach ($attributes as $attribute) {
                $priority = isset($attribute['priority']) ? $attribute['priority'] : $defaultPriority;
                $index = $indexAttribute && isset($attribute[$indexAttribute]) ? $attribute[$indexAttribute] : $defaultIndex;

                if (!$index && !$needsIndexes) {
                    if ($indexAttribute) {
                        throw new InvalidArgumentException(sprintf('Method "%s::%s()" not found: tag "%s" on service "%s" is missing "%s" attribute.', $class, $defaultIndexMethod, $tagName, $serviceId, $indexAttribute));
                    }

                    $services[$priority][] = new Reference($serviceId);
                    continue;
                }

                if (!$r) {
                    throw new InvalidArgumentException(sprintf('Class "%s" used for service "%s" cannot be found.', $class, $serviceId));
                }

                if (!$index) {
                    $services[$priority][$serviceId] = new TypedReference($serviceId, $class);
                } else {
                    $services[$priority][$index] = new TypedReference($serviceId, $class, ContainerBuilder::EXCEPTION_ON_INVALID_REFERENCE, $index);
                }
            }
        }

        if ($services) {
            krsort($services);
            $services = array_merge(...$services);
        }

        return $services;
    }
}
