<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symphony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symphony\Component\Cache\Adapter\TraceableAdapter;
use Symphony\Component\Cache\Adapter\TraceableTagAwareAdapter;
use Symphony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Definition;
use Symphony\Component\DependencyInjection\Reference;

/**
 * Inject a data collector to all the cache services to be able to get detailed statistics.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class CacheCollectorPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('data_collector.cache')) {
            return;
        }

        $collectorDefinition = $container->getDefinition('data_collector.cache');
        foreach ($container->findTaggedServiceIds('cache.pool') as $id => $attributes) {
            $definition = $container->getDefinition($id);
            if ($definition->isAbstract()) {
                continue;
            }

            $recorder = new Definition(is_subclass_of($definition->getClass(), TagAwareAdapterInterface::class) ? TraceableTagAwareAdapter::class : TraceableAdapter::class);
            $recorder->setTags($definition->getTags());
            $recorder->setPublic($definition->isPublic());
            $recorder->setArguments(array(new Reference($innerId = $id.'.recorder_inner')));

            $definition->setTags(array());
            $definition->setPublic(false);

            $container->setDefinition($innerId, $definition);
            $container->setDefinition($id, $recorder);

            // Tell the collector to add the new instance
            $collectorDefinition->addMethodCall('addInstance', array($id, new Reference($id)));
            $collectorDefinition->setPublic(false);
        }
    }
}
