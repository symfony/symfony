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

use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Feeds the "worker_warmer" service with the services tagged with "kernel.worker_warmer".
 *
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
class WorkerWarmerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has('worker_warmer')) {
            return;
        }

        $warmers = $methods = [];
        foreach ($container->findTaggedServiceIds('kernel.worker_warmer', true) as $id => $tags) {
            $warmers[$id] = new Reference($id);
            foreach ($tags as $attributes) {
                $methods[$id][] = $attributes['method'] ?? '__invoke';
            }
        }

        if (!$warmers) {
            $container->removeDefinition('worker_warmer');

            return;
        }

        $container->findDefinition('worker_warmer')
            ->setArgument(0, new IteratorArgument($warmers))
            ->setArgument(1, $methods);
    }
}
