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
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Http\Firewall\FirewallListenerInterface;

/**
 * Sorts firewall listeners based on the execution order provided by FirewallListenerInterface::getPriority().
 *
 * @author Christian Scheb <me@christianscheb.de>
 */
class SortFirewallListenersPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('security.firewalls')) {
            return;
        }

        foreach ($container->getParameter('security.firewalls') as $firewallName) {
            $firewallContextDefinition = $container->getDefinition('security.firewall.map.context.'.$firewallName);
            $this->sortFirewallContextListeners($firewallContextDefinition, $container);
        }
    }

    private function sortFirewallContextListeners(Definition $definition, ContainerBuilder $container): void
    {
        /** @var IteratorArgument $listenerIteratorArgument */
        $listenerIteratorArgument = $definition->getArgument(0);
        $prioritiesByServiceId = $this->getListenerPriorities($listenerIteratorArgument, $container);

        $listeners = $listenerIteratorArgument->getValues();
        usort($listeners, fn (Reference $a, Reference $b) => $prioritiesByServiceId[(string) $b] <=> $prioritiesByServiceId[(string) $a]);

        $listenerIteratorArgument->setValues(array_values($listeners));
    }

    private function getListenerPriorities(IteratorArgument $listeners, ContainerBuilder $container): array
    {
        $priorities = [];

        foreach ($listeners->getValues() as $reference) {
            $id = (string) $reference;
            $def = $container->getDefinition($id);

            // We must assume that the class value has been correctly filled, even if the service is created by a factory
            $class = $def->getClass();

            if (!$r = $container->getReflectionClass($class)) {
                throw new InvalidArgumentException(\sprintf('Class "%s" used for service "%s" cannot be found.', $class, $id));
            }

            $priority = 0;
            if ($r->isSubclassOf(FirewallListenerInterface::class)) {
                $priority = $r->getMethod('getPriority')->invoke(null);
            }

            $priorities[$id] = $priority;
        }

        return $priorities;
    }
}
