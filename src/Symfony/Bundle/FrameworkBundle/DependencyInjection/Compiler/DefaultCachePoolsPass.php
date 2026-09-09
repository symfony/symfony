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
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Wires and checks the services that depend on the cache pools, which only CacheBundle can register.
 *
 * @internal
 */
class DefaultCachePoolsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if ($container->hasDefinition('session.abstract_handler')) {
            $handler = $container->getDefinition('session.abstract_handler');
            $dsn = $handler->getArgument(0);

            // reuse the connection of the cache pool built from the same DSN, when there is one
            if (\is_string($dsn) && $container->hasDefinition($id = '.cache_connection.'.ContainerBuilder::hash($dsn))) {
                $handler->replaceArgument(0, new Reference($id));
            }
        }

        if (!$container->hasParameter('.messenger.claim_check_pools')) {
            return;
        }

        $claimCheckPools = $container->getParameter('.messenger.claim_check_pools');
        $container->getParameterBag()->remove('.messenger.claim_check_pools');

        foreach ($claimCheckPools as $pool => $transport) {
            if (!$container->hasDefinition($pool)) {
                continue;
            }

            $tag = $container->getDefinition($pool)->getTag('cache.pool')[0] ?? null;

            if (null !== $tag && !isset($tag['default_lifetime'])) {
                throw new LogicException(\sprintf('The cache pool "%s" used by Messenger transport "%s" for claim checks must define a "default_lifetime".', $pool, $transport));
            }
        }
    }
}
