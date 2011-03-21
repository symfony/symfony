<?php

namespace Symfony\Bundle\DoctrineMongoDBBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

class RegisterEventListenersAndSubscribersPass implements CompilerPassInterface
{
    protected $container;

    public function process(ContainerBuilder $container)
    {
        $this->container = $container;
        foreach ($container->findTaggedServiceIds('doctrine.odm.mongodb.event_manager') as $id => $tag) {
            $definition = $container->getDefinition($id);
            $prefix = substr($id, 0, -1 * strlen('_event_manager'));
            $this->registerListeners($prefix, $definition);
            $this->registerSubscribers($prefix, $definition);
        }
    }

    protected function registerSubscribers($prefix, $definition)
    {
        $subscribers = array_merge(
            $this->container->findTaggedServiceIds('doctrine.common.event_subscriber'),
            $this->container->findTaggedServiceIds($prefix.'_event_subscriber')
        );

        foreach ($subscribers as $id => $instances) {
            $definition->addMethodCall('addEventSubscriber', array(new Reference($id)));
        }
    }

    protected function registerListeners($prefix, $definition)
    {
        $listeners = array_merge(
            $this->container->findTaggedServiceIds('doctrine.common.event_listener'),
            $this->container->findTaggedServiceIds($prefix.'_event_listener')
        );

        foreach ($listeners as $listenerId => $instances) {
            $events = array();
            foreach ($instances as $attributes) {
                if (isset($attributes['event'])) {
                    $events[] = $attributes['event'];
                }
            }

            if (0 < count($events)) {
                $definition->addMethodCall('addEventListener', array(
                    $events,
                    new Reference($listenerId),
                ));
            }
        }
    }
}
