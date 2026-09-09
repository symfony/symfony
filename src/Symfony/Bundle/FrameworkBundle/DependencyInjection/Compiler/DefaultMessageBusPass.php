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
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Wires the services that depend on the default message bus, which only MessengerBundle can register.
 *
 * @internal
 */
class DefaultMessageBusPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasAlias('messenger.default_bus')) {
            if ($container->hasDefinition('scheduler.event_listener')) {
                throw new LogicException('Scheduler support cannot be enabled as the Messenger component is not '.(interface_exists(MessageBusInterface::class) ? 'enabled.' : 'installed. Try running "composer require symfony/messenger".'));
            }

            $container->removeDefinition('cache.messenger.restart_workers_signal');

            return;
        }

        foreach ($container->getDefinitions() as $id => $definition) {
            if (!str_starts_with($id, '.messenger.transport.') || !str_ends_with($id, '.claim_check_serializer')) {
                continue;
            }

            $pool = (string) $definition->getArgument(1);

            if (!$tags = $container->hasDefinition($pool) ? $container->getDefinition($pool)->getTag('cache.pool') : []) {
                continue;
            }

            if (!isset($tags[0]['default_lifetime'])) {
                $transport = substr($id, \strlen('.messenger.transport.'), -\strlen('.claim_check_serializer'));

                throw new LogicException(\sprintf('The cache pool "%s" used by Messenger transport "%s" for claim checks must define a "default_lifetime".', $pool, $transport));
            }
        }

    }
}
