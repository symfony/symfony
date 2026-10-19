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

use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Security\Http\AccessMap;

/**
 * Removes the channel listener from the firewalls when no access control rule requires a channel.
 *
 * The listener would otherwise match the request against the access control rules on every
 * request, only to find no channel to redirect to. An access map that is not the one built
 * from the configuration keeps the listener, as it can return channels of its own.
 *
 * @internal
 */
final class RemoveUnusedChannelListenerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('security.firewalls') || !$container->has('security.access_map')) {
            return;
        }

        $accessMap = $container->findDefinition('security.access_map');

        if (AccessMap::class !== $accessMap->getClass() || $accessMap->getFactory() || $accessMap->getConfigurator()) {
            return;
        }

        foreach ($accessMap->getMethodCalls() as [$method, $arguments]) {
            if ('add' !== $method || null !== ($arguments[2] ?? $arguments['$channel'] ?? null)) {
                return;
            }
        }

        foreach ($container->getParameter('security.firewalls') as $firewallName) {
            if (!$container->hasDefinition($contextId = 'security.firewall.map.context.'.$firewallName)) {
                continue;
            }

            $listeners = $container->getDefinition($contextId)->getArgument(0);

            if ($listeners instanceof IteratorArgument) {
                $listeners->setValues(array_values(array_filter($listeners->getValues(), static fn ($listener) => 'security.channel_listener' !== (string) $listener)));
            }
        }
    }
}
