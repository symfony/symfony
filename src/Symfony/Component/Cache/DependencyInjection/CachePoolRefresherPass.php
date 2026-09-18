<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\DependencyInjection;

use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\RefreshableInterface;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
class CachePoolRefresherPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('cache.refresher')) {
            return;
        }

        $services = [];

        foreach ($container->findTaggedServiceIds('cache.pool') as $id => $tags) {
            $pool = $container->getDefinition($id);

            if ($pool->isAbstract() || !($tags[0]['refreshable'] ?? false)) {
                continue;
            }

            // a taggable pool carries the tag on its inner adapter while reads go through the
            // tag-aware one; refreshing the inner would make the next commit rotate every tag version
            $name = $tags[0]['name'] ?? $id;
            $target = $container->hasDefinition($name) ? $container->getDefinition($name) : $pool;
            $class = $container->getReflectionClass($target->getClass(), false);

            if ($class && !$class->implementsInterface(RefreshableInterface::class)) {
                throw new InvalidArgumentException(\sprintf('Cache pool "%s" is marked as refreshable but its class "%s" does not implement "%s".', $id, $target->getClass(), RefreshableInterface::class));
            }

            $services[$name] = new Reference($name);
        }

        $container->getDefinition('cache.refresher')->replaceArgument(0, new IteratorArgument($services));
    }
}
