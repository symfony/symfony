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
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Collects the services tagged "kernel.close" for "services_closer", which closes them when the kernel shuts down.
 */
class ClosableServicePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has('services_closer')) {
            return;
        }

        $services = $methods = [];

        foreach ($container->findTaggedServiceIds('kernel.close', true) as $id => $tags) {
            if (!$container->getDefinition($id)->isShared()) {
                throw new InvalidArgumentException(\sprintf('Service "%s" cannot be tagged "kernel.close" because it is not shared: the container keeps no instance of it to close.', $id));
            }

            // a service that was never used is not instantiated just to be closed
            $services[$id] = new Reference($id, ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE);

            foreach ($tags as $attributes) {
                $method = $attributes['method'] ?? 'close';
                $methods[$id][] = 'ignore' === ($attributes['on_invalid'] ?? null) ? '?'.$method : $method;
            }
        }

        if (!$services) {
            $container->removeDefinition('services_closer');

            return;
        }

        $container->findDefinition('services_closer')
            ->setArgument(0, new IteratorArgument($services))
            ->setArgument(1, $methods);
    }
}
