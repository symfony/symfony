<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Removes the listener feeding the security data collector when the profiler
 * is not enabled, as its "kernel.event_subscriber" tag would otherwise keep
 * it, and the collector, alive in a production container.
 *
 * @internal
 */
final class RemoveSecurityDataCollectorListenerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('profiler')) {
            $container->removeDefinition('data_collector.security.listener');
        }
    }
}
