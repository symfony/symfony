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
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\Handler\MessageSubscriberInterface;
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

    public function __construct(string $handlerTag = 'messenger.message_handler', string $busTag = 'messenger.bus', string $senderTag = 'messenger.sender', string $receiverTag = 'messenger.receiver')
    {
        $this->handlerTag = $handlerTag;
        $this->busTag = $busTag;
        $this->senderTag = $senderTag;
        $this->receiverTag = $receiverTag;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('messenger.handler_resolver')) {
            return;
        }

        foreach ($container->findTaggedServiceIds($this->busTag) as $busId => $tags) {
            if ($container->hasParameter($busMiddlewareParameter = $busId.'.middleware')) {
                $this->registerBusMiddleware($container, $busId, $container->getParameter($busMiddlewareParameter));

                $container->getParameterBag()->remove($busMiddlewareParameter);
            }

            if ($container->hasDefinition('messenger.data_collector')) {
                $this->registerBusToCollector($container, $busId);
            }
        }

        $this->registerReceivers($container);
        $this->registerSenders($container);
        $this->registerHandlers($container);
    }

    private function registerHandlers(ContainerBuilder $container)
    {
        $definitions = array();
        $handlersByMessage = array();

        foreach ($container->findTaggedServiceIds($this->handlerTag, true) as $serviceId => $tags) {
            foreach ($tags as $tag) {
                $r = $container->getReflectionClass($container->getDefinition($serviceId)->getClass());

                if (isset($tag['handles'])) {
                    $handles = isset($tag['method']) ? array($tag['handles'] => $tag['method']) : array($tag['handles']);
                } else {
                    $handles = $this->guessHandledClasses($r, $serviceId);
                }

                $priority = $tag['priority'] ?? 0;

                foreach ($handles as $messageClass => $method) {
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
                        $messagePriority = $method[1];
                        $method = $method[0];
                    }

                    if (!\class_exists($messageClass)) {
                        $messageClassLocation = isset($tag['handles']) ? 'declared in your tag attribute "handles"' : $r->implementsInterface(MessageHandlerInterface::class) ? sprintf('returned by method "%s::getHandledMessages()"', $r->getName()) : sprintf('used as argument type in method "%s::%s()"', $r->getName(), $method);

                        throw new RuntimeException(sprintf('Invalid handler service "%s": message class "%s" %s does not exist.', $serviceId, $messageClass, $messageClassLocation));
                    }

                    if (!$r->hasMethod($method)) {
                        throw new RuntimeException(sprintf('Invalid handler service "%s": method "%s::%s()" does not exist.', $serviceId, $r->getName(), $method));
                    }

                    if ('__invoke' !== $method) {
                        $wrapperDefinition = (new Definition('callable'))->addArgument(array(new Reference($serviceId), $method))->setFactory('Closure::fromCallable');

                        $definitions[$serviceId = '.messenger.method_on_object_wrapper.'.ContainerBuilder::hash($messageClass.':'.$messagePriority.':'.$serviceId.':'.$method)] = $wrapperDefinition;
                    }

                    $handlersByMessage[$messageClass][$messagePriority][] = new Reference($serviceId);
                }
            }
        }

        foreach ($handlersByMessage as $message => $handlers) {
            krsort($handlersByMessage[$message]);
            $handlersByMessage[$message] = array_merge(...$handlersByMessage[$message]);
        }

        $handlersLocatorMapping = array();
        foreach ($handlersByMessage as $message => $handlers) {
            if (1 === \count($handlers)) {
                $handlersLocatorMapping['handler.'.$message] = current($handlers);
            } else {
                $d = new Definition(ChainHandler::class, array($handlers));
                $d->setPrivate(true);
                $serviceId = hash('sha1', $message);
                $definitions[$serviceId] = $d;
                $handlersLocatorMapping['handler.'.$message] = new Reference($serviceId);
            }
        }
        $container->addDefinitions($definitions);

        $handlerResolver = $container->getDefinition('messenger.handler_resolver');
        $handlerResolver->replaceArgument(0, ServiceLocatorTagPass::register($container, $handlersLocatorMapping));

        if ($container->hasDefinition('console.command.messenger_debug')) {
            $container->getDefinition('console.command.messenger_debug')
                ->replaceArgument(0, array_map(function (array $handlers): array {
                    return array_map('strval', $handlers);
                }, $handlersByMessage));
        }
    }

    private function guessHandledClasses(\ReflectionClass $handlerClass, string $serviceId): array
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

    private function registerReceivers(ContainerBuilder $container)
    {
        $receiverMapping = array();
        $taggedReceivers = $container->findTaggedServiceIds($this->receiverTag);

        foreach ($taggedReceivers as $id => $tags) {
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

        if (1 === \count($taggedReceivers) && $container->hasDefinition('console.command.messenger_consume_messages')) {
            $container->getDefinition('console.command.messenger_consume_messages')->replaceArgument(3, (string) current($receiverMapping));
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

        $container->getDefinition('messenger.data_collector')->addMethodCall('registerBus', array($busId, new Reference($tracedBusId)));
    }

    private function registerBusMiddleware(ContainerBuilder $container, string $busId, array $middlewareCollection)
    {
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

            if (($definition = $container->findDefinition($messengerMiddlewareId))->isAbstract()) {
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

            $middlewareReferences[] = new Reference($messengerMiddlewareId);
        }

        $container->getDefinition($busId)->replaceArgument(0, $middlewareReferences);
    }
}
