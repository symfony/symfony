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

use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\CompiledEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Turns the "addListener" calls of a dispatcher into the map a CompiledEventDispatcher takes.
 *
 * This pass runs last, so that the passes reading those calls still find them.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class CompileListenersPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach ($container->findTaggedServiceIds('event_dispatcher.dispatcher') as $id => $tags) {
            $definition = $container->getDefinition($id);

            if ($definition->isAbstract()) {
                continue;
            }

            // a decorator took the id over: the listeners belong to the dispatcher it decorates
            $dispatcher = $definition;

            while (null !== $innerId = $dispatcher->innerServiceId) {
                if (!$container->hasDefinition($innerId)) {
                    continue 2;
                }

                $dispatcher = $container->getDefinition($innerId);

                if ($calls = $dispatcher->getMethodCalls()) {
                    $container->log($this, \sprintf('Not compiling the listeners of "%s": "%s()" is called on "%s".', $id, $calls[0][0], $innerId));

                    continue 2;
                }
            }

            if ($dispatcher->getArguments()) {
                continue;
            }

            $class = $dispatcher->getClass();

            if (EventDispatcher::class !== ($class ? $container->getParameterBag()->resolveValue($class) : null)) {
                continue;
            }

            $listeners = [];
            $references = [];

            foreach ($definition->getMethodCalls() as $call) {
                if ('addListener' !== $call[0]) {
                    $container->log($this, \sprintf('Not compiling the listeners of "%s": "%s()" is called on it.', $id, $call[0]));

                    continue 2;
                }

                [$eventName, $listener] = $call[1] + [null, null];

                if (!\is_string($eventName) || !\is_array($listener) || !($listener[0] ?? null) instanceof ServiceClosureArgument || !\is_string($listener[1] ?? null)) {
                    $container->log($this, \sprintf('Not compiling the listeners of "%s": one of them is not a lazy service.', $id));

                    continue 2;
                }

                $reference = $listener[0]->getValues()[0];
                $references[(string) $reference] = $reference;
                $listeners[$eventName][$call[1][2] ?? 0][] = [(string) $reference, $listener[1]];
            }

            if (!$listeners) {
                continue;
            }

            foreach ($listeners as &$byPriority) {
                krsort($byPriority);
            }
            unset($byPriority);

            $dispatcher->setClass(CompiledEventDispatcher::class);
            $dispatcher->setArguments([$listeners, new ServiceLocatorArgument($references)]);
            $definition->setMethodCalls([]);
        }
    }
}
