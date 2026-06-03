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

use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author Rob Frawley 2nd <rmf@src.run>
 */
class CachePoolPrunerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('console.command.cache_pool_prune')) {
            return;
        }

        $services = [];

        foreach ($container->findTaggedServiceIds('cache.pool') as $id => $tags) {
            if ($tags[0]['pruneable'] ?? $container->getReflectionClass($container->getDefinition($id)->getClass(), false)?->implementsInterface(PruneableInterface::class) ?? false) {
                $services[$tags[0]['name'] ?? $id] = new Reference($id);
            }
        }

        $container->getDefinition('console.command.cache_pool_prune')->replaceArgument(0, new IteratorArgument($services));
    }
}
