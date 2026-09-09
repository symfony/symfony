<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Drops the services that the container cannot wire, because what they depend on or serve is missing.
 *
 * @internal
 */
class RemoveMissingDependenciesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $droppedMiddleware = [];

        if (!$container->has('lock.factory')) {
            $container->removeDefinition('messenger.middleware.deduplicate_middleware');
            $container->removeDefinition('messenger.failure.release_deduplication_lock_on_failure_listener');
            $droppedMiddleware[] = 'deduplicate_middleware';
        }

        if (!$container->has('debug.stopwatch')) {
            $container->removeDefinition('messenger.middleware.traceable');
            $droppedMiddleware[] = 'traceable';
        }

        if (!$container->has('profiler')) {
            $container->removeDefinition('data_collector.messenger');
        }

        if (!$container->has('cache.app')) {
            $container->removeDefinition('cache.messenger.restart_workers_signal');
        }

        if (!$container->has('cache.messenger.restart_workers_signal')) {
            $container->removeDefinition('messenger.listener.stop_worker_on_restart_signal_listener');
            $container->removeDefinition('console.command.messenger_stop_workers');
        }

        if (!$container->has('console.command.messenger_consume_messages')) {
            $container->removeDefinition('messenger.listener.reset_services');
        }

        if (!$droppedMiddleware) {
            return;
        }

        foreach ($container->findTaggedServiceIds('messenger.bus') as $busId => $tags) {
            if (!$container->hasParameter($busMiddleware = $busId.'.middleware')) {
                continue;
            }

            $middleware = array_filter($container->getParameter($busMiddleware), static fn ($item) => !\in_array($item['id'], $droppedMiddleware, true));
            $container->setParameter($busMiddleware, array_values($middleware));
        }
    }
}
