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
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\Handler\MessageSubscriberInterface;
use Symfony\Component\Messenger\TraceableMessageBus;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 *
 * @experimental in 4.2
 */
class MessengerPass implements CompilerPassInterface
{
    private $handlerTag;
    private $busTag;
    private $receiverTag;

    public function __construct(string $handlerTag = 'messenger.message_handler', string $busTag = 'messenger.bus', string $receiverTag = 'messenger.receiver')
    {
        $this->handlerTag = $handlerTag;
        $this->busTag = $busTag;
        $this->receiverTag = $receiverTag;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $busIds = [];
        foreach ($container->findTaggedServiceIds($this->busTag) as $busId => $tags) {
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

    private function registerHandlers(ContainerBuilder $container, array $busIds)
    {
        $definitions = [];
        $handlersByBusAndMessage = [];

        foreach ($container->findTaggedServiceIds($this->handlerTag, true) as $serviceId => $tags) {
            foreach ($tags as $tag) {
                if (isset($tag['bus']) && !\in_array($tag['bus'], $busIds, true)) {
                    throw new RuntimeException(sprintf('Invalid handler service "%s": bus "%s" specified on the tag "%s" does not exist (known ones are: %s).', $serviceId, $tag['bus'], $this->handlerTag, implode(', ', $busIds)));
                }

                $className = $container->getDefinition($serviceId)->getClass();
                $r = $container->getReflectionClass($className);

                if (null === $r) {
                    throw new RuntimeException(sprintf('Invalid service "%s": class "%s" does not exist.', $serviceId, $className));
                }

                if (isset($tag['handles'])) {
                    $handles = isset($tag['method']) ? [$tag['handles'] => $tag['method']] : [$tag['handles']];
                } else {
                    $handles = $this->guessHandledClasses($r, $serviceId);
                }

                $message = null;
                $handlerBuses = (array) ($tag['bus'] ?? $busIds);

                foreach ($handles as $message => $method) {
                    $buses = $handlerBuses;
                    if (\is_int($message)) {
                        $message = $method;
                        $method = '__invoke';
                    }

                    if (\is_array($message)) {
                        list($message, $priority) = $message;
                    } else {
                        $priority = $tag['priority'] ?? 0;
                    }

                    if (\is_array($method)) {
                        if (isset($method['bus'])) {
                            if (!\in_array($method['bus'], $busIds)) {
                                $messageLocation = isset($tag['handles']) ? 'declared in your tag attribute "handles"' : $r->implementsInterface(MessageSubscriberInterface::class) ? sprintf('returned by method "%s::getHandledMessages()"', $r->getName()) : sprintf('used as argument type in method "%s::%s()"', $r->getName(), $method);

                                throw new RuntimeException(sprintf('Invalid configuration %s for message "%s": bus "%s" does not exist.', $messageLocation, $message, $method['bus']));
                            }

                            $buses = [$method['bus']];
                        }

                        $priority = $method['priority'] ?? $priority;
                        $method = $method['method'] ?? '__invoke';
                    }

                    if ('*' !== $message && !class_exists($message) && !interface_exists($message, false)) {
                        $messageLocation = isset($tag['handles']) ? 'declared in your tag attribute "handles"' : $r->implementsInterface(MessageSubscriberInterface::class) ? sprintf('returned by method "%s::getHandledMessages()"', $r->getName()) : sprintf('used as argument type in method "%s::%s()"', $r->getName(), $method);

                        throw new RuntimeException(sprintf('Invalid handler service "%s": class or interface "%s" %s not found.', $serviceId, $message, $messageLocation));
                    }

                    if (!$r->hasMethod($method)) {
                        throw new RuntimeException(sprintf('Invalid handler service "%s": method "%s::%s()" does not exist.', $serviceId, $r->getName(), $method));
                    }

                    if ('__invoke' !== $method) {
                        $wrapperDefinition = (new Definition('callable'))->addArgument([new Reference($serviceId), $method])->setFactory('Closure::fromCallable');

                        $definitions[$definitionId = '.messenger.method_on_object_wrapper.'.ContainerBuilder::hash($message.':'.$priority.':'.$serviceId.':'.$method)] = $wrapperDefinition;
                    } else {
                        $definitionId = $serviceId;
                    }

                    foreach ($buses as $handlerBus) {
                        $handlersByBusAndMessage[$handlerBus][$message][$priority][] = $definitionId;
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
                $handlersByBusAndMessage[$bus][$message] = array_unique(array_merge(...$handlersByPriority));
            }
        }

        $handlersLocatorMappingByBus = [];
        foreach ($handlersByBusAndMessage as $bus => $handlersByMessage) {
            foreach ($handlersByMessage as $message => $handlerIds) {
                $handlers = array_map(function (string $handlerId) { return new Reference($handlerId); }, $handlerIds);
                $handlersLocatorMappingByBus[$bus][$message] = new IteratorArgument($handlers);
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
            }
            $container->getDefinition('console.command.messenger_debug')->replaceArgument(0, $debugCommandMapping);
        }
    }

    private function guessHandledClasses(\ReflectionClass $handlerClass, string $serviceId): iterable
    {
        if ($handlerClass->implementsInterface(MessageSubscriberInterface::class)) {
            return $handlerClass->getName()::getHandledMessages();
        }

        try {
            $method = $handlerClass->getMethod('__invoke');
        } catch (\ReflectionException $e) {
            throw new RuntimeException(sprintf('Invalid handler service "%s": class "%s" must have an "__invoke()" method.', $serviceId, $handlerClass->getName()));
        }

        $parameters = $method->getParameters();
        if (1 !== \count($parameters)) {
            throw new RuntimeException(sprintf('Invalid handler service "%s": method "%s::__invoke()" must have exactly one argument corresponding to the message it handles.', $serviceId, $handlerClass->getName()));
        }

        if (!$type = $parameters[0]->getType()) {
            throw new RuntimeException(sprintf('Invalid handler service "%s": argument "$%s" of method "%s::__invoke()" must have a type-hint corresponding to the message class it handles.', $serviceId, $parameters[0]->getName(), $handlerClass->getName()));
        }

        if ($type->isBuiltin()) {
            throw new RuntimeException(sprintf('Invalid handler service "%s": type-hint of argument "$%s" in method "%s::__invoke()" must be a class , "%s" given.', $serviceId, $parameters[0]->getName(), $handlerClass->getName(), $type));
        }

        return [(string) $parameters[0]->getType()];
    }

    private function registerReceivers(ContainerBuilder $container, array $busIds)
    {
        $receiverMapping = [];

        foreach ($container->findTaggedServiceIds($this->receiverTag) as $id => $tags) {
            $receiverClass = $container->findDefinition($id)->getClass();
            if (!is_subclass_of($receiverClass, ReceiverInterface::class)) {
                throw new RuntimeException(sprintf('Invalid receiver "%s": class "%s" must implement interface "%s".', $id, $receiverClass, ReceiverInterface::class));
            }

            $receiverMapping[$id] = new Reference($id);

            foreach ($tags as $tag) {
                if (isset($tag['alias'])) {
                    $receiverMapping[$tag['alias']] = $receiverMapping[$id];
                }
            }
        }

        if ($container->hasDefinition('console.command.messenger_consume_messages')) {
            $receiverNames = [];
            foreach ($receiverMapping as $name => $reference) {
                $receiverNames[(string) $reference] = $name;
            }
            $buses = [];
            foreach ($busIds as $busId) {
                $buses[$busId] = new Reference($busId);
            }

            $container->getDefinition('console.command.messenger_consume_messages')
                ->replaceArgument(0, ServiceLocatorTagPass::register($container, $buses))
                ->replaceArgument(3, array_values($receiverNames))
                ->replaceArgument(4, $busIds);
        }

        $container->getDefinition('messenger.receiver_locator')->replaceArgument(0, $receiverMapping);
    }

    private function registerBusToCollector(ContainerBuilder $container, string $busId)
    {
        $container->setDefinition(
            $tracedBusId = 'debug.traced.'.$busId,
            (new Definition(TraceableMessageBus::class, [new Reference($tracedBusId.'.inner')]))->setDecoratedService($busId)
        );

        $container->getDefinition('data_collector.messenger')->addMethodCall('registerBus', [$busId, new Reference($tracedBusId)]);
    }

    private function registerBusMiddleware(ContainerBuilder $container, string $busId, array $middlewareCollection)
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
                $container->setDefinition($messengerMiddlewareId = $busId.'.middleware.'.$id, $childDefinition);
            } elseif ($arguments) {
                throw new RuntimeException(sprintf('Invalid middleware factory "%s": a middleware factory must be an abstract definition.', $id));
            }

            $middlewareReferences[] = new Reference($messengerMiddlewareId);
        }

        $container->getDefinition($busId)->replaceArgument(0, new IteratorArgument($middlewareReferences));
    }
}
