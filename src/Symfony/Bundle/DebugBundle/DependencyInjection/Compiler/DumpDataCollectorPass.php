<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DebugBundle\DependencyInjection\Compiler;

use Symfony\Bundle\WebProfilerBundle\EventListener\WebDebugToolbarListener;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Registers the file link format for the {@link \Symfony\Component\HttpKernel\DataCollector\DumpDataCollector}.
 *
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 */
class DumpDataCollectorPass implements CompilerPassInterface
{
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
