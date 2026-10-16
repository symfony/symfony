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
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author Alexander M. Turek <me@derrabus.de>
 */
class ResettableServicePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has('services_resetter')) {
            return;
        }

        $services = $methods = [];
        $hasNonShared = false;

        foreach ($container->findTaggedServiceIds('kernel.reset', true) as $id => $tags) {
            $def = $container->getDefinition($id);
            $isShared = $def->isShared();

            if ($isShared) {
                $services[$id] = new Reference($id, ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE);
            }

            foreach ($tags as $attributes) {
                if (!isset($attributes['method'])) {
                    throw new RuntimeException(\sprintf('Tag "kernel.reset" requires the "method" attribute to be set on service "%s".', $id));
                }

                if ('ignore' === ($attributes['on_invalid'] ?? null)) {
                    $attributes['method'] = '?'.$attributes['method'];
                }

                $onShutdown = $attributes['on_shutdown'] ?? null;
                if (null !== $onShutdown && !\in_array($onShutdown, ['flag', 'only'], true)) {
                    throw new RuntimeException(\sprintf('The "on_shutdown" attribute of tag "kernel.reset" on service "%s" must be "flag" or "only".', $id));
                }

                $method = [$attributes['method'] => $onShutdown];

                if ($isShared) {
                    $methods[$id][] = $method;
                } else {
                    $hasNonShared = true;
                    $def->addTag('container.tracked_for_reset', ['method' => $method]);
                }
            }
        }

        if (!$services && !$hasNonShared) {
            $container->removeAlias('services_resetter');
            $container->removeDefinition('services_resetter');

            return;
        }

        $resetter = $container->findDefinition('services_resetter');
        $resetter
            ->setArgument(0, new IteratorArgument($services))
            ->setArgument(1, $methods);

        if ($hasNonShared) {
            $resetter->setArgument(2, (new Definition(\WeakMap::class))
                ->setFactory([new Reference('service_container'), 'getResetMap'])
            );
        }
    }
}
