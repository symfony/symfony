<?php

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Adds tagged data_collector services to profiler service
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ProfilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (false === $container->hasDefinition('profiler')) {
            return;
        }

        $definition = $container->getDefinition('profiler');

        $collectors = array();
        $priorities = array();
        $templates = array();
        foreach ($container->findTaggedServiceIds('data_collector') as $id => $attributes) {
            $priorities[] = isset($attributes[0]['priority']) ? $attributes[0]['priority'] : 0;
            $collectors[] = $id;
            if (isset($attributes[0]['template'])) {
                $templates[] = $attributes[0]['template'];
            }
        }

        array_multisort($priorities, SORT_DESC, $collectors, SORT_DESC, $templates);
        foreach ($collectors as $collector) {
            $definition->addMethodCall('add', array(new Reference($collector)));
        }

        $container->setParameter('data_collector.templates', array_combine($collectors, $templates));
    }
}
