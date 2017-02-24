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

use Symfony\Component\Cache\Adapter\TraceableAdapter;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
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
            if ($container->getDefinition($id)->isAbstract()) {
                continue;
            }

            $container->register($id.'.recorder', TraceableAdapter::class)
                ->setDecoratedService($id)
                ->addArgument(new Reference($id.'.recorder.inner'))
                ->setPublic(false);

            // Tell the collector to add the new instance
            $collectorDefinition->addMethodCall('addInstance', array($id, new Reference($id)));
        }
    }
}
