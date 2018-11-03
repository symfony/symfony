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
    private $dataCollectorCacheId;
    private $cachePoolTag;
    private $cachePoolRecorderInnerSuffix;

    public function __construct(string $dataCollectorCacheId = 'data_collector.cache', string $cachePoolTag = 'cache.pool', string $cachePoolRecorderInnerSuffix = '.recorder_inner')
    {
        $this->dataCollectorCacheId = $dataCollectorCacheId;
        $this->cachePoolTag = $cachePoolTag;
        $this->cachePoolRecorderInnerSuffix = $cachePoolRecorderInnerSuffix;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition($this->dataCollectorCacheId)) {
            return;
        }

        $collectorDefinition = $container->getDefinition($this->dataCollectorCacheId);
        foreach ($container->findTaggedServiceIds($this->cachePoolTag) as $id => $attributes) {
            $definition = $container->getDefinition($id);
            if ($definition->isAbstract()) {
                continue;
            }

            $recorder = new Definition(is_subclass_of($definition->getClass(), TagAwareAdapterInterface::class) ? TraceableTagAwareAdapter::class : TraceableAdapter::class);
            $recorder->setTags($definition->getTags());
            $recorder->setPublic($definition->isPublic());
            $recorder->setArguments(array(new Reference($innerId = $id.$this->cachePoolRecorderInnerSuffix)));

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
