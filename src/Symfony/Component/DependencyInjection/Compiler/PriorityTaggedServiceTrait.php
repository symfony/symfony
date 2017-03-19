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
     * we should not use this class.
     *
     * @see https://bugs.php.net/bug.php?id=53710
     * @see https://bugs.php.net/bug.php?id=60926
     *
     * @param string           $tagName
     * @param ContainerBuilder $container
     *
     * @return Reference[]
     */
    private function findAndSortTaggedServices($tagName, ContainerBuilder $container)
    {
        $services = array();

        foreach ($container->findTaggedServiceIds($tagName) as $serviceId => $tags) {
            foreach ($tags as $attributes) {
                $priority = isset($attributes['priority']) ? $attributes['priority'] : 0;
                $services[$priority][] = new Reference($serviceId);
            }
        }

        if ($services) {
            krsort($services);
            $services = call_user_func_array('array_merge', $services);
        }

        return $services;
    }
}
