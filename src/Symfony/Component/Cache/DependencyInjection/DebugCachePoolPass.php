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

use Symfony\Component\Cache\Adapter\DebugAdapter;
use Symfony\Component\Cache\Adapter\DebugTagAwareAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class DebugCachePoolPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->getParameter('cache.exception_on_save')) {
            return;
        }

        foreach ($container->findTaggedServiceIds('cache.pool') as $id => $tags) {
            $decoratablePool = $container->getDefinition($id);
            if ($decoratablePool->isAbstract()) {
                continue;
            }

            $decoratingClass = DebugAdapter::class;
            if (is_subclass_of($decoratablePool->getClass(), TagAwareAdapterInterface::class)) {
                $decoratingClass = DebugTagAwareAdapter::class;
            }
            $renamedServiceId = $id . '.inner_adapter';

            $decoratingPool = new Definition($decoratingClass, [$decoratablePool]);
            $decoratingPool->setArguments([new Reference($renamedServiceId)]);
            $decoratingPool->setDecoratedService($id, $renamedServiceId);

            $container->set($id, $decoratingPool);
        }
    }
}
