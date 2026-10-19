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

use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\Cache\Adapter\TraceableAdapter;
use Symfony\Component\Cache\Adapter\TraceableTagAwareAdapter;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Inject a data collector to all the cache services to be able to get detailed statistics.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class CacheCollectorPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('data_collector.cache')) {
            return;
        }

        foreach ($container->findTaggedServiceIds('cache.pool') as $id => $attributes) {
            $poolName = $attributes[0]['name'] ?? $id;

            $this->addToCollector($id, $poolName, $container);
        }
    }

    private function addToCollector(string $id, string $name, ContainerBuilder $container): void
    {
        $definition = $container->getDefinition($id);
        if ($definition->isAbstract()) {
            return;
        }

        $collectorDefinition = $container->getDefinition('data_collector.cache');
        $recorder = new Definition(is_subclass_of($definition->getClass(), TagAwareAdapterInterface::class) ? TraceableTagAwareAdapter::class : TraceableAdapter::class);
        $recorder->setTags($definition->getTags());
        $recorder->setPublic($definition->isPublic());
        $recorder->setArguments([new Reference($innerId = $id.'.recorder_inner'), new Reference('profiler.is_disabled_state_checker', ContainerBuilder::IGNORE_ON_INVALID_REFERENCE)]);

        foreach ($definition->getMethodCalls() as [$method, $args]) {
            if ('setCallbackWrapper' !== $method || !$args[0] instanceof Definition || !($args[0]->getArguments()[2] ?? null) instanceof Definition) {
                continue;
            }
            if ([new Reference($id), 'setCallbackWrapper'] == $args[0]->getArguments()[2]->getFactory()) {
                $args[0]->getArguments()[2]->setFactory([new Reference($innerId), 'setCallbackWrapper']);
            }
        }

        $definition->setTags(array_intersect_key($definition->getTags(), ['kernel.reset' => true]));
        $definition->setPublic(false);

        $container->setDefinition($innerId, $definition);
        $container->setDefinition($id, $recorder);

        // Tell the collector to add the new instance
        $collectorDefinition->addMethodCall('addInstance', [$name, new Reference($id)]);

        $this->addToResetter($id, $innerId, $container);
    }

    /**
     * Registers the inner pool with the services resetter, as the recorder forwards the shutdown flag only to pools that implement ShutdownInterface.
     *
     * ResettableServicePass ran before this pass, so this updates what it produced.
     */
    private function addToResetter(string $id, string $innerId, ContainerBuilder $container): void
    {
        if (!$container->has('services_resetter')) {
            return;
        }

        $resetter = $container->findDefinition('services_resetter');
        $services = $resetter->getArguments()[0] ?? null;
        $methods = $resetter->getArguments()[1] ?? null;

        if (!$services instanceof IteratorArgument || !isset($methods[$id])) {
            return;
        }

        $services->setValues($services->getValues() + [$innerId => new Reference($innerId, ContainerBuilder::IGNORE_ON_UNINITIALIZED_REFERENCE)]);
        $methods[$innerId] = $methods[$id];
        $resetter->replaceArgument(1, $methods);
    }
}
