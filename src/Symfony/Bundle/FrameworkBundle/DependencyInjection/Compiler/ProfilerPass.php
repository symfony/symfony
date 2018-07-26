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

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Adds tagged data_collector services to profiler service.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ProfilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (false === $container->hasDefinition('profiler')) {
            return;
        }

        $definition = $container->getDefinition('profiler');

        $collectors = new \SplPriorityQueue();
        $order = PHP_INT_MAX;
        foreach ($container->findTaggedServiceIds('data_collector') as $id => $attributes) {
            $priority = isset($attributes[0]['priority']) ? $attributes[0]['priority'] : 0;
            $template = null;

            if (isset($attributes[0]['template'])) {
                if (!isset($attributes[0]['id'])) {
                    throw new \InvalidArgumentException(sprintf('Data collector service "%s" must have an id attribute in order to specify a template', $id));
                }
                $template = array($attributes[0]['id'], $attributes[0]['template']);
            }

            $collectors->insert(array($id, $template), array($priority, --$order));
        }

        $templates = array();
        foreach ($collectors as $collector) {
            $definition->addMethodCall('add', array(new Reference($collector[0])));
            $templates[$collector[0]] = $collector[1];
        }

        $container->setParameter('data_collector.templates', $templates);
    }
}
