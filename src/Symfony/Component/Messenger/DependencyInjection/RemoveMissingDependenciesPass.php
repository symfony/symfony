<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Drops the services that the container cannot wire, because what they depend on or serve is missing.
 *
 * @internal
 */
class RemoveMissingDependenciesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $droppedMiddleware = [];

        foreach (['deduplicate_middleware', 'traceable'] as $name) {
            if (!$container->hasDefinition('messenger.middleware.'.$name)) {
                $droppedMiddleware[] = $name;
            }
        }

        if (!$droppedMiddleware) {
            return;
        }

        foreach ($container->findTaggedServiceIds('messenger.bus') as $busId => $tags) {
            if (!$container->hasParameter($busMiddleware = $busId.'.middleware')) {
                continue;
            }

            $middleware = array_filter($container->getParameter($busMiddleware), static fn ($item) => !\in_array($item['id'], $droppedMiddleware, true));
            $container->setParameter($busMiddleware, array_values($middleware));
        }
    }
}
