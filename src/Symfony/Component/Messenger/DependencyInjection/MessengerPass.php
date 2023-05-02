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

use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\OutOfBoundsException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\Handler\MessageSubscriberInterface;
use Symfony\Component\Messenger\TraceableMessageBus;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class MessengerPass implements CompilerPassInterface
{
    /**
     * @return void
     */
    public function process(ContainerBuilder $container)
    {
        $busIds = [];
        foreach ($container->findTaggedServiceIds('messenger.bus') as $busId => $tags) {
            $busIds[] = $busId;
            if ($container->hasParameter($busMiddlewareParameter = $busId.'.middleware')) {
                $this->registerBusMiddleware($container, $busId, $container->getParameter($busMiddlewareParameter));

                $container->getParameterBag()->remove($busMiddlewareParameter);
            }

            if ($container->hasDefinition('data_collector.messenger')) {
                $this->registerBusToCollector($container, $busId);
            }
        }

        if ($container->hasDefinition('messenger.receiver_locator')) {
            $this->registerReceivers($container, $busIds);
        }
        $this->registerHandlers($container, $busIds);
    }

    private function registerHandlers(ContainerBuilder $container, array $busIds): void
    {
        $definitions = [];
        $handlersByBusAndMessage = [];
        $handlerToOriginalServiceIdMapping = [];

        foreach ($container->findTaggedServiceIds('messenger.message_handler', true) as $serviceId => $tags) {
            foreach ($tags as $tag) {
                if (isset($tag['bus']) && !\in_array($tag['bus'], $busIds, true)) {
                    throw new RuntimeException(sprintf('Invalid handler service "%s": bus "%s" specified on the tag "messenger.message_handler" does not exist (known ones are: "%s").', $serviceId, $tag['bus'], implode('", "', $busIds)));
                }

                $className = $this->getServiceClass($container, $serviceId);
                $r = $container->getReflectionClass($className);

                if (null === $r) {
                    throw new RuntimeException(sprintf('Invalid service "%s": class "%s" does not exist.', $serviceId, $className));
                }

                if (isset($tag['handles'])) {
                    $handles = isset($tag['method']) ? [$tag['handles'] => $tag['method']] : [$tag['handles']];
                } else {
                    $handles = $this->guessHandledClasses($r, $serviceId, $tag['method'] ?? '__invoke');
                }

                $message = null;
                $handlerBuses = (array) ($tag['bus'] ?? $busIds);

                foreach ($handles as $message => $options) {
                    $buses = $handlerBuses;

                    if (\is_int($message)) {
                        if (\is_string($options)) {
                            $message = $options;
                            $options = [];
                        } else {
                            throw new RuntimeException(sprintf('The handler configuration needs to return an array of messages or an associated array of message and configuration. Found value of type "%s" at position "%d" for service "%s".', get_debug_type($options), $message, $serviceId));
                        }
                    }

                    if (\is_string($options)) {
                        $options = ['method' => $options];
                    }

                    if (!isset($options['from_transport']) && isset($tag['from_transport'])) {
                        $options['from_transport'] = $tag['from_transport'];
                    }

                    $priority = $tag['priority'] ?? $options['priority'] ?? 0;
                    $method = $options['method'] ?? '__invoke';

                    if (isset($options['bus'])) {
                        if (!\in_array($options['bus'], $busIds)) {
                            // @deprecated since Symfony 6.2, in 7.0 change to:
                            // $messageLocation = isset($tag['handles']) ? 'declared in your tag attribute "handles"' : sprintf('used as argument type in method "%s::%s()"', $r->getName(), $method);
                            $messageLocation = isset($tag['handles']) ? 'declared in your tag attribute "handles"' : ($r->implementsInterface(MessageSubscriberInterface::class) ? sprintf('returned by method "%s::getHandledMessages()"', $r->getName()) : sprintf('used as argument type in method "%s::%s()"', $r->getName(), $method));

                            throw new RuntimeException(sprintf('Invalid configuration "%s" for message "%s": bus "%s" does not exist.', $messageLocation, $message, $options['bus']));
                        }

                        $buses = [$options['bus']];
                    }

                    if ('*' !== $message && !class_exists($message) && !interface_exists($message, false)) {
                        // @deprecated since Symfony 6.2, in 7.0 change to:
                        // $messageLocation = isset($tag['handles']) ? 'declared in your tag attribute "handles"' : sprintf('used as argument type in method "%s::%s()"', $r->getName(), $method);
                        $messageLocation = isset($tag['handles']) ? 'declared in your tag attribute "handles"' : ($r->implementsInterface(MessageSubscriberInterface::class) ? sprintf('returned by method "%s::getHandledMessages()"', $r->getName()) : sprintf('used as argument type in method "%s::%s()"', $r->getName(), $method));

                        throw new RuntimeException(sprintf('Invalid handler service "%s": class or interface "%s" "%s" not found.', $serviceId, $message, $messageLocation));
                    }

                    if (!$r->hasMethod($method)) {
                        throw new RuntimeException(sprintf('Invalid handler service "%s": method "%s::%s()" does not exist.', $serviceId, $r->getName(), $method));
                    }

                    if ('__invoke' !== $method) {
                        $wrapperDefinition = (new Definition('Closure'))->addArgument([new Reference($serviceId), $method])->setFactory('Closure::fromCallable');

                        $definitions[$definitionId = '.messenger.method_on_object_wrapper.'.ContainerBuilder::hash($message.':'.$priority.':'.$serviceId.':'.$method)] = $wrapperDefinition;
                    } else {
                        $definitionId = $serviceId;
                    }

                    $handlerToOriginalServiceIdMapping[$definitionId] = $serviceId;

                    foreach ($buses as $handlerBus) {
                        $handlersByBusAndMessage[$handlerBus][$message][$priority][] = [$definitionId, $options];
                    }
                }

                if (null === $message) {
                    throw new RuntimeException(sprintf('Invalid handler service "%s": method "%s::getHandledMessages()" must return one or more messages.', $serviceId, $r->getName()));
                }
            }
        }

        foreach ($handlersByBusAndMessage as $bus => $handlersByMessage) {
            foreach ($handlersByMessage as $message => $handlersByPriority) {
                krsort($handlersByPriority);
                $handlersByBusAndMessage[$bus][$message] = array_merge(...$handlersByPriority);
            }
        }

        $handlersLocatorMappingByBus = [];
        foreach ($handlersByBusAndMessage as $bus => $handlersByMessage) {
            foreach ($handlersByMessage as $message => $handlers) {
                $handlerDescriptors = [];
                foreach ($handlers as $handler) {
                    $definitions[$definitionId = '.messenger.handler_descriptor.'.ContainerBuilder::hash($bus.':'.$message.':'.$handler[0])] = (new Definition(HandlerDescriptor::class))->setArguments([new Reference($handler[0]), $handler[1]]);
                    $handlerDescriptors[] = new Reference($definitionId);
                }

                $handlersLocatorMappingByBus[$bus][$message] = new IteratorArgument($handlerDescriptors);
            }
        }
        $container->addDefinitions($definitions);

        foreach ($busIds as $bus) {
            $container->register($locatorId = $bus.'.messenger.handlers_locator', HandlersLocator::class)
                ->setArgument(0, $handlersLocatorMappingByBus[$bus] ?? [])
            ;
            if ($container->has($handleMessageId = $bus.'.middleware.handle_message')) {
                $container->getDefinition($handleMessageId)
                    ->replaceArgument(0, new Reference($locatorId))
                ;
            }
        }

        if ($container->hasDefinition('console.command.messenger_debug')) {
            $debugCommandMapping = $handlersByBusAndMessage;
            foreach ($busIds as $bus) {
                if (!isset($debugCommandMapping[$bus])) {
                    $debugCommandMapping[$bus] = [];
                }

                foreach ($debugCommandMapping[$bus] as $message => $handlers) {
                    foreach ($handlers as $key => $handler) {
                        $debugCommandMapping[$bus][$message][$key][0] = $handlerToOriginalServiceIdMapping[$handler[0]];
                    }
                }
            }
            $container->getDefinition('console.command.messenger_debug')->replaceArgument(0, $debugCommandMapping);
        }
    }

    private function guessHandledClasses(\ReflectionClass $handlerClass, string $serviceId, string $methodName): iterable
    {
        if ($handlerClass->implementsInterface(MessageSubscriberInterface::class)) {
            trigger_deprecation('symfony/messenger', '6.2', 'Implementing "%s" is deprecated, use the "%s" attribute instead.', MessageSubscriberInterface::class, AsMessageHandler::class);

            return $handlerClass->getName()::getHandledMessages();
        }

        if ($handlerClass->implementsInterface(MessageHandlerInterface::class)) {
            trigger_deprecation('symfony/messenger', '6.2', 'Implementing "%s" is deprecated, use the "%s" attribute instead.', MessageHandlerInterface::class, AsMessageHandler::class);
        }

        try {
            $method = $handlerClass->getMethod($methodName);
        } catch (\ReflectionException) {
            throw new RuntimeException(sprintf('Invalid handler service "%s": class "%s" must have an "%s()" method.', $serviceId, $handlerClass->getName(), $methodName));
        }

        if (0 === $method->getNumberOfRequiredParameters()) {
            throw new RuntimeException(sprintf('Invalid handler service "%s": method "%s::%s()" requires at least one argument, first one being the message it handles.', $serviceId, $handlerClass->getName(), $methodName));
        }

        $parameters = $method->getParameters();

        /** @var \ReflectionNamedType|\ReflectionUnionType|null */
        $type = $parameters[0]->getType();

        if (!$type) {
            throw new RuntimeException(sprintf('Invalid handler service "%s": argument "$%s" of method "%s::%s()" must have a type-hint corresponding to the message class it handles.', $serviceId, $parameters[0]->getName(), $handlerClass->getName(), $methodName));
        }

        if ($type instanceof \ReflectionUnionType) {
            $types = [];
            $invalidTypes = [];
            foreach ($type->getTypes() as $type) {
                if (!$type->isBuiltin()) {
                    $types[] = (string) $type;
                } else {
                    $invalidTypes[] = (string) $type;
                }
            }

            if ($types) {
                return ('__invoke' === $methodName) ? $types : array_fill_keys($types, $methodName);
            }

            throw new RuntimeException(sprintf('Invalid handler service "%s": type-hint of argument "$%s" in method "%s::__invoke()" must be a class , "%s" given.', $serviceId, $parameters[0]->getName(), $handlerClass->getName(), implode('|', $invalidTypes)));
        }

        if ($type->isBuiltin()) {
            throw new RuntimeException(sprintf('Invalid handler service "%s": type-hint of argument "$%s" in method "%s::%s()" must be a class , "%s" given.', $serviceId, $parameters[0]->getName(), $handlerClass->getName(), $methodName, $type instanceof \ReflectionNamedType ? $type->getName() : (string) $type));
        }

        return ('__invoke' === $methodName) ? [$type->getName()] : [$type->getName() => $methodName];
    }

    private function registerReceivers(ContainerBuilder $container, array $busIds): void
    {
        $receiverMapping = [];
        $failureTransportsMap = [];
        if ($container->hasDefinition('console.command.messenger_failed_messages_retry')) {
            $commandDefinition = $container->getDefinition('console.command.messenger_failed_messages_retry');
            $globalReceiverName = $commandDefinition->getArgument(0);
            if (null !== $globalReceiverName) {
                if ($container->hasAlias('messenger.failure_transports.default')) {
                    $failureTransportsMap[$globalReceiverName] = new Reference('messenger.failure_transports.default');
                } else {
                    $failureTransportsMap[$globalReceiverName] = new Reference('messenger.transport.'.$globalReceiverName);
                }
            }
        }

        foreach ($container->findTaggedServiceIds('messenger.receiver') as $id => $tags) {
            $receiverClass = $this->getServiceClass($container, $id);
            if (!is_subclass_of($receiverClass, ReceiverInterface::class)) {
                throw new RuntimeException(sprintf('Invalid receiver "%s": class "%s" must implement interface "%s".', $id, $receiverClass, ReceiverInterface::class));
            }

            $receiverMapping[$id] = new Reference($id);

            foreach ($tags as $tag) {
                if (isset($tag['alias'])) {
                    $receiverMapping[$tag['alias']] = $receiverMapping[$id];
                    if ($tag['is_failure_transport'] ?? false) {
                        $failureTransportsMap[$tag['alias']] = $receiverMapping[$id];
                    }
                }
            }
        }

        $receiverNames = [];
        foreach ($receiverMapping as $name => $reference) {
            $receiverNames[(string) $reference] = $name;
        }

        $buses = [];
        foreach ($busIds as $busId) {
            $buses[$busId] = new Reference($busId);
        }

        if ($hasRoutableMessageBus = $container->hasDefinition('messenger.routable_message_bus')) {
            $container->getDefinition('messenger.routable_message_bus')
                ->replaceArgument(0, ServiceLocatorTagPass::register($container, $buses));
        }

        if ($container->hasDefinition('console.command.messenger_consume_messages')) {
            $consumeCommandDefinition = $container->getDefinition('console.command.messenger_consume_messages');

            if ($hasRoutableMessageBus) {
                $consumeCommandDefinition->replaceArgument(0, new Reference('messenger.routable_message_bus'));
            }

            $consumeCommandDefinition->replaceArgument(4, array_values($receiverNames));
            try {
                $consumeCommandDefinition->replaceArgument(6, $busIds);
            } catch (OutOfBoundsException) {
                // ignore to preserve compatibility with symfony/framework-bundle < 5.4
            }
        }

        if ($container->hasDefinition('console.command.messenger_setup_transports')) {
            $container->getDefinition('console.command.messenger_setup_transports')
                ->replaceArgument(1, array_values($receiverNames));
        }

        if ($container->hasDefinition('console.command.messenger_stats')) {
            $container->getDefinition('console.command.messenger_stats')
                ->replaceArgument(1, array_values($receiverNames));
        }

        $container->getDefinition('messenger.receiver_locator')->replaceArgument(0, $receiverMapping);

        $failureTransportsLocator = ServiceLocatorTagPass::register($container, $failureTransportsMap);

        $failedCommandIds = [
            'console.command.messenger_failed_messages_retry',
            'console.command.messenger_failed_messages_show',
            'console.command.messenger_failed_messages_remove',
        ];
        foreach ($failedCommandIds as $failedCommandId) {
            if ($container->hasDefinition($failedCommandId)) {
                $definition = $container->getDefinition($failedCommandId);
                $definition->replaceArgument(1, $failureTransportsLocator);
            }
        }
    }

    private function registerBusToCollector(ContainerBuilder $container, string $busId): void
    {
        $container->setDefinition(
            $tracedBusId = 'debug.traced.'.$busId,
            (new Definition(TraceableMessageBus::class, [new Reference($tracedBusId.'.inner')]))->setDecoratedService($busId)
        );

        $container->getDefinition('data_collector.messenger')->addMethodCall('registerBus', [$busId, new Reference($tracedBusId)]);
    }

    private function registerBusMiddleware(ContainerBuilder $container, string $busId, array $middlewareCollection): void
    {
        $middlewareReferences = [];
        foreach ($middlewareCollection as $middlewareItem) {
            $id = $middlewareItem['id'];
            $arguments = $middlewareItem['arguments'] ?? [];
            if (!$container->has($messengerMiddlewareId = 'messenger.middleware.'.$id)) {
                $messengerMiddlewareId = $id;
            }

            if (!$container->has($messengerMiddlewareId)) {
                throw new RuntimeException(sprintf('Invalid middleware: service "%s" not found.', $id));
            }

            if ($container->findDefinition($messengerMiddlewareId)->isAbstract()) {
                $childDefinition = new ChildDefinition($messengerMiddlewareId);
                $childDefinition->setArguments($arguments);
                if (isset($middlewareReferences[$messengerMiddlewareId = $busId.'.middleware.'.$id])) {
                    $messengerMiddlewareId .= '.'.ContainerBuilder::hash($arguments);
                }
                $container->setDefinition($messengerMiddlewareId, $childDefinition);
            } elseif ($arguments) {
                throw new RuntimeException(sprintf('Invalid middleware factory "%s": a middleware factory must be an abstract definition.', $id));
            }

            $middlewareReferences[$messengerMiddlewareId] = new Reference($messengerMiddlewareId);
        }

        $container->getDefinition($busId)->replaceArgument(0, new IteratorArgument(array_values($middlewareReferences)));
    }

    private function getServiceClass(ContainerBuilder $container, string $serviceId): string
    {
        while (true) {
            $definition = $container->findDefinition($serviceId);

            if (!$definition->getClass() && $definition instanceof ChildDefinition) {
                $serviceId = $definition->getParent();

                continue;
            }

            return $definition->getClass();
        }
    }
}
