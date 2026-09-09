<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Drops the services that the container cannot wire, because what they depend on is missing.
 *
 * @internal
 */
class RemoveMissingDependenciesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has('cache.app')) {
            $container->removeDefinition('cache.http_client.pool');
            $container->removeDefinition('cache.http_client');
        }

        if (!$container->has('profiler')) {
            $container->removeDefinition('data_collector.http_client');
        }
    }
}
