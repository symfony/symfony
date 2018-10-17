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

use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\Handler\ChainHandler;
use Symfony\Component\Messenger\Handler\Locator\ContainerHandlerLocator;
use Symfony\Component\Messenger\Handler\MessageSubscriberInterface;
use Symfony\Component\Messenger\Middleware\Enhancers\TraceableMiddleware;
use Symfony\Component\Messenger\TraceableMessageBus;
use Symfony\Component\Messenger\Transport\ReceiverInterface;
use Symfony\Component\Messenger\Transport\SenderInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class MessengerPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    private $handlerTag;
    private $busTag;
    private $senderTag;
    private $receiverTag;
    private $debugStopwatchId;

    public function __construct(string $handlerTag = 'messenger.message_handler', string $busTag = 'messenger.bus', string $senderTag = 'messenger.sender', string $receiverTag = 'messenger.receiver', string $debugStopwatchId = 'debug.stopwatch')
    {
        $this->handlerTag = $handlerTag;
        $this->busTag = $busTag;
        $this->senderTag = $senderTag;
        $this->receiverTag = $receiverTag;
        $this->debugStopwatchId = $debugStopwatchId;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->has('message_bus')) {
            return;
        }

        $busIds = array();
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

        $this->registerReceivers($container, $busIds);
        $this->registerSenders($container);
        $this->registerHandlers($container, $busIds);
    }

    private function registerHandlers(ContainerBuilder $container, array $busIds)
    {
        $definitions = array();
        $handlersByBusAndMessage = array();

        foreach ($container->findTaggedServiceIds($this->handlerTag, true) as $serviceId => $tags) {
            foreach ($tags as $tag) {
                if (isset($tag['bus']) && !\in_array($tag['bus'], $busIds, true)) {
                    throw new RuntimeException(sprintf('Invalid handler service "%s": bus "%s" specified on the tag "%s" does not exist (known ones are: %s).', $serviceId, $tag['bus'], $this->handlerTag, implode(', ', $busIds)));
                }

                $r = $container->getReflectionClass($container->getDefinition($serviceId)->getClass());

                if (isset($tag['handles'])) {
                    $handles = isset($tag['method']) ? array($tag['handles'] => $tag['method']) : array($tag['handles']);
                } else {
                    $handles = $this->guessHandledClasses($r, $serviceId);
                }

                $priority = $tag['priority'] ?? 0;
                $handlerBuses = (array) ($tag['bus'] ?? $busIds);

                foreach ($handles as $messageClass => $method) {
                    $buses = $handlerBuses;
                    if (\is_int($messageClass)) {
                        $messageClass = $method;
                        $method = '__invoke';
                    }

                    if (\is_array($messageClass)) {
                        $messagePriority = $messageClass[1];
                        $messageClass = $messageClass[0];
                    } else {
                        $messagePriority = $priority;
                    }

                    if (\is_array($method)) {
                        if (isset($method['bus'])) {
                            if (!\in_array($method['bus'], $busIds)) {
                                $messageClassLocation = isset($tag['handles']) ? 'declared in your tag attribute "handles"' : $r->implementsInterface(MessageSubscriberInterface::class) ? sprintf('returned by method "%s::getHandledMessages()"', $r->getName()) : sprintf('used as argument type in method "%s::%s()"', $r->getName(), $method);

                                throw new RuntimeException(sprintf('Invalid configuration %s for message "%s": bus "%s" does not exist.', $messageClassLocation, $messageClass, $method['bus']));
                            }

                            $buses = array($method['bus']);
                        }

                        if (isset($method['priority'])) {
                            $messagePriority = $method['priority'];
                        }

                        $method = $method['method'] ?? '__invoke';
                    }

                    if (!\class_exists($messageClass) && !\interface_exists($messageClass, false)) {
                        $messageClassLocation = isset($tag['handles']) ? 'declared in your tag attribute "handles"' : $r->implementsInterface(MessageSubscriberInterface::class) ? sprintf('returned by method "%s::getHandledMessages()"', $r->getName()) : sprintf('used as argument type in method "%s::%s()"', $r->getName(), $method);

                        throw new RuntimeException(sprintf('Invalid handler service "%s": message class "%s" %s does not exist.', $serviceId, $messageClass, $messageClassLocation));
                    }

                    if (!$r->hasMethod($method)) {
                        throw new RuntimeException(sprintf('Invalid handler service "%s": method "%s::%s()" does not exist.', $serviceId, $r->getName(), $method));
                    }

                    if ('__invoke' !== $method) {
                        $wrapperDefinition = (new Definition('callable'))->addArgument(array(new Reference($serviceId), $method))->setFactory('Closure::fromCallable');

                        $definitions[$definitionId = '.messenger.method_on_object_wrapper.'.ContainerBuilder::hash($messageClass.':'.$messagePriority.':'.$serviceId.':'.$method)] = $wrapperDefinition;
                    } else {
                        $definitionId = $serviceId;
                    }

                    foreach ($buses as $handlerBus) {
                        $handlersByBusAndMessage[$handlerBus][$messageClass][$messagePriority][] = $definitionId;
                    }
                }
            }
        }

        foreach ($handlersByBusAndMessage as $bus => $handlersByMessage) {
            foreach ($handlersByMessage as $message => $handlersByPriority) {
                krsort($handlersByPriority);
                $handlersByBusAndMessage[$bus][$message] = array_unique(array_merge(...$handlersByPriority));
            }
        }

        $handlersLocatorMappingByBus = array();
        foreach ($handlersByBusAndMessage as $bus => $handlersByMessage) {
            foreach ($handlersByMessage as $message => $handlersIds) {
                if (1 === \count($handlersIds)) {
                    $handlersLocatorMappingByBus[$bus][$message] = new Reference(current($handlersIds));
                } else {
                    $chainHandler = new Definition(ChainHandler::class, array(array_map(function (string $handlerId): Reference {
                        return new Reference($handlerId);
                    }, $handlersIds)));
                    $chainHandler->setPrivate(true);
                    $serviceId = '.messenger.chain_handler.'.ContainerBuilder::hash($bus.$message);
                    $definitions[$serviceId] = $chainHandler;
                    $handlersLocatorMappingByBus[$bus][$message] = new Reference($serviceId);
                }
            }
        }
        $container->addDefinitions($definitions);

        foreach ($busIds as $bus) {
            $container->register($resolverName = "$bus.messenger.handler_resolver", ContainerHandlerLocator::class)
                ->setArgument(0, ServiceLocatorTagPass::register($container, $handlersLocatorMappingByBus[$bus] ?? array()))
            ;
            if ($container->has($callMessageHandlerId = "$bus.middleware.call_message_handler")) {
                $container->getDefinition($callMessageHandlerId)
                    ->replaceArgument(0, new Reference($resolverName))
                ;
            }
        }

        if ($container->hasDefinition('console.command.messenger_debug')) {
            $debugCommandMapping = $handlersByBusAndMessage;
            foreach ($busIds as $bus) {
                if (!isset($debugCommandMapping[$bus])) {
                    $debugCommandMapping[$bus] = array();
                }
            }
            $container->getDefinition('console.command.messenger_debug')->replaceArgument(0, $debugCommandMapping);
        }
    }

    private function guessHandledClasses(\ReflectionClass $handlerClass, string $serviceId): iterable
    {
        if ($handlerClass->implementsInterface(MessageSubscriberInterface::class)) {
            if (!$handledMessages = $handlerClass->getName()::getHandledMessages()) {
                throw new RuntimeException(sprintf('Invalid handler service "%s": method "%s::getHandledMessages()" must return one or more messages.', $serviceId, $handlerClass->getName()));
            }

            return $handledMessages;
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

        return array((string) $parameters[0]->getType());
    }

    private function registerReceivers(ContainerBuilder $container, array $busIds)
    {
        $receiverMapping = array();

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
            $receiverNames = array();
            foreach ($receiverMapping as $name => $reference) {
                $receiverNames[(string) $reference] = $name;
            }
            $buses = array();
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

    private function registerSenders(ContainerBuilder $container)
    {
        $senderLocatorMapping = array();
        foreach ($container->findTaggedServiceIds($this->senderTag) as $id => $tags) {
            $senderClass = $container->findDefinition($id)->getClass();
            if (!is_subclass_of($senderClass, SenderInterface::class)) {
                throw new RuntimeException(sprintf('Invalid sender "%s": class "%s" must implement interface "%s".', $id, $senderClass, SenderInterface::class));
            }

            $senderLocatorMapping[$id] = new Reference($id);

            foreach ($tags as $tag) {
                if (isset($tag['alias'])) {
                    $senderLocatorMapping[$tag['alias']] = $senderLocatorMapping[$id];
                }
            }
        }

        $container->getDefinition('messenger.sender_locator')->replaceArgument(0, $senderLocatorMapping);
    }

    private function registerBusToCollector(ContainerBuilder $container, string $busId)
    {
        $container->setDefinition(
            $tracedBusId = 'debug.traced.'.$busId,
            (new Definition(TraceableMessageBus::class, array(new Reference($tracedBusId.'.inner'))))->setDecoratedService($busId)
        );

        $container->getDefinition('data_collector.messenger')->addMethodCall('registerBus', array($busId, new Reference($tracedBusId)));
    }

    private function registerBusMiddleware(ContainerBuilder $container, string $busId, array $middlewareCollection)
    {
        $debug = $container->getParameter('kernel.debug') && $container->has($this->debugStopwatchId);
        $middlewareReferences = array();
        foreach ($middlewareCollection as $middlewareItem) {
            $id = $middlewareItem['id'];
            $arguments = $middlewareItem['arguments'] ?? array();
            if (!$container->has($messengerMiddlewareId = 'messenger.middleware.'.$id)) {
                $messengerMiddlewareId = $id;
            }

            if (!$container->has($messengerMiddlewareId)) {
                throw new RuntimeException(sprintf('Invalid middleware "%s": define such service to be able to use it.', $id));
            }

            if ($isDefinitionAbstract = ($definition = $container->findDefinition($messengerMiddlewareId))->isAbstract()) {
                $childDefinition = new ChildDefinition($messengerMiddlewareId);
                $count = \count($definition->getArguments());
                foreach (array_values($arguments ?? array()) as $key => $argument) {
                    // Parent definition can provide default arguments.
                    // Replace each explicitly or add if not set:
                    $key < $count ? $childDefinition->replaceArgument($key, $argument) : $childDefinition->addArgument($argument);
                }

                $container->setDefinition($messengerMiddlewareId = $busId.'.middleware.'.$id, $childDefinition);
            } elseif ($arguments) {
                throw new RuntimeException(sprintf('Invalid middleware factory "%s": a middleware factory must be an abstract definition.', $id));
            }

            if ($debug) {
                $container->register($debugMiddlewareId = '.messenger.debug.traced.'.$messengerMiddlewareId, TraceableMiddleware::class)
                    // Decorates with a high priority so it's applied the earliest:
                    ->setDecoratedService($messengerMiddlewareId, null, 100)
                    ->setArguments(array(
                        new Reference($debugMiddlewareId.'.inner'),
                        new Reference($this->debugStopwatchId),
                        // In case the definition isn't abstract,
                        // we cannot be sure the service instance is used by one bus only.
                        // So we only inject the bus name when the original definition is abstract.
                        $isDefinitionAbstract ? $busId : null,
                    ))
                ;
            }

            $middlewareReferences[] = new Reference($messengerMiddlewareId);
        }

        $container->getDefinition($busId)->replaceArgument(0, $middlewareReferences);
    }
}
