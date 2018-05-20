<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Compiler\ResolveChildDefinitionsPass;
use Symfony\Component\DependencyInjection\Compiler\ResolveClassPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Command\ConsumeMessagesCommand;
use Symfony\Component\Messenger\Command\DebugCommand;
use Symfony\Component\Messenger\DataCollector\MessengerDataCollector;
use Symfony\Component\Messenger\DependencyInjection\MessengerPass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\ChainHandler;
use Symfony\Component\Messenger\Handler\MessageSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\AllowNoHandlerMiddleware;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Tests\Fixtures\DummyCommand;
use Symfony\Component\Messenger\Tests\Fixtures\DummyCommandHandler;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\DummyQuery;
use Symfony\Component\Messenger\Tests\Fixtures\DummyQueryHandler;
use Symfony\Component\Messenger\Tests\Fixtures\MultipleBusesMessage;
use Symfony\Component\Messenger\Tests\Fixtures\MultipleBusesMessageHandler;
use Symfony\Component\Messenger\Tests\Fixtures\SecondMessage;
use Symfony\Component\Messenger\Transport\AmqpExt\AmqpReceiver;
use Symfony\Component\Messenger\Transport\AmqpExt\AmqpSender;
use Symfony\Component\Messenger\Transport\ReceiverInterface;

class MessengerPassTest extends TestCase
{
    public function testProcess()
    {
        $container = $this->getContainerBuilder($busId = 'message_bus');
        $container
            ->register(DummyHandler::class, DummyHandler::class)
            ->addTag('messenger.message_handler')
        ;
        $container
            ->register(MissingArgumentTypeHandler::class, MissingArgumentTypeHandler::class)
            ->addTag('messenger.message_handler', array('handles' => SecondMessage::class))
        ;
        $container
            ->register(DummyReceiver::class, DummyReceiver::class)
            ->addTag('messenger.receiver')
        ;

        (new MessengerPass())->process($container);

        $this->assertFalse($container->hasDefinition('messenger.middleware.debug.logging'));

        $handlerLocatorDefinition = $container->getDefinition($container->getDefinition("$busId.messenger.handler_resolver")->getArgument(0));
        $this->assertSame(ServiceLocator::class, $handlerLocatorDefinition->getClass());
        $this->assertEquals(
            array(
                'handler.'.DummyMessage::class => new ServiceClosureArgument(new Reference(DummyHandler::class)),
                'handler.'.SecondMessage::class => new ServiceClosureArgument(new Reference(MissingArgumentTypeHandler::class)),
            ),
            $handlerLocatorDefinition->getArgument(0)
        );

        $this->assertEquals(
            array(DummyReceiver::class => new Reference(DummyReceiver::class)),
            $container->getDefinition('messenger.receiver_locator')->getArgument(0)
        );
    }

    public function testProcessHandlersByBus()
    {
        $container = $this->getContainerBuilder($commandBusId = 'command_bus');
        $container->register($queryBusId = 'query_bus', MessageBusInterface::class)->setArgument(0, array())->addTag('messenger.bus');
        $container->register('messenger.middleware.call_message_handler', HandleMessageMiddleware::class)
            ->addArgument(null)
            ->setAbstract(true)
        ;

        $middlewares = array(array('id' => 'call_message_handler'));

        $container->setParameter($commandBusId.'.middleware', $middlewares);
        $container->setParameter($queryBusId.'.middleware', $middlewares);

        $container->register(DummyCommandHandler::class)->addTag('messenger.message_handler', array('bus' => $commandBusId));
        $container->register(DummyQueryHandler::class)->addTag('messenger.message_handler', array('bus' => $queryBusId));
        $container->register(MultipleBusesMessageHandler::class)
            ->addTag('messenger.message_handler', array('bus' => $commandBusId))
            ->addTag('messenger.message_handler', array('bus' => $queryBusId))
        ;

        (new ResolveClassPass())->process($container);
        (new MessengerPass())->process($container);

        $commandBusHandlerLocatorDefinition = $container->getDefinition($container->getDefinition("$commandBusId.messenger.handler_resolver")->getArgument(0));
        $this->assertSame(ServiceLocator::class, $commandBusHandlerLocatorDefinition->getClass());
        $this->assertEquals(
            array(
                'handler.'.DummyCommand::class => new ServiceClosureArgument(new Reference(DummyCommandHandler::class)),
                'handler.'.MultipleBusesMessage::class => new ServiceClosureArgument(new Reference(MultipleBusesMessageHandler::class)),
            ),
            $commandBusHandlerLocatorDefinition->getArgument(0)
        );

        $queryBusHandlerLocatorDefinition = $container->getDefinition($container->getDefinition("$queryBusId.messenger.handler_resolver")->getArgument(0));
        $this->assertSame(ServiceLocator::class, $queryBusHandlerLocatorDefinition->getClass());
        $this->assertEquals(
            array(
                'handler.'.DummyQuery::class => new ServiceClosureArgument(new Reference(DummyQueryHandler::class)),
                'handler.'.MultipleBusesMessage::class => new ServiceClosureArgument(new Reference(MultipleBusesMessageHandler::class)),
            ),
            $queryBusHandlerLocatorDefinition->getArgument(0)
        );
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Invalid handler service "Symfony\Component\Messenger\Tests\Fixtures\DummyCommandHandler": bus "unknown_bus" specified on the tag "messenger.message_handler" does not exist (known ones are: command_bus).
     */
    public function testProcessTagWithUnknownBus()
    {
        $container = $this->getContainerBuilder($commandBusId = 'command_bus');

        $container->register(DummyCommandHandler::class)->addTag('messenger.message_handler', array('bus' => 'unknown_bus'));

        (new ResolveClassPass())->process($container);
        (new MessengerPass())->process($container);
    }

    public function testGetClassesFromTheHandlerSubscriberInterface()
    {
        $container = $this->getContainerBuilder($busId = 'message_bus');
        $container
            ->register(HandlerWithMultipleMessages::class, HandlerWithMultipleMessages::class)
            ->addTag('messenger.message_handler')
        ;
        $container
            ->register(PrioritizedHandler::class, PrioritizedHandler::class)
            ->addTag('messenger.message_handler')
        ;

        (new MessengerPass())->process($container);

        $handlerLocatorDefinition = $container->getDefinition($container->getDefinition("$busId.messenger.handler_resolver")->getArgument(0));
        $handlerMapping = $handlerLocatorDefinition->getArgument(0);

        $this->assertArrayHasKey('handler.'.DummyMessage::class, $handlerMapping);
        $this->assertEquals(new ServiceClosureArgument(new Reference(HandlerWithMultipleMessages::class)), $handlerMapping['handler.'.DummyMessage::class]);

        $this->assertArrayHasKey('handler.'.SecondMessage::class, $handlerMapping);
        $handlerReference = (string) $handlerMapping['handler.'.SecondMessage::class]->getValues()[0];
        $definition = $container->getDefinition($handlerReference);

        $this->assertSame(ChainHandler::class, $definition->getClass());
        $this->assertEquals(array(new Reference(PrioritizedHandler::class), new Reference(HandlerWithMultipleMessages::class)), $definition->getArgument(0));
    }

    public function testGetClassesAndMethodsAndPrioritiesFromTheSubscriber()
    {
        $container = $this->getContainerBuilder($busId = 'message_bus');
        $container
            ->register(HandlerMappingMethods::class, HandlerMappingMethods::class)
            ->addTag('messenger.message_handler')
        ;
        $container
            ->register(PrioritizedHandler::class, PrioritizedHandler::class)
            ->addTag('messenger.message_handler')
        ;

        (new MessengerPass())->process($container);

        $handlerLocatorDefinition = $container->getDefinition($container->getDefinition("$busId.messenger.handler_resolver")->getArgument(0));
        $handlerMapping = $handlerLocatorDefinition->getArgument(0);

        $this->assertArrayHasKey('handler.'.DummyMessage::class, $handlerMapping);
        $this->assertArrayHasKey('handler.'.SecondMessage::class, $handlerMapping);

        $dummyHandlerReference = (string) $handlerMapping['handler.'.DummyMessage::class]->getValues()[0];
        $dummyHandlerDefinition = $container->getDefinition($dummyHandlerReference);
        $this->assertSame('callable', $dummyHandlerDefinition->getClass());
        $this->assertEquals(array(new Reference(HandlerMappingMethods::class), 'dummyMethod'), $dummyHandlerDefinition->getArgument(0));
        $this->assertSame(array('Closure', 'fromCallable'), $dummyHandlerDefinition->getFactory());

        $secondHandlerReference = (string) $handlerMapping['handler.'.SecondMessage::class]->getValues()[0];
        $secondHandlerDefinition = $container->getDefinition($secondHandlerReference);
        $this->assertSame(ChainHandler::class, $secondHandlerDefinition->getClass());
        $this->assertEquals(new Reference(PrioritizedHandler::class), $secondHandlerDefinition->getArgument(0)[1]);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Invalid handler service "Symfony\Component\Messenger\Tests\DependencyInjection\HandlerMappingWithNonExistentMethod": method "Symfony\Component\Messenger\Tests\DependencyInjection\HandlerMappingWithNonExistentMethod::dummyMethod()" does not exist.
     */
    public function testThrowsExceptionIfTheHandlerMethodDoesNotExist()
    {
        $container = $this->getContainerBuilder();
        $container->register('message_bus', MessageBusInterface::class)->addTag('messenger.bus');
        $container
            ->register(HandlerMappingWithNonExistentMethod::class, HandlerMappingWithNonExistentMethod::class)
            ->addTag('messenger.message_handler')
        ;

        (new MessengerPass())->process($container);
    }

    public function testItRegistersReceivers()
    {
        $container = $this->getContainerBuilder();
        $container->register('message_bus', MessageBusInterface::class)->addTag('messenger.bus');
        $container->register(AmqpReceiver::class, AmqpReceiver::class)->addTag('messenger.receiver', array('alias' => 'amqp'));

        (new MessengerPass())->process($container);

        $this->assertEquals(array('amqp' => new Reference(AmqpReceiver::class), AmqpReceiver::class => new Reference(AmqpReceiver::class)), $container->getDefinition('messenger.receiver_locator')->getArgument(0));
    }

    public function testItRegistersReceiversWithoutTagName()
    {
        $container = $this->getContainerBuilder();
        $container->register('message_bus', MessageBusInterface::class)->addTag('messenger.bus');
        $container->register(AmqpReceiver::class, AmqpReceiver::class)->addTag('messenger.receiver');

        (new MessengerPass())->process($container);

        $this->assertEquals(array(AmqpReceiver::class => new Reference(AmqpReceiver::class)), $container->getDefinition('messenger.receiver_locator')->getArgument(0));
    }

    public function testItRegistersMultipleReceiversAndSetsTheReceiverNamesOnTheCommand()
    {
        $container = $this->getContainerBuilder();
        $container->register('console.command.messenger_consume_messages', ConsumeMessagesCommand::class)->setArguments(array(
            new Reference('message_bus'),
            new Reference('messenger.receiver_locator'),
            null,
            null,
        ));

        $container->register(AmqpReceiver::class, AmqpReceiver::class)->addTag('messenger.receiver', array('alias' => 'amqp'));
        $container->register(DummyReceiver::class, DummyReceiver::class)->addTag('messenger.receiver', array('alias' => 'dummy'));

        (new MessengerPass())->process($container);

        $this->assertSame(array('amqp', 'dummy'), $container->getDefinition('console.command.messenger_consume_messages')->getArgument(3));
    }

    public function testItRegistersSenders()
    {
        $container = $this->getContainerBuilder();
        $container->register(AmqpSender::class, AmqpSender::class)->addTag('messenger.sender', array('alias' => 'amqp'));

        (new MessengerPass())->process($container);

        $this->assertEquals(array('amqp' => new Reference(AmqpSender::class), AmqpSender::class => new Reference(AmqpSender::class)), $container->getDefinition('messenger.sender_locator')->getArgument(0));
    }

    public function testItRegistersSenderWithoutTagName()
    {
        $container = $this->getContainerBuilder();
        $container->register(AmqpSender::class, AmqpSender::class)->addTag('messenger.sender');

        (new MessengerPass())->process($container);

        $this->assertEquals(array(AmqpSender::class => new Reference(AmqpSender::class)), $container->getDefinition('messenger.sender_locator')->getArgument(0));
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Invalid sender "app.messenger.sender": class "Symfony\Component\Messenger\Tests\DependencyInjection\InvalidSender" must implement interface "Symfony\Component\Messenger\Transport\SenderInterface".
     */
    public function testItDoesNotRegisterInvalidSender()
    {
        $container = $this->getContainerBuilder();
        $container->register('app.messenger.sender', InvalidSender::class)
            ->addTag('messenger.sender');

        (new MessengerPass())->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Invalid handler service "Symfony\Component\Messenger\Tests\DependencyInjection\UndefinedMessageHandler": message class "Symfony\Component\Messenger\Tests\DependencyInjection\UndefinedMessage" used as argument type in method "Symfony\Component\Messenger\Tests\DependencyInjection\UndefinedMessageHandler::__invoke()" does not exist.
     */
    public function testUndefinedMessageClassForHandler()
    {
        $container = $this->getContainerBuilder();
        $container
            ->register(UndefinedMessageHandler::class, UndefinedMessageHandler::class)
            ->addTag('messenger.message_handler')
        ;

        (new MessengerPass())->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Invalid handler service "Symfony\Component\Messenger\Tests\DependencyInjection\UndefinedMessageHandlerViaInterface": message class "Symfony\Component\Messenger\Tests\DependencyInjection\UndefinedMessage" returned by method "Symfony\Component\Messenger\Tests\DependencyInjection\UndefinedMessageHandlerViaInterface::getHandledMessages()" does not exist.
     */
    public function testUndefinedMessageClassForHandlerViaInterface()
    {
        $container = $this->getContainerBuilder();
        $container
            ->register(UndefinedMessageHandlerViaInterface::class, UndefinedMessageHandlerViaInterface::class)
            ->addTag('messenger.message_handler')
        ;

        (new MessengerPass())->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Invalid handler service "Symfony\Component\Messenger\Tests\DependencyInjection\NotInvokableHandler": class "Symfony\Component\Messenger\Tests\DependencyInjection\NotInvokableHandler" must have an "__invoke()" method.
     */
    public function testNotInvokableHandler()
    {
        $container = $this->getContainerBuilder();
        $container
            ->register(NotInvokableHandler::class, NotInvokableHandler::class)
            ->addTag('messenger.message_handler')
        ;

        (new MessengerPass())->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Invalid handler service "Symfony\Component\Messenger\Tests\DependencyInjection\MissingArgumentHandler": method "Symfony\Component\Messenger\Tests\DependencyInjection\MissingArgumentHandler::__invoke()" must have exactly one argument corresponding to the message it handles.
     */
    public function testMissingArgumentHandler()
    {
        $container = $this->getContainerBuilder();
        $container
            ->register(MissingArgumentHandler::class, MissingArgumentHandler::class)
            ->addTag('messenger.message_handler')
        ;

        (new MessengerPass())->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Invalid handler service "Symfony\Component\Messenger\Tests\DependencyInjection\MissingArgumentTypeHandler": argument "$message" of method "Symfony\Component\Messenger\Tests\DependencyInjection\MissingArgumentTypeHandler::__invoke()" must have a type-hint corresponding to the message class it handles.
     */
    public function testMissingArgumentTypeHandler()
    {
        $container = $this->getContainerBuilder();
        $container
            ->register(MissingArgumentTypeHandler::class, MissingArgumentTypeHandler::class)
            ->addTag('messenger.message_handler')
        ;

        (new MessengerPass())->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Invalid handler service "Symfony\Component\Messenger\Tests\DependencyInjection\BuiltinArgumentTypeHandler": type-hint of argument "$message" in method "Symfony\Component\Messenger\Tests\DependencyInjection\BuiltinArgumentTypeHandler::__invoke()" must be a class , "string" given.
     */
    public function testBuiltinArgumentTypeHandler()
    {
        $container = $this->getContainerBuilder();
        $container
            ->register(BuiltinArgumentTypeHandler::class, BuiltinArgumentTypeHandler::class)
            ->addTag('messenger.message_handler')
        ;

        (new MessengerPass())->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Invalid handler service "Symfony\Component\Messenger\Tests\DependencyInjection\HandleNoMessageHandler": method "Symfony\Component\Messenger\Tests\DependencyInjection\HandleNoMessageHandler::getHandledMessages()" must return one or more messages.
     */
    public function testNeedsToHandleAtLeastOneMessage()
    {
        $container = $this->getContainerBuilder();
        $container
            ->register(HandleNoMessageHandler::class, HandleNoMessageHandler::class)
            ->addTag('messenger.message_handler')
        ;

        (new MessengerPass())->process($container);
    }

    public function testRegistersTraceableBusesToCollector()
    {
        $dataCollector = $this->getMockBuilder(MessengerDataCollector::class)->getMock();

        $container = $this->getContainerBuilder($fooBusId = 'messenger.bus.foo');
        $container->register('messenger.data_collector', $dataCollector);
        $container->setParameter('kernel.debug', true);

        (new MessengerPass())->process($container);

        $this->assertTrue($container->hasDefinition($debuggedFooBusId = 'debug.traced.'.$fooBusId));
        $this->assertSame(array($fooBusId, null, 0), $container->getDefinition($debuggedFooBusId)->getDecoratedService());
        $this->assertEquals(array(array('registerBus', array($fooBusId, new Reference($debuggedFooBusId)))), $container->getDefinition('messenger.data_collector')->getMethodCalls());
    }

    public function testRegistersMiddlewareFromServices()
    {
        $container = $this->getContainerBuilder($fooBusId = 'messenger.bus.foo');
        $container->register('messenger.middleware.allow_no_handler', AllowNoHandlerMiddleware::class)->setAbstract(true);
        $container->register('middleware_with_factory', UselessMiddleware::class)->addArgument('some_default')->setAbstract(true);
        $container->register('middleware_with_factory_using_default', UselessMiddleware::class)->addArgument('some_default')->setAbstract(true);
        $container->register(UselessMiddleware::class, UselessMiddleware::class);

        $container->setParameter($middlewareParameter = $fooBusId.'.middleware', array(
            array('id' => UselessMiddleware::class),
            array('id' => 'middleware_with_factory', 'arguments' => array('foo', 'bar')),
            array('id' => 'middleware_with_factory_using_default'),
            array('id' => 'allow_no_handler'),
        ));

        (new MessengerPass())->process($container);
        (new ResolveChildDefinitionsPass())->process($container);

        $this->assertTrue($container->hasDefinition($childMiddlewareId = $fooBusId.'.middleware.allow_no_handler'));

        $this->assertTrue($container->hasDefinition($factoryChildMiddlewareId = $fooBusId.'.middleware.middleware_with_factory'));
        $this->assertEquals(
            array('foo', 'bar'),
            $container->getDefinition($factoryChildMiddlewareId)->getArguments(),
            'parent default argument is overridden, and next ones appended'
        );

        $this->assertTrue($container->hasDefinition($factoryWithDefaultChildMiddlewareId = $fooBusId.'.middleware.middleware_with_factory_using_default'));
        $this->assertEquals(
            array('some_default'),
            $container->getDefinition($factoryWithDefaultChildMiddlewareId)->getArguments(),
            'parent default argument is used'
        );

        $this->assertEquals(array(
            new Reference(UselessMiddleware::class),
            new Reference($factoryChildMiddlewareId),
            new Reference($factoryWithDefaultChildMiddlewareId),
            new Reference($childMiddlewareId),
        ), $container->getDefinition($fooBusId)->getArgument(0));
        $this->assertFalse($container->hasParameter($middlewareParameter));
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Invalid middleware "not_defined_middleware": define such service to be able to use it.
     */
    public function testCannotRegistersAnUndefinedMiddleware()
    {
        $container = $this->getContainerBuilder($fooBusId = 'messenger.bus.foo');
        $container->setParameter($middlewareParameter = $fooBusId.'.middleware', array(
            array('id' => 'not_defined_middleware', 'arguments' => array()),
        ));

        (new MessengerPass())->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Invalid middleware factory "not_an_abstract_definition": a middleware factory must be an abstract definition.
     */
    public function testMiddlewareFactoryDefinitionMustBeAbstract()
    {
        $container = $this->getContainerBuilder($fooBusId = 'messenger.bus.foo');
        $container->register('not_an_abstract_definition', UselessMiddleware::class);
        $container->setParameter($middlewareParameter = $fooBusId.'.middleware', array(
            array('id' => 'not_an_abstract_definition', 'arguments' => array('foo')),
        ));

        (new MessengerPass())->process($container);
    }

    public function testItRegistersTheDebugCommand()
    {
        $container = $this->getContainerBuilder($commandBusId = 'command_bus');
        $container->register($queryBusId = 'query_bus', MessageBusInterface::class)->setArgument(0, array())->addTag('messenger.bus');
        $container->register($emptyBus = 'empty_bus', MessageBusInterface::class)->setArgument(0, array())->addTag('messenger.bus');
        $container->register('messenger.middleware.call_message_handler', HandleMessageMiddleware::class)
            ->addArgument(null)
            ->setAbstract(true)
        ;

        $container->register('console.command.messenger_debug', DebugCommand::class)->addArgument(array());

        $middlewares = array(array('id' => 'call_message_handler'));

        $container->setParameter($commandBusId.'.middleware', $middlewares);
        $container->setParameter($queryBusId.'.middleware', $middlewares);

        $container->register(DummyCommandHandler::class)->addTag('messenger.message_handler', array('bus' => $commandBusId));
        $container->register(DummyQueryHandler::class)->addTag('messenger.message_handler', array('bus' => $queryBusId));
        $container->register(MultipleBusesMessageHandler::class)
            ->addTag('messenger.message_handler', array('bus' => $commandBusId))
            ->addTag('messenger.message_handler', array('bus' => $queryBusId))
        ;

        (new ResolveClassPass())->process($container);
        (new MessengerPass())->process($container);

        $this->assertEquals(array(
            $commandBusId => array(
                DummyCommand::class => array(DummyCommandHandler::class),
                MultipleBusesMessage::class => array(MultipleBusesMessageHandler::class),
            ),
            $queryBusId => array(
                DummyQuery::class => array(DummyQueryHandler::class),
                MultipleBusesMessage::class => array(MultipleBusesMessageHandler::class),
            ),
            $emptyBus => array(),
        ), $container->getDefinition('console.command.messenger_debug')->getArgument(0));
    }

    private function getContainerBuilder(string $busId = 'message_bus'): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', true);

        $container->register($busId, MessageBusInterface::class)->addTag('messenger.bus')->setArgument(0, array());
        if ('message_bus' !== $busId) {
            $container->setAlias('message_bus', $busId);
        }

        $container
            ->register('messenger.sender_locator', ServiceLocator::class)
            ->addArgument(new Reference('service_container'))
        ;

        $container->register('messenger.receiver_locator', ServiceLocator::class)
            ->addArgument(new Reference('service_container'))
        ;

        return $container;
    }
}

class DummyHandler
{
    public function __invoke(DummyMessage $message): void
    {
    }
}

class DummyReceiver implements ReceiverInterface
{
    public function receive(callable $handler): void
    {
        for ($i = 0; $i < 3; ++$i) {
            $handler(Envelope::wrap(new DummyMessage("Dummy $i")));
        }
    }

    public function stop(): void
    {
    }
}

class InvalidReceiver
{
}

class InvalidSender
{
}

class UndefinedMessageHandler
{
    public function __invoke(UndefinedMessage $message)
    {
    }
}

class UndefinedMessageHandlerViaInterface implements MessageSubscriberInterface
{
    public static function getHandledMessages(): iterable
    {
        return array(UndefinedMessage::class);
    }

    public function __invoke()
    {
    }
}

class NotInvokableHandler
{
}

class MissingArgumentHandler
{
    public function __invoke()
    {
    }
}

class MissingArgumentTypeHandler
{
    public function __invoke($message)
    {
    }
}

class BuiltinArgumentTypeHandler
{
    public function __invoke(string $message)
    {
    }
}

class HandlerWithMultipleMessages implements MessageSubscriberInterface
{
    public static function getHandledMessages(): iterable
    {
        return array(
            DummyMessage::class,
            SecondMessage::class,
        );
    }

    public function __invoke()
    {
    }
}

class PrioritizedHandler implements MessageSubscriberInterface
{
    public static function getHandledMessages(): iterable
    {
        return array(
            array(SecondMessage::class, 10),
        );
    }

    public function __invoke()
    {
    }
}

class HandlerMappingMethods implements MessageSubscriberInterface
{
    public static function getHandledMessages(): iterable
    {
        return array(
            DummyMessage::class => 'dummyMethod',
            SecondMessage::class => array('secondMessage', 20),
        );
    }

    public function dummyMethod()
    {
    }

    public function secondMessage()
    {
    }
}

class HandlerMappingWithNonExistentMethod implements MessageSubscriberInterface
{
    public static function getHandledMessages(): iterable
    {
        return array(
            DummyMessage::class => 'dummyMethod',
        );
    }
}

class HandleNoMessageHandler implements MessageSubscriberInterface
{
    public static function getHandledMessages(): iterable
    {
        return array();
    }

    public function __invoke()
    {
    }
}

class UselessMiddleware implements MiddlewareInterface
{
    public function handle($message, callable $next)
    {
        return $next($message);
    }
}
