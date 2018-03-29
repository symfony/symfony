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

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\Handler\ChainHandler;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class MessengerPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    private $messageBusService;
    private $messageHandlerResolverService;
    private $handlerTag;

    public function __construct(string $messageBusService = 'message_bus', string $messageHandlerResolverService = 'messenger.handler_resolver', string $handlerTag = 'messenger.message_handler')
    {
        $this->messageBusService = $messageBusService;
        $this->messageHandlerResolverService = $messageHandlerResolverService;
        $this->handlerTag = $handlerTag;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition($this->messageBusService)) {
            return;
        }

        if (!$container->getParameter('kernel.debug') || !$container->has('logger')) {
            $container->removeDefinition('messenger.middleware.debug.logging');
        }

        if (!$container->has('serializer')) {
            $container->removeDefinition('messenger.transport.serialize_message_with_type_in_headers');
            $container->removeAlias('messenger.transport.default_encoder');
            $container->removeAlias('messenger.transport.default_decoder');
        }

        $this->registerReceivers($container);
        $this->registerHandlers($container);
    }

    private function registerHandlers(ContainerBuilder $container)
    {
        $handlersByMessage = array();

        foreach ($container->findTaggedServiceIds($this->handlerTag, true) as $serviceId => $tags) {
            foreach ($tags as $tag) {
                $handles = $tag['handles'] ?? $this->guessHandledClass($r = $container->getReflectionClass($container->getParameterBag()->resolveValue($container->getDefinition($serviceId)->getClass())), $serviceId);

                if (!class_exists($handles)) {
                    $messageClassLocation = isset($tag['handles']) ? 'declared in your tag attribute "handles"' : sprintf('used as argument type in method "%s::__invoke()"', $r->getName());

                    throw new RuntimeException(sprintf('Invalid handler service "%s": message class "%s" %s does not exist.', $serviceId, $handles, $messageClassLocation));
                }

                $priority = $tag['priority'] ?? 0;
                $handlersByMessage[$handles][$priority][] = new Reference($serviceId);
            }
        }

        foreach ($handlersByMessage as $message => $handlers) {
            krsort($handlersByMessage[$message]);
            $handlersByMessage[$message] = call_user_func_array('array_merge', $handlersByMessage[$message]);
        }

        $definitions = array();
        foreach ($handlersByMessage as $message => $handlers) {
            if (1 === count($handlers)) {
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

        $handlerResolver = $container->getDefinition($this->messageHandlerResolverService);
        $handlerResolver->replaceArgument(0, ServiceLocatorTagPass::register($container, $handlersLocatorMapping));
    }

    private function guessHandledClass(\ReflectionClass $handlerClass, string $serviceId): string
    {
        try {
            $method = $handlerClass->getMethod('__invoke');
        } catch (\ReflectionException $e) {
            throw new RuntimeException(sprintf('Invalid handler service "%s": class "%s" must have an "__invoke()" method.', $serviceId, $handlerClass->getName()));
        }

        $parameters = $method->getParameters();
        if (1 !== count($parameters)) {
            throw new RuntimeException(sprintf('Invalid handler service "%s": method "%s::__invoke()" must have exactly one argument corresponding to the message it handles.', $serviceId, $handlerClass->getName()));
        }

        if (!$type = $parameters[0]->getType()) {
            throw new RuntimeException(sprintf('Invalid handler service "%s": argument "$%s" of method "%s::__invoke()" must have a type-hint corresponding to the message class it handles.', $serviceId, $parameters[0]->getName(), $handlerClass->getName()));
        }

        if ($type->isBuiltin()) {
            throw new RuntimeException(sprintf('Invalid handler service "%s": type-hint of argument "$%s" in method "%s::__invoke()" must be a class , "%s" given.', $serviceId, $parameters[0]->getName(), $handlerClass->getName(), $type));
        }

        return $parameters[0]->getType();
    }

    private function registerReceivers(ContainerBuilder $container)
    {
        $receiverMapping = array();
        foreach ($container->findTaggedServiceIds('messenger.receiver') as $id => $tags) {
            foreach ($tags as $tag) {
                $receiverMapping[$tag['id'] ?? $id] = new Reference($id);
            }
        }

        $container->getDefinition('messenger.receiver_locator')->replaceArgument(0, $receiverMapping);
    }
}
