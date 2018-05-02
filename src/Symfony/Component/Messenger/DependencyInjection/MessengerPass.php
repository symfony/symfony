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
            if ($container->hasParameter($busMiddlewaresParameter = $busId.'.middlewares')) {
                $this->registerBusMiddlewares($container, $busId, $container->getParameter($busMiddlewaresParameter));

                $container->getParameterBag()->remove($busMiddlewaresParameter);
            }

            if ($container->hasDefinition('messenger.data_collector')) {
                $this->registerBusToCollector($container, $busId, $tags[0]);
            }
        }

        $this->registerReceivers($container);
        $this->registerSenders($container);
        $this->registerHandlers($container);
    }

    private function registerHandlers(ContainerBuilder $container)
    {
        $handlersByMessage = array();

        foreach ($container->findTaggedServiceIds($this->handlerTag, true) as $serviceId => $tags) {
            foreach ($tags as $tag) {
                $handles = isset($tag['handles']) ? array($tag['handles']) : $this->guessHandledClasses($r = $container->getReflectionClass($container->getDefinition($serviceId)->getClass()), $serviceId);
                $priority = $tag['priority'] ?? 0;

                foreach ($handles as $messageClass) {
                    if (\is_array($messageClass)) {
                        $messagePriority = $messageClass[1];
                        $messageClass = $messageClass[0];
                    } else {
                        $messagePriority = $priority;
                    }

                    if (!class_exists($messageClass)) {
                        $messageClassLocation = isset($tag['handles']) ? 'declared in your tag attribute "handles"' : sprintf($r->implementsInterface(MessageHandlerInterface::class) ? 'returned by method "%s::getHandledMessages()"' : 'used as argument type in method "%s::__invoke()"', $r->getName());

                        throw new RuntimeException(sprintf('Invalid handler service "%s": message class "%s" %s does not exist.', $serviceId, $messageClass, $messageClassLocation));
                    }

                    $handlersByMessage[$messageClass][$messagePriority][] = new Reference($serviceId);
                }
            }
        }

        foreach ($handlersByMessage as $message => $handlers) {
            krsort($handlersByMessage[$message]);
            $handlersByMessage[$message] = array_merge(...$handlersByMessage[$message]);
        }

        $definitions = array();
        foreach ($handlersByMessage as $message => $handlers) {
            if (1 === \count($handlers)) {
                $handlersByMessage[$message] = current($handlers);
            } else {
                $d = new Definition(ChainHandler::class, array($handlers));
                $d->setPrivate(true);
                $serviceId = hash('sha1', $message);
                $definitions[$serviceId] = $d;
                $handlersByMessage[$message] = new Reference($serviceId);
            }
        }
        $container->addDefinitions($definitions);

        $handlersLocatorMapping = array();
        foreach ($handlersByMessage as $message => $handler) {
            $handlersLocatorMapping['handler.'.$message] = $handler;
        }

        $handlerResolver = $container->getDefinition('messenger.handler_resolver');
        $handlerResolver->replaceArgument(0, ServiceLocatorTagPass::register($container, $handlersLocatorMapping));
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
        foreach ($container->findTaggedServiceIds($this->receiverTag) as $id => $tags) {
            foreach ($tags as $tag) {
                $receiverMapping[$id] = new Reference($id);

                if (isset($tag['name'])) {
                    $receiverMapping[$tag['name']] = $receiverMapping[$id];
                }
            }
        }

        $container->getDefinition('messenger.receiver_locator')->replaceArgument(0, $receiverMapping);
    }

    private function registerSenders(ContainerBuilder $container)
    {
        $senderLocatorMapping = array();
        foreach ($container->findTaggedServiceIds($this->senderTag) as $id => $tags) {
            foreach ($tags as $tag) {
                $senderLocatorMapping[$id] = new Reference($id);

                if (isset($tag['name'])) {
                    $senderLocatorMapping[$tag['name']] = $senderLocatorMapping[$id];
                }
            }
        }

        $container->getDefinition('messenger.sender_locator')->replaceArgument(0, $senderLocatorMapping);
    }

    private function registerBusToCollector(ContainerBuilder $container, string $busId, array $tag)
    {
        $container->setDefinition(
            $tracedBusId = 'debug.traced.'.$busId,
            (new Definition(TraceableMessageBus::class, array(new Reference($tracedBusId.'.inner'))))->setDecoratedService($busId)
        );

        $container->getDefinition('messenger.data_collector')->addMethodCall('registerBus', array($tag['name'] ?? $busId, new Reference($tracedBusId)));
    }

    private function registerBusMiddlewares(ContainerBuilder $container, string $busId, array $middlewares)
    {
        $container->getDefinition($busId)->replaceArgument(0, array_map(function (string $name) use ($container, $busId) {
            if (!$container->has($messengerMiddlewareId = 'messenger.middleware.'.$name)) {
                $messengerMiddlewareId = $name;
            }

            if (!$container->has($messengerMiddlewareId)) {
                throw new RuntimeException(sprintf('Invalid middleware "%s": define such service to be able to use it.', $name));
            }

            if ($container->getDefinition($messengerMiddlewareId)->isAbstract()) {
                $childDefinition = new ChildDefinition($messengerMiddlewareId);

                $container->setDefinition($messengerMiddlewareId = $busId.'.middleware.'.$name, $childDefinition);
            }

            return new Reference($messengerMiddlewareId);
        }, $middlewares));
    }
}
