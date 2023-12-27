<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\DependencyInjection\CompilerPass;

use Symfony\Bridge\Doctrine\ContainerAwareEventManager;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers event listeners and subscribers to the available doctrine connections.
 *
 * @author Jeremy Mikola <jmikola@gmail.com>
 * @author Alexander <iam.asm89@gmail.com>
 * @author David Maicher <mail@dmaicher.de>
 */
class RegisterEventListenersAndSubscribersPass implements CompilerPassInterface
{
    private array $connections;

    /**
     * @var array<string, Definition>
     */
    private array $eventManagers = [];

    /**
     * @param string $managerTemplate sprintf() template for generating the event
     *                                manager's service ID for a connection name
     * @param string $tagPrefix       Tag prefix for listeners and subscribers
     */
    public function __construct(
        private readonly string $connectionsParameter,
        private readonly string $managerTemplate,
        private readonly string $tagPrefix,
    ) {
    }

    /**
     * @return void
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasParameter($this->connectionsParameter)) {
            return;
        }

        $this->connections = $container->getParameter($this->connectionsParameter);
        $listenerRefs = $this->addTaggedServices($container);

        // replace service container argument of event managers with smaller service locator
        // so services can even remain private
        foreach ($listenerRefs as $connection => $refs) {
            $this->getEventManagerDef($container, $connection)
                ->replaceArgument(0, ServiceLocatorTagPass::register($container, $refs));
        }
    }

    private function addTaggedServices(ContainerBuilder $container): array
    {
        $listenerTag = $this->tagPrefix.'.event_listener';
        $subscriberTag = $this->tagPrefix.'.event_subscriber';
        $listenerRefs = [];
        $taggedServices = $this->findAndSortTags($subscriberTag, $listenerTag, $container);

        $managerDefs = [];
        foreach ($taggedServices as $taggedSubscriber) {
            [$tagName, $id, $tag] = $taggedSubscriber;
            $connections = isset($tag['connection'])
                ? [$container->getParameterBag()->resolveValue($tag['connection'])]
                : array_keys($this->connections);
            if ($listenerTag === $tagName && !isset($tag['event'])) {
                throw new InvalidArgumentException(sprintf('Doctrine event listener "%s" must specify the "event" attribute.', $id));
            }
            foreach ($connections as $con) {
                if (!isset($this->connections[$con])) {
                    throw new RuntimeException(sprintf('The Doctrine connection "%s" referenced in service "%s" does not exist. Available connections names: "%s".', $con, $id, implode('", "', array_keys($this->connections))));
                }

                if (!isset($managerDefs[$con])) {
                    $managerDef = $parentDef = $this->getEventManagerDef($container, $con);
                    while (!$parentDef->getClass() && $parentDef instanceof ChildDefinition) {
                        $parentDef = $container->findDefinition($parentDef->getParent());
                    }
                    $managerClass = $container->getParameterBag()->resolveValue($parentDef->getClass());
                    $managerDefs[$con] = [$managerDef, $managerClass];
                } else {
                    [$managerDef, $managerClass] = $managerDefs[$con];
                }

                if (ContainerAwareEventManager::class === $managerClass) {
                    $refs = $managerDef->getArguments()[1] ?? [];
                    $listenerRefs[$con][$id] = new Reference($id);
                    if ($subscriberTag === $tagName) {
                        trigger_deprecation('symfony/doctrine-bridge', '6.3', 'Registering "%s" as a Doctrine subscriber is deprecated. Register it as a listener instead, using e.g. the #[%s] attribute.', $id, str_starts_with($this->tagPrefix, 'doctrine_mongodb') ? 'AsDocumentListener' : 'AsDoctrineListener');
                        $refs[] = $id;
                    } else {
                        $refs[] = [[$tag['event']], $id];
                    }
                    $managerDef->setArgument(1, $refs);
                } else {
                    if ($subscriberTag === $tagName) {
                        $managerDef->addMethodCall('addEventSubscriber', [new Reference($id)]);
                    } else {
                        $managerDef->addMethodCall('addEventListener', [[$tag['event']], new Reference($id)]);
                    }
                }
            }
        }

        return $listenerRefs;
    }

    private function getEventManagerDef(ContainerBuilder $container, string $name): Definition
    {
        if (!isset($this->eventManagers[$name])) {
            $this->eventManagers[$name] = $container->getDefinition(sprintf($this->managerTemplate, $name));
        }

        return $this->eventManagers[$name];
    }

    /**
     * Finds and orders all service tags with the given name by their priority.
     *
     * The order of additions must be respected for services having the same priority,
     * and knowing that the \SplPriorityQueue class does not respect the FIFO method,
     * we should not use this class.
     *
     * @see https://bugs.php.net/53710
     * @see https://bugs.php.net/60926
     */
    private function findAndSortTags(string $subscriberTag, string $listenerTag, ContainerBuilder $container): array
    {
        $sortedTags = [];
        $taggedIds = [
            $subscriberTag => $container->findTaggedServiceIds($subscriberTag, true),
            $listenerTag => $container->findTaggedServiceIds($listenerTag, true),
        ];
        $taggedIds[$subscriberTag] = array_diff_key($taggedIds[$subscriberTag], $taggedIds[$listenerTag]);

        foreach ($taggedIds as $tagName => $serviceIds) {
            foreach ($serviceIds as $serviceId => $tags) {
                foreach ($tags as $attributes) {
                    $priority = $attributes['priority'] ?? 0;
                    $sortedTags[$priority][] = [$tagName, $serviceId, $attributes];
                }
            }
        }

        krsort($sortedTags);

        return array_merge(...$sortedTags);
    }
}
