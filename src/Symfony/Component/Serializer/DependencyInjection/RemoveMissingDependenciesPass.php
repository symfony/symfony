<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\DependencyInjection;

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
        // FrameworkBundle aliases "error_renderer" to the default renderer; the serializer-aware one
        // wins when it is available. That used to be a matter of load order inside a single extension,
        // which no longer decides anything now that the two live in different ones.
        if ($container->hasAlias('error_renderer.serializer')) {
            $container->setAlias('error_renderer', 'error_renderer.serializer');
        }

        if (!$container->has('translator')) {
            $container->removeDefinition('serializer.normalizer.translatable');
        }

        if (!$container->has('profiler')) {
            $container->removeDefinition('serializer.data_collector');
            $container->removeDefinition('debug.serializer');
        }

        if (!$container->has('cache.system')) {
            $container->removeDefinition('cache.serializer');
            $container->removeDefinition('serializer.mapping.cache_class_metadata_factory');
        }
    }
}
