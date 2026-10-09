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
use Symfony\Component\DependencyInjection\Compiler\BeforeAfterSorter;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Http\Attribute\FirewallListenerOrder;
use Symfony\Component\Security\Http\Firewall\FirewallListenerInterface;

/**
 * Sorts firewall listeners based on the execution order provided by FirewallListenerInterface::getPriority() and #[FirewallListenerOrder].
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
            $this->sortFirewallContextListeners($firewallContextDefinition, $container, $firewallName);
        }
    }

    private function sortFirewallContextListeners(Definition $definition, ContainerBuilder $container, string $firewallName): void
    {
        /** @var IteratorArgument $listenerIteratorArgument */
        $listenerIteratorArgument = $definition->getArgument(0);
        $prioritiesByServiceId = $this->getListenerPriorities($listenerIteratorArgument, $container);

        $listeners = $listenerIteratorArgument->getValues();
        usort($listeners, static fn (Reference $a, Reference $b) => $prioritiesByServiceId[(string) $b] <=> $prioritiesByServiceId[(string) $a]);

        $logoutListener = $definition->getArguments()[2] ?? null;
        $listeners = $this->applyConstraints($container, $firewallName, array_values($listeners), $prioritiesByServiceId, $logoutListener instanceof Reference ? $logoutListener : null);

        $listenerIteratorArgument->setValues($listeners);
    }

    private function getListenerPriorities(IteratorArgument $listeners, ContainerBuilder $container): array
    {
        $priorities = [];

        foreach ($listeners->getValues() as $reference) {
            $priorities[(string) $reference] = $this->getListenerPriority($container, (string) $reference);
        }

        return $priorities;
    }

    private function getListenerPriority(ContainerBuilder $container, string $id): int
    {
        $def = $container->getDefinition($id);

        // We must assume that the class value has been correctly filled, even if the service is created by a factory
        $class = $def->getClass();

        if (!$r = $container->getReflectionClass($class)) {
            throw new InvalidArgumentException(\sprintf('Class "%s" used for service "%s" cannot be found.', $class, $id));
        }

        if ($r->isSubclassOf(FirewallListenerInterface::class)) {
            return $r->getMethod('getPriority')->invoke(null);
        }

        return 0;
    }

    /**
     * Reorders the listeners of a firewall so that their #[FirewallListenerOrder] constraints are met.
     *
     * A priority is never crossed. The logout listener can be targeted, at the position where the firewall calls it.
     *
     * @param list<Reference>    $listeners  The listeners, sorted by priority
     * @param array<string, int> $priorities The priority of each listener, by service id
     *
     * @return list<Reference>
     */
    private function applyConstraints(ContainerBuilder $container, string $firewallName, array $listeners, array $priorities, ?Reference $logoutListener): array
    {
        $items = $listeners;

        if ($logoutListener) {
            $priorities[(string) $logoutListener] = $this->getListenerPriority($container, (string) $logoutListener);

            // the firewall calls the logout listener before the first listener that has a lower priority
            for ($i = 0; isset($items[$i]) && $priorities[(string) $items[$i]] >= $priorities[(string) $logoutListener]; ++$i) {
            }

            array_splice($items, $i, 0, [$logoutListener]);
        }

        $references = $keyPriorities = $constraints = $keysById = $aliases = [];
        $logoutKey = null;

        foreach ($items as $reference) {
            $id = (string) $reference;

            for ($n = 1, $key = $id; isset($references[$key]); ++$n) {
                $key = $id.'#'.$n;
            }

            $references[$key] = $reference;
            $keyPriorities[$key] = $priorities[$id];

            if ($reference === $logoutListener) {
                $logoutKey = $key;
            }

            [$ids, $classes] = $this->getDecorationChain($container, $id);

            foreach ($ids as $name) {
                $keysById[$name][] = $key;
            }

            foreach ($classes as $class) {
                $aliases[$class->name][] = $key;

                foreach ($class->getAttributes(FirewallListenerOrder::class) as $attribute) {
                    $order = $attribute->newInstance();

                    foreach (['before' => $order->before, 'after' => $order->after] as $direction => $targets) {
                        foreach ((array) $targets as $target) {
                            $constraints[$key][$direction][] = $target;
                        }
                    }
                }
            }
        }

        if (!$constraints) {
            return $listeners;
        }

        try {
            $sorted = array_keys(BeforeAfterSorter::sortWithPriorities($keyPriorities, $constraints, $keysById + $aliases));

            if (null !== $logoutKey) {
                $afterLogout = false;

                foreach ($sorted as $key) {
                    if ($key === $logoutKey) {
                        $afterLogout = true;
                    } elseif ($afterLogout && $keyPriorities[$key] === $keyPriorities[$logoutKey]) {
                        throw new InvalidArgumentException(\sprintf('"%s" cannot run after "%s", because the firewall calls the logout listener after every listener of its priority (%d); lower the priority of "%s" or drop the constraint.', $key, $logoutKey, $keyPriorities[$key], $key));
                    }
                }
            }
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException(\sprintf('Cannot order the listeners of firewall "%s": ', $firewallName).lcfirst($e->getMessage()), 0, $e);
        }

        return array_values(array_map(static fn ($key) => $references[$key], array_diff($sorted, [$logoutKey])));
    }

    /**
     * Returns the service ids and the classes a listener answers to, following the services it decorates.
     *
     * @return array{list<string>, list<\ReflectionClass>}
     */
    private function getDecorationChain(ContainerBuilder $container, string $id): array
    {
        $ids = $classes = [];

        while (null !== $id && !\in_array($id, $ids, true) && $container->hasDefinition($id)) {
            $ids[] = $id;
            $definition = $container->getDefinition($id);

            if ($class = $container->getReflectionClass($definition->getClass(), false)) {
                $classes[] = $class;
            }

            $id = null;

            foreach ($definition->getTag('container.decorator') as $decorator) {
                $ids[] = $decorator['id'];
                $id = $decorator['inner'];
            }
        }

        return [$ids, $classes];
    }
}
