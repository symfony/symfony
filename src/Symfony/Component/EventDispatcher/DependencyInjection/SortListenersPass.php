<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\SortedListeners;

/**
 * Moves the "addListener" calls of every dispatcher to its constructor, sorted by priority.
 *
 * This pass runs last, so that the passes reading those calls still find them.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class SortListenersPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach ($container->findTaggedServiceIds('event_dispatcher.dispatcher') as $id => $tags) {
            $definition = $container->getDefinition($id);

            if ($definition->isAbstract() || $definition->getArguments()) {
                continue;
            }

            $class = $definition->getClass();

            if (EventDispatcher::class !== ($class ? $container->getParameterBag()->resolveValue($class) : null)) {
                continue;
            }

            $listeners = new SortedListeners();
            $calls = [];

            foreach ($definition->getMethodCalls() as $call) {
                if ('addListener' === $call[0] && \is_string($call[1][0] ?? null)) {
                    $listeners->add($call[1][0], $call[1][1], $call[1][2] ?? 0);
                } else {
                    $calls[] = $call;
                }
            }

            if ($listeners->isEmpty()) {
                continue;
            }

            $definition->setMethodCalls($calls);
            $definition->setArguments([$listeners->toArray()]);
        }
    }
}
