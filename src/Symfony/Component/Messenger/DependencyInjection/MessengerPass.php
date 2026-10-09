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
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\BeforeAfterSorter;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\TraceableMessageBus;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class MessengerPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    private ?array $configuredSendersMap = null;
    private array $handlerTransports = [];

    public function process(ContainerBuilder $container): void
    {
        $busIds = array_keys($container->findTaggedServiceIds('messenger.bus'));
        $taggedMiddleware = $this->findTaggedMiddleware($container, $busIds);

        foreach ($busIds as $busId) {
            if ($container->hasParameter($busMiddlewareParameter = $busId.'.middleware')) {
                $middleware = $container->getParameter($busMiddlewareParameter);

                if (isset($taggedMiddleware[$busId]) || isset($taggedMiddleware['*'])) {
                    $middleware = $this->addTaggedMiddleware($container, $busId, $middleware, $taggedMiddleware[$busId] ?? [], $taggedMiddleware['*'] ?? []);
                }

                $this->registerBusMiddleware($container, $busId, $middleware);

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
        $this->registerTypeMapping($container);
        $this->registerDebugCommandRouting($container);
        $this->registerDebugCommandMiddleware($container, $busIds);
    }

    private function registerHandlers(ContainerBuilder $container, array $busIds): void
    {
        $definitions = [];
        $handlersByBusAndMessage = [];
        $handlerToOriginalServiceIdMapping = [];
        $signedMessageTypes = [];
        $handlerTransports = [];
        $hasSendersLocator = $container->hasDefinition('messenger.senders_locator');
        $receiverNames = null;

        if ($container->hasDefinition('messenger.receiver_locator')) {
            $receiverNames = [];
            foreach ($container->findTaggedServiceIds('messenger.receiver') as $id => $tags) {
                foreach ($tags as $tag) {
                    $receiverNames[$tag['alias'] ?? $id] = $id;
                }
            }
        }

        foreach ($container->findTaggedServiceIds('messenger.message_handler', true) as $serviceId => $tags) {
            // an option-less tag comes from autoconfiguration; it would defeat the configured ones
            if ($configuredTags = array_filter($tags)) {
                $tags = $configuredTags;
            }

            foreach ($tags as $tag) {
                if (isset($tag['bus']) && !\in_array($tag['bus'], $busIds, true)) {
                    throw new RuntimeException(\sprintf('Invalid handler service "%s": bus "%s" specified on the tag "messenger.message_handler" does not exist (known ones are: "%s").', $serviceId, $tag['bus'], implode('", "', $busIds)));
                }

                $className = $this->getServiceClass($container, $serviceId);
                $r = $container->getReflectionClass($className);

                if (null === $r) {
                    throw new RuntimeException(\sprintf('Invalid service "%s": class "%s" does not exist.', $serviceId, $className));
                }

                if (isset($tag['handles'])) {
                    $handles = isset($tag['method']) ? [$tag['handles'] => $tag['method']] : [$tag['handles']];
                } else {
                    $handles = $this->guessHandledClasses($r, $serviceId, $tag['method'] ?? '__invoke');
                }

                $message = null;
                $handlerBuses = (array) ($tag['bus'] ?? $busIds);
                $declaredPriority = $tag['priority'] ?? null;
                $constraints = array_filter(['before' => (array) ($tag['before'] ?? []), 'after' => (array) ($tag['after'] ?? [])]);

                foreach ($handles as $message => $options) {
                    $buses = $handlerBuses;

                    if (\is_int($message)) {
                        if (\is_string($options)) {
                            $message = $options;
                            $options = [];
                        } else {
                            throw new RuntimeException(\sprintf('The handler configuration needs to return an array of messages or an associated array of message and configuration. Found value of type "%s" at position "%d" for service "%s".', get_debug_type($options), $message, $serviceId));
                        }
                    }

                    if (\is_string($options)) {
                        $options = ['method' => $options];
                    }

                    $options += array_filter($tag);
                    unset($options['handles'], $options['before'], $options['after']);

                    if (null !== $transport = $options['transport'] ?? null) {
                        if (isset($options['from_transport']) && $transport !== $options['from_transport']) {
                            throw new RuntimeException(\sprintf('Invalid handler service "%s": the "transport" and "from_transport" options of the "messenger.message_handler" tag must have the same value, "%s" and "%s" given.', $serviceId, $transport, $options['from_transport']));
                        }

                        if ('*' === $message) {
                            throw new RuntimeException(\sprintf('Invalid handler service "%s": the "transport" option cannot be used with "*" as message type.', $serviceId));
                        }

                        if (null !== $receiverNames && !isset($receiverNames[$transport])) {
                            throw new RuntimeException(\sprintf('Invalid handler service "%s": the "transport" option refers to "%s", which is not a configured transport (known ones are: "%s").', $serviceId, $transport, implode('", "', array_keys($receiverNames))));
                        }

                        if (!$hasSendersLocator) {
                            throw new RuntimeException(\sprintf('Invalid handler service "%s": the "transport" option needs the "messenger.senders_locator" service, which is not defined.', $serviceId));
                        }

                        unset($options['transport']);
                        $options['from_transport'] = $transport;
                        $handlerTransports[$message][] = $transport;
                    }

                    $priority = $options['priority'] ?? 0;
                    $method = $options['method'] ?? '__invoke';
                    $fromTransport = $options['from_transport'] ?? '';
                    $sign = $options['sign'] ?? false;

                    if (isset($options['bus'])) {
                        if (!\in_array($options['bus'], $busIds)) {
                            $messageLocation = isset($tag['handles']) ? 'declared in your tag attribute "handles"' : \sprintf('used as argument type in method "%s::%s()"', $r->getName(), $method);

                            throw new RuntimeException(\sprintf('Invalid configuration '.$messageLocation.' for message "%s": bus "%s" does not exist.', $message, $options['bus']));
                        }

                        $buses = [$options['bus']];
                    }

                    if ('*' !== $message && !class_exists($message) && !interface_exists($message, false)) {
                        $messageLocation = isset($tag['handles']) ? 'declared in your tag attribute "handles"' : \sprintf('used as argument type in method "%s::%s()"', $r->getName(), $method);

                        throw new RuntimeException(\sprintf('Invalid handler service "%s": class or interface "%s" '.$messageLocation.' not found.', $serviceId, $message));
                    }

                    if (!$r->hasMethod($method)) {
                        throw new RuntimeException(\sprintf('Invalid handler service "%s": method "%s::%s()" does not exist.', $serviceId, $r->getName(), $method));
                    }

                    if ('__invoke' !== $method || '' !== $fromTransport) {
                        $wrapperDefinition = (new Definition('Closure'))->addArgument([new Reference($serviceId), $method])->setFactory('Closure::fromCallable');

                        $definitions[$definitionId = '.messenger.method_on_object_wrapper.'.ContainerBuilder::hash($message.':'.$priority.':'.$serviceId.':'.$method.':'.$fromTransport)] = $wrapperDefinition;
                    } else {
                        $definitionId = $serviceId;
                    }

                    $handlerToOriginalServiceIdMapping[$definitionId] = $serviceId;

                    foreach ($buses as $handlerBus) {
                        $handlersByBusAndMessage[$handlerBus][$message][$priority][] = [$definitionId, $options, $declaredPriority, $constraints];
                    }

                    if ($sign && '*' !== $message) {
                        $signedMessageTypes[$message] = true;
                    }
                }

                if (null === $message) {
                    throw new RuntimeException(\sprintf('Invalid handler service "%s": the list of messages to handle is empty.', $serviceId));
                }
            }
        }

        foreach ($handlersByBusAndMessage as $bus => $handlersByMessage) {
            foreach ($handlersByMessage as $message => $handlersByPriority) {
                krsort($handlersByPriority);
                $handlers = $this->sortHandlersByConstraints($container, $bus, $message, array_merge(...$handlersByPriority), $handlerToOriginalServiceIdMapping);
                $serviceIdsByName = [];

                foreach ($handlers as $key => [$definitionId, $options]) {
                    $handlers[$key] = [$definitionId, $options];
                    $serviceId = $handlerToOriginalServiceIdMapping[$definitionId];
                    $name = $this->getServiceClass($container, $serviceId).'::'.($options['method'] ?? '__invoke');
                    $serviceIdsByName[$name][$key] = $serviceId;
                }

                // several services sharing a name cannot be told apart at runtime, name them after their service id
                foreach ($serviceIdsByName as $serviceIds) {
                    if (1 === \count(array_unique($serviceIds))) {
                        continue;
                    }

                    foreach ($serviceIds as $key => $serviceId) {
                        $handlers[$key][1]['alias'] ??= $serviceId;
                    }
                }

                $handlersByBusAndMessage[$bus][$message] = $handlers;
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

        if ($container->hasDefinition('messenger.signing_serializer')) {
            $container->getDefinition('messenger.signing_serializer')->replaceArgument(2, array_keys($signedMessageTypes));

            foreach ($container->getDefinitions() as $id => $definition) {
                if (!$definition instanceof ChildDefinition || 'messenger.signing_serializer' !== $definition->getParent()) {
                    continue;
                }

                if ($signAll = $definition->getArguments()['index_2'] ?? null) {
                    // even as a failure transport, a transport that signs every message refuses the unverified messages of the types that require a signature
                    $definition->replaceArgument(2, [...$signAll, ...array_keys($signedMessageTypes)]);
                } elseif (!$signedMessageTypes) {
                    // the signing serializer of a transport that does not sign every message has nothing to do when no message type requires a signature
                    $container->setAlias($id, (string) $definition->getArgument(0));
                }
            }
        }

        if ($handlerTransports) {
            $sendersLocatorDefinition = $container->getDefinition('messenger.senders_locator');
            $sendersMap = $sendersLocatorDefinition->getArgument(0);
            if (!\is_array($sendersMap)) {
                $sendersMap = [];
            }
            // debug:messenger tells the configured routing from the one handlers add
            $this->configuredSendersMap = $sendersMap;
            $this->handlerTransports = $handlerTransports;

            foreach ($handlerTransports as $message => $transports) {
                // a handler transport adds to the routing the message has today, which may come
                // from a parent, an interface, a wildcard or the #[AsMessage] attribute
                if (!isset($sendersMap[$message])) {
                    $container->getReflectionClass($message);
                    $sendersMap[$message] = SendersLocator::getSenderAliases($message, $sendersMap);
                }

                foreach ($transports as $transport) {
                    // the configured routing may name a transport by its service id
                    if (!\in_array($transport, $sendersMap[$message], true) && !\in_array($receiverNames[$transport] ?? $transport, $sendersMap[$message], true)) {
                        $sendersMap[$message][] = $transport;
                    }
                }
            }

            $sendersLocatorDefinition->replaceArgument(0, $sendersMap);
        }

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

    /**
     * Reorders the handlers of a message on a bus so that their "before"/"after" constraints are met.
     *
     * A declared priority is never crossed; a handler without one goes where its constraints put it.
     *
     * @param list<array{0: string, 1: array, 2: int|null, 3: array}> $handlers                          Definition id, options, declared priority and "before"/"after" constraints of each handler, in their default order
     * @param array<string, string>                                   $handlerToOriginalServiceIdMapping
     *
     * @return list<array{0: string, 1: array, 2: int|null, 3: array}>
     */
    private function sortHandlersByConstraints(ContainerBuilder $container, string $bus, string $message, array $handlers, array $handlerToOriginalServiceIdMapping): array
    {
        if (!array_filter(array_column($handlers, 3))) {
            return $handlers;
        }

        $indexes = $priorities = $constraints = $keysById = $aliases = [];

        foreach ($handlers as $i => [$definitionId, $options, $declaredPriority, $handlerConstraints]) {
            $serviceId = $handlerToOriginalServiceIdMapping[$definitionId];
            $method = $options['method'] ?? '__invoke';

            // keys show up in error messages: the service id, then "id::method", then a counter for a repeated method
            for ($n = 0, $key = $serviceId; isset($indexes[$key]); ++$n) {
                $key = $serviceId.'::'.$method.($n ? '#'.$n : '');
            }

            $indexes[$key] = $i;
            $priorities[$key] = $declaredPriority;
            $keysById[$serviceId][] = $key;
            $keysById[$serviceId.'::'.$method][] = $key;

            if ($handlerConstraints) {
                $constraints[$key] = $handlerConstraints;
            }

            $class = $container->getParameterBag()->resolveValue($this->getServiceClass($container, $serviceId));
            $aliases[$class][] = $key;
            $aliases[$class.'::'.$method][] = $key;
        }

        // a service id always designates its own handlers, whatever class it shares a name with
        $aliases = $keysById + $aliases;

        $this->checkTargetMethods($constraints, $aliases, $bus, $message);

        try {
            $sorted = BeforeAfterSorter::sortWithPriorities($priorities, $constraints, $aliases);
        } catch (InvalidArgumentException $e) {
            throw new RuntimeException(\sprintf('Cannot order the handlers of "%s" on bus "%s": ', $message, $bus).lcfirst($e->getMessage()), 0, $e);
        }

        return array_map(static fn ($key) => $handlers[$indexes[$key]], array_keys($sorted));
    }

    /**
     * Rejects a "service::method" target naming a method the service does not handle the message with.
     *
     * A target whose service part is absent stays ignored: the package declaring it may not be installed.
     * One that is present has to name one of its handlers, otherwise the constraint would silently do nothing.
     *
     * @param array<string, array{before?: list<string>, after?: list<string>}> $constraints
     * @param array<string, list<string>>                                       $aliases
     */
    private function checkTargetMethods(array $constraints, array $aliases, string $bus, string $message): void
    {
        foreach ($constraints as $key => $constraint) {
            foreach ($constraint as $direction => $targets) {
                foreach ($targets as $target) {
                    if (isset($aliases[$target]) || false === $i = strrpos($target, '::')) {
                        continue;
                    }

                    if (isset($aliases[$service = substr($target, 0, $i)])) {
                        throw new RuntimeException(\sprintf('Invalid "%s" constraint on handler "%s": "%s" does not handle "%s" on bus "%s" with method "%s".', $direction, $key, $service, $message, $bus, substr($target, 2 + $i)));
                    }
                }
            }
        }
    }

    private function guessHandledClasses(\ReflectionClass $handlerClass, string $serviceId, string $methodName): iterable
    {
        try {
            $method = $handlerClass->getMethod($methodName);
        } catch (\ReflectionException) {
            throw new RuntimeException(\sprintf('Invalid handler service "%s": class "%s" must have an "%s()" method.', $serviceId, $handlerClass->getName(), $methodName));
        }

        if (0 === $method->getNumberOfRequiredParameters()) {
            throw new RuntimeException(\sprintf('Invalid handler service "%s": method "%s::%s()" requires at least one argument, first one being the message it handles.', $serviceId, $handlerClass->getName(), $methodName));
        }

        $parameters = $method->getParameters();

        /** @var \ReflectionNamedType|\ReflectionUnionType|null */
        $type = $parameters[0]->getType();

        if (!$type) {
            throw new RuntimeException(\sprintf('Invalid handler service "%s": argument "$%s" of method "%s::%s()" must have a type-hint corresponding to the message class it handles.', $serviceId, $parameters[0]->getName(), $handlerClass->getName(), $methodName));
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

            throw new RuntimeException(\sprintf('Invalid handler service "%s": type-hint of argument "$%s" in method "%s::__invoke()" must be a class , "%s" given.', $serviceId, $parameters[0]->getName(), $handlerClass->getName(), implode('|', $invalidTypes)));
        }

        if ($type->isBuiltin()) {
            throw new RuntimeException(\sprintf('Invalid handler service "%s": type-hint of argument "$%s" in method "%s::%s()" must be a class , "%s" given.', $serviceId, $parameters[0]->getName(), $handlerClass->getName(), $methodName, $type instanceof \ReflectionNamedType ? $type->getName() : (string) $type));
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

        // the receivers are walked in tag priority order, so that everything derived from
        // them keeps that order, "messenger:consume --all" included
        $tagsById = $container->findTaggedServiceIds('messenger.receiver');
        $consumableReceiverNames = [];
        foreach ($this->findAndSortTaggedServices('messenger.receiver', $container) as $reference) {
            $id = (string) $reference;
            $receiverClass = $this->getServiceClass($container, $id);
            if (!is_subclass_of($receiverClass, ReceiverInterface::class)) {
                throw new RuntimeException(\sprintf('Invalid receiver "%s": class "%s" must implement interface "%s".', $id, $receiverClass, ReceiverInterface::class));
            }

            $receiverMapping[$id] = new Reference($id);

            foreach ($tagsById[$id] ?? [] as $tag) {
                if (isset($tag['alias'])) {
                    $receiverMapping[$tag['alias']] = $receiverMapping[$id];

                    if ($tag['is_failure_transport'] ?? false) {
                        $failureTransportsMap[$tag['alias']] = $receiverMapping[$id];
                    }
                }
                if (!isset($tag['is_consumable']) || false !== $tag['is_consumable']) {
                    $consumableReceiverNames[] = $tag['alias'] ?? $id;
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

            $consumeCommandDefinition->replaceArgument(4, $consumableReceiverNames);
            $consumeCommandDefinition->replaceArgument(6, $busIds);
        }

        if ($container->hasDefinition('console.command.messenger_setup_transports')) {
            $container->getDefinition('console.command.messenger_setup_transports')
                ->replaceArgument(1, array_values($receiverNames));
        }

        if ($container->hasDefinition('console.command.messenger_stats')) {
            $container->getDefinition('console.command.messenger_stats')
                ->replaceArgument(1, array_values($receiverNames));
        }

        if ($container->hasDefinition('console.command.messenger_show')) {
            $container->getDefinition('console.command.messenger_show')
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

        if ($container->hasDefinition('messenger.failed_message_repository')) {
            $container->getDefinition('messenger.failed_message_repository')
                ->replaceArgument(0, $failureTransportsLocator);
        }
    }

    private function registerBusToCollector(ContainerBuilder $container, string $busId): void
    {
        $container->setDefinition(
            $tracedBusId = 'debug.traced.'.$busId,
            (new Definition(TraceableMessageBus::class, [new Reference($tracedBusId.'.inner'), new Reference('profiler.is_disabled_state_checker', ContainerBuilder::IGNORE_ON_INVALID_REFERENCE)]))->setDecoratedService($busId)
        );

        $container->getDefinition('data_collector.messenger')->addMethodCall('registerBus', [$busId, new Reference($tracedBusId)]);
    }

    /**
     * @param list<string> $busIds
     *
     * @return array<string, array<string, array{before?: list<string>, after?: list<string>}>> The constraints of the tagged middleware, by bus ("*" for all buses) and service id
     */
    private function findTaggedMiddleware(ContainerBuilder $container, array $busIds): array
    {
        $knownBusIds = array_values(array_filter($busIds, static fn ($busId) => $container->hasParameter($busId.'.middleware')));
        $middlewareByBus = [];

        foreach ($container->findTaggedServiceIds('messenger.middleware', true) as $serviceId => $tags) {
            foreach ($tags as $tag) {
                if (null === $busId = $tag['bus'] ?? null) {
                    throw new RuntimeException(\sprintf('Invalid middleware service "%s": the "messenger.middleware" tag requires a "bus" attribute.', $serviceId));
                }

                if ('*' !== $busId && !\in_array($busId, $knownBusIds, true)) {
                    throw new RuntimeException(\sprintf('Invalid middleware service "%s": bus "%s" specified on the tag "messenger.middleware" does not exist (known ones are: "%s").', $serviceId, $busId, implode('", "', $knownBusIds)));
                }

                if (isset($middlewareByBus[$busId][$serviceId])) {
                    throw new RuntimeException(\sprintf('Invalid middleware service "%s": it is tagged "messenger.middleware" more than once for bus "%s".', $serviceId, $busId));
                }

                $middlewareByBus[$busId][$serviceId] = array_filter(['before' => (array) ($tag['before'] ?? []), 'after' => (array) ($tag['after'] ?? [])]);
            }
        }

        return $middlewareByBus;
    }

    /**
     * @param list<array{id: string, arguments?: array}>                        $middleware
     * @param array<string, array{before?: list<string>, after?: list<string>}> $tagged            The middleware tagged for this bus
     * @param array<string, array{before?: list<string>, after?: list<string>}> $taggedForAllBuses The middleware tagged for all buses
     *
     * @return list<array{id: string, arguments?: array}>
     */
    private function addTaggedMiddleware(ContainerBuilder $container, string $busId, array $middleware, array $tagged, array $taggedForAllBuses): array
    {
        $items = $names = $constraints = $byId = $byClass = [];
        $insertAt = null;

        foreach ($middleware as $item) {
            $serviceId = $container->has('messenger.middleware.'.$item['id']) ? 'messenger.middleware.'.$item['id'] : $item['id'];

            // tags are on definitions, so a middleware configured through an alias is compared by the definition it points to
            for ($definitionId = $serviceId, $seen = []; $container->hasAlias($definitionId) && !isset($seen[$definitionId]); $seen[$definitionId] = true) {
                $definitionId = (string) $container->getAlias($definitionId);
            }

            if (isset($tagged[$definitionId])) {
                throw new RuntimeException(\sprintf('Invalid middleware service "%s": it is both listed in the configuration of bus "%s" and tagged "messenger.middleware" for it.', $definitionId, $busId));
            }

            // a middleware tagged for all buses keeps the position the configuration of a bus gives it
            unset($taggedForAllBuses[$definitionId]);

            for ($n = 0, $key = $item['id']; isset($items[$key]); ++$n) {
                $key = $item['id'].'#'.$n;
            }

            // tagged middleware go after the configured ones, in front of the ones that send and handle the message
            if (null === $insertAt && \in_array($serviceId, ['messenger.middleware.send_message', 'messenger.middleware.chain', 'messenger.middleware.handle_message'], true)) {
                $insertAt = \count($items);
            }

            $items[$key] = $item;
            $names[$key] = [$item['id'], $serviceId, $definitionId];
        }

        $seed = array_keys($items);
        $taggedKeys = [];

        foreach ($tagged + $taggedForAllBuses as $serviceId => $serviceConstraints) {
            for ($n = 0, $key = $serviceId; isset($items[$key]); ++$n) {
                $key = $serviceId.'#'.$n;
            }

            $items[$key] = ['id' => $serviceId];
            $taggedKeys[] = $key;

            if ($serviceConstraints) {
                $constraints[$key] = $serviceConstraints;
            }

            $names[$key] = [$serviceId];
        }

        array_splice($seed, $insertAt ?? \count($seed), 0, $taggedKeys);

        foreach ($names as $key => $ids) {
            foreach (array_unique($ids) as $id) {
                $byId[$id][] = $key;
            }

            if ($container->has($serviceId = end($ids)) && \is_string($class = $container->getParameterBag()->resolveValue($container->findDefinition($serviceId)->getClass()))) {
                $byClass[$class][] = $key;
            }
        }

        try {
            $sorted = BeforeAfterSorter::sort($seed, $constraints, $byId + $byClass);
        } catch (InvalidArgumentException $e) {
            throw new RuntimeException(\sprintf('Cannot order the middleware of bus "%s": ', $busId).lcfirst($e->getMessage()), 0, $e);
        }

        return array_map(static fn ($key) => $items[$key], $sorted);
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
                throw new RuntimeException(\sprintf('Invalid middleware: service "%s" not found.', $id));
            }

            if ($container->findDefinition($messengerMiddlewareId)->isAbstract()) {
                $childDefinition = new ChildDefinition($messengerMiddlewareId);
                $childDefinition->setArguments($arguments);
                if (isset($middlewareReferences[$messengerMiddlewareId = $busId.'.middleware.'.$id])) {
                    $messengerMiddlewareId .= '.'.ContainerBuilder::hash($arguments);
                }
                $container->setDefinition($messengerMiddlewareId, $childDefinition);
            } elseif ($arguments) {
                throw new RuntimeException(\sprintf('Invalid middleware factory "%s": a middleware factory must be an abstract definition.', $id));
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

    private function registerTypeMapping(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('messenger.transport.symfony_serializer')) {
            return;
        }

        $taggedIds = $container->findTaggedResourceIds('messenger.message', false);

        // a message with no serialized type name is sent under its own class name, so that name is
        // taken even though it never enters the map
        $messageClasses = [];
        foreach ($taggedIds as $id => $tags) {
            $messageClasses[$container->getDefinition($id)->getClass()] = true;
        }

        $typeToClassMap = [];
        foreach ($taggedIds as $id => $tags) {
            $class = $container->getDefinition($id)->getClass();
            foreach ($tags as $tag) {
                $aliases = $tag['serializedTypeNameAliases'] ?? [];

                if (!\is_array($aliases)) {
                    throw new RuntimeException(\sprintf('The "serializedTypeNameAliases" attribute of the "messenger.message" tag of class "%s" must be an array of strings, "%s" given.', $class, get_debug_type($aliases)));
                }

                if (!isset($tag['serializedTypeName'])) {
                    if ($aliases) {
                        throw new RuntimeException(\sprintf('A serialized type name must be set on class "%s" to use serialized type name aliases.', $class));
                    }

                    continue;
                }

                // the aliases come first so that the canonical name wins once the map is flipped for encoding
                foreach ([...$aliases, $tag['serializedTypeName']] as $typeName) {
                    if ($typeName !== $class && isset($messageClasses[$typeName])) {
                        throw new RuntimeException(\sprintf('The serialized type name "%s" set on class "%s" is the name of another message class, which uses it as its own serialized type.', $typeName, $class));
                    }

                    if (isset($typeToClassMap[$typeName])) {
                        if ($typeToClassMap[$typeName] === $class) {
                            throw new RuntimeException(\sprintf('The serialized type name "%s" is listed more than once on class "%s".', $typeName, $class));
                        }

                        throw new RuntimeException(\sprintf('The serialized type name "%s" is already mapped to class "%s", cannot map it to "%s" as well. Each serialized type must be unique.', $typeName, $typeToClassMap[$typeName], $class));
                    }

                    $typeToClassMap[$typeName] = $class;
                }
            }
        }

        $container->getDefinition('messenger.transport.symfony_serializer')->setArgument(3, $typeToClassMap);
    }

    private function registerDebugCommandRouting(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('console.command.messenger_debug') || !$container->hasDefinition('messenger.senders_locator')) {
            return;
        }

        $sendersMap = $this->configuredSendersMap ?? $container->getDefinition('messenger.senders_locator')->getArgument(0);
        if (!\is_array($sendersMap)) {
            $sendersMap = [];
        }

        $senderAliases = [];
        foreach ($container->findTaggedServiceIds('messenger.receiver') as $id => $tags) {
            foreach ($tags as $tag) {
                if (isset($tag['alias'])) {
                    $senderAliases[$tag['alias']] = $id;
                }
            }
        }

        $attributeMessages = [];
        foreach ($container->findTaggedResourceIds('messenger.message', false) as $id => $tags) {
            $class = $container->getDefinition($id)->getClass();
            foreach ($tags as $tag) {
                foreach ((array) ($tag['transport'] ?? []) as $transport) {
                    $attributeMessages[$class][] = $transport;
                }
            }
        }

        $failureTransports = [];
        if ($container->hasDefinition('messenger.failure.send_failed_message_to_failure_transport_listener')) {
            $failureTransports = $container->getDefinition('messenger.failure.send_failed_message_to_failure_transport_listener')->getArguments()[2] ?? [];
            if (!\is_array($failureTransports)) {
                $failureTransports = [];
            }
        }

        $container->getDefinition('console.command.messenger_debug')
            ->setArgument(1, $sendersMap)
            ->setArgument(2, $senderAliases)
            ->setArgument(3, $attributeMessages)
            ->setArgument(4, $failureTransports)
            ->setArgument(5, $this->handlerTransports)
        ;
    }

    private function registerDebugCommandMiddleware(ContainerBuilder $container, array $busIds): void
    {
        if (!$container->hasDefinition('console.command.messenger_debug')) {
            return;
        }

        $middlewareByBus = [];
        foreach ($busIds as $busId) {
            $middlewareByBus[$busId] = [];

            $arguments = $container->getDefinition($busId)->getArguments();
            $middleware = $arguments[0] ?? [];
            if ($middleware instanceof TaggedIteratorArgument) {
                $middleware = $this->findAndSortTaggedServices($middleware, $container, $middleware->excludeSelf() ? [$busId] : []);
            } elseif ($middleware instanceof IteratorArgument) {
                $middleware = $middleware->getValues();
            }
            if (!\is_array($middleware)) {
                continue;
            }

            foreach ($middleware as $reference) {
                if (!$reference instanceof Reference) {
                    continue;
                }

                $id = $class = (string) $reference;
                $seen = [];
                do {
                    $definition = !isset($seen[$class]) && $container->has($class) ? $container->findDefinition($class) : null;
                    $seen[$class] = true;
                    $class = $definition instanceof ChildDefinition && !$definition->getClass() ? $definition->getParent() : $definition?->getClass();
                } while ($definition instanceof ChildDefinition && !$definition->getClass());

                $middlewareByBus[$busId][] = [$id, $class];
            }
        }

        $container->getDefinition('console.command.messenger_debug')->setArgument('$middleware', $middlewareByBus);
    }
}
