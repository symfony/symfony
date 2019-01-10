<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\Cache\Adapter\TraceableAdapter;
use Symfony\Component\Cache\Adapter\TraceableTagAwareAdapter;
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
            $recorder->setArguments([new Reference($innerId = $id.'.recorder_inner')]);

            $definition->setTags([]);
            $definition->setPublic(false);

            if (method_exists($definition, 'getAutowiringTypes') && $types = $definition->getAutowiringTypes(false)) {
                $recorder->setAutowiringTypes($types);
                $definition->setAutowiringTypes([]);
            }

            $container->setDefinition($innerId, $definition);
            $container->setDefinition($id, $recorder);

            // Tell the collector to add the new instance
            $collectorDefinition->addMethodCall('addInstance', [$id, new Reference($id)]);
            $collectorDefinition->setPublic(false);
        }
    }
}
