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

use Symfony\Bundle\FrameworkBundle\DataCollector\TemplateAwareDataCollectorInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Adds tagged data_collector services to profiler service.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ProfilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (false === $container->hasDefinition('profiler')) {
            return;
        }

        $definition = $container->getDefinition('profiler');

        $collectors = new \SplPriorityQueue();
        $order = \PHP_INT_MAX;
        foreach ($container->findTaggedServiceIds('data_collector', true) as $id => $attributes) {
            $priority = $attributes[0]['priority'] ?? 0;
            $template = null;

            $collectorClass = $container->findDefinition($id)->getClass();
            if (isset($attributes[0]['template']) || is_subclass_of($collectorClass, TemplateAwareDataCollectorInterface::class)) {
                $idForTemplate = $attributes[0]['id'] ?? $collectorClass;
                if (!$idForTemplate) {
                    throw new InvalidArgumentException(\sprintf('Data collector service "%s" must have an id attribute in order to specify a template.', $id));
                }
                $template = [$idForTemplate, $attributes[0]['template'] ?? $collectorClass::getTemplate()];
            }

            $collectors->insert([$id, $template], [$priority, --$order]);
        }

        $templates = [];
        foreach ($collectors as $collector) {
            $definition->addMethodCall('add', [new Reference($collector[0])]);
            $templates[$collector[0]] = $collector[1];
        }

        $container->setParameter('data_collector.templates', $templates);
    }
}
