<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\DebugBundle\DependencyInjection\Compiler;

use Symphony\Bundle\WebProfilerBundle\EventListener\WebDebugToolbarListener;
use Symphony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symphony\Component\DependencyInjection\ContainerBuilder;

/**
 * Registers the file link format for the {@link \Symphony\Component\HttpKernel\DataCollector\DumpDataCollector}.
 *
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 */
class DumpDataCollectorPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('data_collector.dump')) {
            return;
        }

        $definition = $container->getDefinition('data_collector.dump');

        if (!$container->hasParameter('web_profiler.debug_toolbar.mode') || WebDebugToolbarListener::DISABLED === $container->getParameter('web_profiler.debug_toolbar.mode')) {
            $definition->replaceArgument(3, null);
        }
    }
}
