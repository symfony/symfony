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
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\AttributeAutoconfigurationPass;
use Symfony\Component\DependencyInjection\Compiler\ResolveChildDefinitionsPass;
use Symfony\Component\DependencyInjection\Compiler\ResolveClassPass;
use Symfony\Component\DependencyInjection\Compiler\ResolveInstanceofConditionalsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpReceiver;
use Symfony\Component\Messenger\Command\ConsumeMessagesCommand;
use Symfony\Component\Messenger\Command\DebugCommand;
use Symfony\Component\Messenger\Command\FailedMessagesRetryCommand;
use Symfony\Component\Messenger\Command\FailedMessagesShowCommand;
use Symfony\Component\Messenger\Command\SetupTransportsCommand;
use Symfony\Component\Messenger\DataCollector\MessengerDataCollector;
use Symfony\Component\Messenger\DependencyInjection\MessengerPass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\Handler\MessageSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Tests\Fixtures\ChildDummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\DummyCommand;
use Symfony\Component\Messenger\Tests\Fixtures\DummyCommandHandler;
use Symfony\Component\Messenger\Tests\Fixtures\DummyHandlerWithCustomMethods;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\DummyQuery;
use Symfony\Component\Messenger\Tests\Fixtures\DummyQueryHandler;
use Symfony\Component\Messenger\Tests\Fixtures\MultipleBusesMessage;
use Symfony\Component\Messenger\Tests\Fixtures\MultipleBusesMessageHandler;
use Symfony\Component\Messenger\Tests\Fixtures\SecondMessage;
use Symfony\Component\Messenger\Tests\Fixtures\TaggedDummyHandler;
use Symfony\Component\Messenger\Tests\Fixtures\TaggedDummyHandlerWithUnionTypes;
use Symfony\Component\Messenger\Tests\Fixtures\UnionBuiltinTypeArgumentHandler;
use Symfony\Component\Messenger\Tests\Fixtures\UnionTypeArgumentHandler;
use Symfony\Component\Messenger\Tests\Fixtures\UnionTypeOneMessage;
use Symfony\Component\Messenger\Tests\Fixtures\UnionTypeTwoMessage;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

class MessengerPassTest extends TestCase
{
    use ExpectDeprecationTrait;

    public function testProcess()
    {
        $container = $this->getContainerBuilder($busId = 'message_bus');
        $container
            ->register(DummyHandler::class, DummyHandler::class)
            ->addTag('messenger.message_handler')
        ;
        $container
            ->register(MissingArgumentTypeHandler::class, MissingArgumentTypeHandler::class)
            ->addTag('messenger.message_handler', ['handles' => SecondMessage::class])
        ;
        $container
            ->register(DummyReceiver::class, DummyReceiver::class)
            ->addTag('messenger.receiver')
        ;

        (new MessengerPass())->process($container);

        $this->assertFalse($container->hasDefinition('messenger.middleware.debug.logging'));

        $handlersLocatorDefinition = $container->getDefinition($busId.'.messenger.handlers_locator');
        $this->assertSame(HandlersLocator::class, $handlersLocatorDefinition->getClass());

        $handlerDescriptionMapping = $handlersLocatorDefinition->getArgument(0);
        $this->assertCount(2, $handlerDescriptionMapping);

        $this->assertHandlerDescriptor($container, $handlerDescriptionMapping, DummyMessage::class, [DummyHandler::class]);
        $this->assertHandlerDescriptor($container, $handlerDescriptionMapping, SecondMessage::class, [MissingArgumentTypeHandler::class]);

        $this->assertEquals(
            [DummyReceiver::class => new Reference(DummyReceiver::class)],
            $container->getDefinition('messenger.receiver_locator')->getArgument(0)
        );
    }

    public function testFromTransportViaTagAttribute()
    {
        $container = $this->getContainerBuilder($busId = 'message_bus');
        $container
            ->register(DummyHandler::class, DummyHandler::class)
            ->addTag('messenger.message_handler', ['from_transport' => 'async'])
        ;

        (new MessengerPass())->process($container);

        $handlersLocatorDefinition = $container->getDefinition($busId.'.messenger.handlers_locator');
        $this->assertSame(HandlersLocator::class, $handlersLocatorDefinition->getClass());

        $handlerDescriptionMapping = $handlersLocatorDefinition->getArgument(0);
        $this->assertCount(1, $handlerDescriptionMapping);

        $this->assertHandlerDescriptor($container, $handlerDescriptionMapping, DummyMessage::class, [DummyHandler::class], [['from_transport' => 'async']]);
    }

    public function testHandledMessageTypeResolvedWithMethodAndNoHandlesViaTagAttributes()
    {
        $container = $this->getContainerBuilder($busId = 'message_bus');
        $container
            ->register(DummyHandlerWithCustomMethods::class, DummyHandlerWithCustomMethods::class)
            ->addTag('messenger.message_handler', [
                'method' => 'handleDummyMessage',
            ])
            ->addTag('messenger.message_handler', [
                'method' => 'handleSecondMessage',
            ]);

        (new MessengerPass())->process($container);

        $handlersMapping = $container->getDefinition($busId.'.messenger.handlers_locator')->getArgument(0);

        $this->assertArrayHasKey(DummyMessage::class, $handlersMapping);
        $this->assertHandlerDescriptor(
            $container,
            $handlersMapping,
            DummyMessage::class,
            [[DummyHandlerWithCustomMethods::class, 'handleDummyMessage']]
        );

        $this->assertArrayHasKey(SecondMessage::class, $handlersMapping);
        $this->assertHandlerDescriptor(
            $container,
            $handlersMapping,
            SecondMessage::class,
            [[DummyHandlerWithCustomMethods::class, 'handleSecondMessage']]
        );
    }

    public function testTaggedMessageHandler()
    {
        $container = $this->getContainerBuilder($busId = 'message_bus');
        $container->registerAttributeForAutoconfiguration(AsMessageHandler::class, static function (ChildDefinition $definition, AsMessageHandler $attribute, \ReflectionClass|\ReflectionMethod $reflector): void {
            $tagAttributes = get_object_vars($attribute);
            $tagAttributes['from_transport'] = $tagAttributes['fromTransport'];
            unset($tagAttributes['fromTransport']);
            if ($reflector instanceof \ReflectionMethod) {
                if (isset($tagAttributes['method'])) {
                    throw new LogicException(sprintf('AsMessageHandler attribute cannot declare a method on "%s::%s()".', $reflector->class, $reflector->name));
                }
                $tagAttributes['method'] = $reflector->getName();
            }

            $definition->addTag('messenger.message_handler', $tagAttributes);
        });
        $container
            ->register(TaggedDummyHandler::class, TaggedDummyHandler::class)
            ->setAutoconfigured(true)
        ;

        (new AttributeAutoconfigurationPass())->process($container);
        (new ResolveInstanceofConditionalsPass())->process($container);
        (new MessengerPass())->process($container);

        $handlersLocatorDefinition = $container->getDefinition($busId.'.messenger.handlers_locator');
        $this->assertSame(HandlersLocator::class, $handlersLocatorDefinition->getClass());

        $handlerDescriptionMapping = $handlersLocatorDefinition->getArgument(0);
        $this->assertCount(2, $handlerDescriptionMapping);

        $this->assertHandlerDescriptor($container, $handlerDescriptionMapping, DummyMessage::class, [TaggedDummyHandler::class], [[]]);
        $this->assertHandlerDescriptor(
            $container,
            $handlerDescriptionMapping,
            SecondMessage::class,
            [[TaggedDummyHandler::class, 'handleSecondMessage']]
        );
    }

    public function testTaggedMessageHandlerWithUnionTypes()
    {
        $container = $this->getContainerBuilder($busId = 'message_bus');
        $container->registerAttributeForAutoconfiguration(AsMessageHandler::class, static function (ChildDefinition $definition, AsMessageHandler $attribute, \ReflectionClass|\ReflectionMethod $reflector): void {
            $tagAttributes = get_object_vars($attribute);
            $tagAttributes['from_transport'] = $tagAttributes['fromTransport'];
            unset($tagAttributes['fromTransport']);
            if ($reflector instanceof \ReflectionMethod) {
                if (isset($tagAttributes['method'])) {
                    throw new LogicException(sprintf('AsMessageHandler attribute cannot declare a method on "%s::%s()".', $reflector->class, $reflector->name));
                }
                $tagAttributes['method'] = $reflector->getName();
            }

            $definition->addTag('messenger.message_handler', $tagAttributes);
        });
        $container
            ->register(TaggedDummyHandlerWithUnionTypes::class, TaggedDummyHandlerWithUnionTypes::class)
            ->setAutoconfigured(true)
        ;

        (new AttributeAutoconfigurationPass())->process($container);
        (new ResolveInstanceofConditionalsPass())->process($container);
        (new MessengerPass())->process($container);

        $handlersLocatorDefinition = $container->getDefinition($busId.'.messenger.handlers_locator');
        $this->assertSame(HandlersLocator::class, $handlersLocatorDefinition->getClass());

        $handlerDescriptionMapping = $handlersLocatorDefinition->getArgument(0);

        $this->assertCount(4, $handlerDescriptionMapping);

        $this->assertHandlerDescriptor($container, $handlerDescriptionMapping, DummyMessage::class, [TaggedDummyHandlerWithUnionTypes::class], [[]]);
        $this->assertHandlerDescriptor($container, $handlerDescriptionMapping, SecondMessage::class, [TaggedDummyHandlerWithUnionTypes::class], [[]]);
        $this->assertHandlerDescriptor(
            $container,
            $handlerDescriptionMapping,
            UnionTypeOneMessage::class,
            [[TaggedDummyHandlerWithUnionTypes::class, 'handleUnionTypeMessage']]
        );
        $this->assertHandlerDescriptor(
            $container,
            $handlerDescriptionMapping,
            UnionTypeTwoMessage::class,
            [[TaggedDummyHandlerWithUnionTypes::class, 'handleUnionTypeMessage']]
        );
    }

    public function testProcessHandlersByBus()
    {
        $container = $this->getContainerBuilder($commandBusId = 'command_bus');
        $container->register($queryBusId = 'query_bus', MessageBusInterface::class)->setArgument(0, [])->addTag('messenger.bus');
        $container->register('messenger.middleware.handle_message', HandleMessageMiddleware::class)
            ->addArgument(null)
            ->setAbstract(true)
        ;

        $middlewareHandlers = [['id' => 'handle_message']];

        $container->setParameter($commandBusId.'.middleware', $middlewareHandlers);
        $container->setParameter($queryBusId.'.middleware', $middlewareHandlers);

        $container->register(DummyCommandHandler::class)->addTag('messenger.message_handler', ['bus' => $commandBusId]);
        $container->register(DummyQueryHandler::class)->addTag('messenger.message_handler', ['bus' => $queryBusId]);
        $container->register(MultipleBusesMessageHandler::class)
            ->addTag('messenger.message_handler', ['bus' => $commandBusId])
            ->addTag('messenger.message_handler', ['bus' => $queryBusId])
        ;

        (new ResolveClassPass())->process($container);
        (new MessengerPass())->process($container);

        $commandBusHandlersLocatorDefinition = $container->getDefinition($commandBusId.'.messenger.handlers_locator');
        $this->assertSame(HandlersLocator::class, $commandBusHandlersLocatorDefinition->getClass());

        $this->assertHandlerDescriptor(
            $container,
            $commandBusHandlersLocatorDefinition->getArgument(0),
            MultipleBusesMessage::class,
            [MultipleBusesMessageHandler::class]
        );
        $this->assertHandlerDescriptor(
            $container,
            $commandBusHandlersLocatorDefinition->getArgument(0),
            DummyCommand::class,
            [DummyCommandHandler::class]
        );

        $queryBusHandlersLocatorDefinition = $container->getDefinition($queryBusId.'.messenger.handlers_locator');
        $this->assertSame(HandlersLocator::class, $queryBusHandlersLocatorDefinition->getClass());
        $this->assertHandlerDescriptor(
            $container,
            $queryBusHandlersLocatorDefinition->getArgument(0),
            DummyQuery::class,
            [DummyQueryHandler::class]
        );
        $this->assertHandlerDescriptor(
            $container,
            $queryBusHandlersLocatorDefinition->getArgument(0),
            MultipleBusesMessage::class,
            [MultipleBusesMessageHandler::class]
        );
    }

    public function testProcessTagWithUnknownBus()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid handler service "Symfony\Component\Messenger\Tests\Fixtures\DummyCommandHandler": bus "unknown_bus" specified on the tag "messenger.message_handler" does not exist (known ones are: "command_bus").');
        $container = $this->getContainerBuilder($commandBusId = 'command_bus');

        $container->register(DummyCommandHandler::class)->addTag('messenger.message_handler', ['bus' => 'unknown_bus']);

        (new ResolveClassPass())->process($container);
        (new MessengerPass())->process($container);
    }

    /**
     * @group legacy
     */
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

        $this->expectDeprecation('Since symfony/messenger 6.2: Implementing "Symfony\Component\Messenger\Handler\MessageSubscriberInterface" is deprecated, use the "Symfony\Component\Messenger\Attribute\AsMessageHandler" attribute instead.');
        (new MessengerPass())->process($container);

        $handlersMapping = $container->getDefinition($busId.'.messenger.handlers_locator')->getArgument(0);

        $this->assertArrayHasKey(DummyMessage::class, $handlersMapping);
        $this->assertHandlerDescriptor($container, $handlersMapping, DummyMessage::class, [HandlerWithMultipleMessages::class]);

        $this->assertArrayHasKey(SecondMessage::class, $handlersMapping);
        $this->assertHandlerDescriptor($container, $handlersMapping, SecondMessage::class, [PrioritizedHandler::class, HandlerWithMultipleMessages::class], [['priority' => 10]]);
    }

    /**
     * @group legacy
     */
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

        $handlersMapping = $container->getDefinition($busId.'.messenger.handlers_locator')->getArgument(0);

        $this->assertArrayHasKey(DummyMessage::class, $handlersMapping);
        $this->assertArrayHasKey(SecondMessage::class, $handlersMapping);

        $dummyHandlerDescriptorReference = $handlersMapping[DummyMessage::class]->getValues()[0];
        $dummyHandlerDescriptorDefinition = $container->getDefinition($dummyHandlerDescriptorReference);

        $dummyHandlerReference = $dummyHandlerDescriptorDefinition->getArgument(0);
        $dummyHandlerDefinition = $container->getDefinition($dummyHandlerReference);

        $this->assertSame('callable', $dummyHandlerDefinition->getClass());
        $this->assertEquals([new Reference(HandlerMappingMethods::class), 'dummyMethod'], $dummyHandlerDefinition->getArgument(0));
        $this->assertSame(['Closure', 'fromCallable'], $dummyHandlerDefinition->getFactory());

        $secondHandlerDescriptorReference = $handlersMapping[SecondMessage::class]->getValues()[1];
        $secondHandlerDescriptorDefinition = $container->getDefinition($secondHandlerDescriptorReference);

        $secondHandlerReference = $secondHandlerDescriptorDefinition->getArgument(0);
        $secondHandlerDefinition = $container->getDefinition($secondHandlerReference);
        $this->assertSame(PrioritizedHandler::class, $secondHandlerDefinition->getClass());
    }

    public function testRegisterAbstractHandler()
    {
        $container = $this->getContainerBuilder($messageBusId = 'message_bus');
        $container->register($messageBusId, MessageBusInterface::class)->addTag('messenger.bus')->setArgument(0, []);

        $container
            ->register(DummyHandler::class, DummyHandler::class)
            ->setAbstract(true);

        $container
            ->setDefinition($abstractDirectChildId = 'direct_child', new ChildDefinition(DummyHandler::class))
            ->setAbstract(true);

        $container
            ->setDefinition($abstractHandlerId = 'child', new ChildDefinition($abstractDirectChildId))
            ->addTag('messenger.message_handler');

        (new MessengerPass())->process($container);

        $messageHandlerMapping = $container->getDefinition($messageBusId.'.messenger.handlers_locator')->getArgument(0);
        $this->assertHandlerDescriptor(
            $container,
            $messageHandlerMapping,
            DummyMessage::class,
            [$abstractHandlerId]
        );
    }

    public function testThrowsExceptionIfTheHandlerClassDoesNotExist()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid service "NonExistentHandlerClass": class "NonExistentHandlerClass" does not exist.');
        $container = $this->getContainerBuilder();
        $container->register('message_bus', MessageBusInterface::class)->addTag('messenger.bus');
        $container
            ->register('NonExistentHandlerClass', 'NonExistentHandlerClass')
            ->addTag('messenger.message_handler')
        ;

        (new MessengerPass())->process($container);
    }

    /**
     * @group legacy
     */
    public function testThrowsExceptionIfTheHandlerMethodDoesNotExist()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid handler service "Symfony\Component\Messenger\Tests\DependencyInjection\HandlerMappingWithNonExistentMethod": method "Symfony\Component\Messenger\Tests\DependencyInjection\HandlerMappingWithNonExistentMethod::dummyMethod()" does not exist.');
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
        $container->register(AmqpReceiver::class, AmqpReceiver::class)->addTag('messenger.receiver', ['alias' => 'amqp']);

        (new MessengerPass())->process($container);

        $this->assertEquals(['amqp' => new Reference(AmqpReceiver::class), AmqpReceiver::class => new Reference(AmqpReceiver::class)], $container->getDefinition('messenger.receiver_locator')->getArgument(0));
    }

    public function testItRegistersReceiversWithoutTagName()
    {
        $container = $this->getContainerBuilder();
        $container->register('message_bus', MessageBusInterface::class)->addTag('messenger.bus');
        $container->register(AmqpReceiver::class, AmqpReceiver::class)->addTag('messenger.receiver');

        (new MessengerPass())->process($container);

        $this->assertEquals([AmqpReceiver::class => new Reference(AmqpReceiver::class)], $container->getDefinition('messenger.receiver_locator')->getArgument(0));
    }

    public function testItRegistersMultipleReceiversAndSetsTheReceiverNamesOnTheCommand()
    {
        $container = $this->getContainerBuilder();
        $container->register('console.command.messenger_consume_messages', ConsumeMessagesCommand::class)->setArguments([
            null,
            new Reference('messenger.receiver_locator'),
            null,
            null,
            null,
        ]);

        $container->register(AmqpReceiver::class, AmqpReceiver::class)->addTag('messenger.receiver', ['alias' => 'amqp']);
        $container->register(DummyReceiver::class, DummyReceiver::class)->addTag('messenger.receiver', ['alias' => 'dummy']);

        (new MessengerPass())->process($container);

        $this->assertSame(['amqp', 'dummy'], $container->getDefinition('console.command.messenger_consume_messages')->getArgument(4));
    }

    public function testItSetsTheReceiverNamesOnTheSetupTransportsCommand()
    {
        $container = $this->getContainerBuilder();
        $container->register('console.command.messenger_setup_transports', SetupTransportsCommand::class)->setArguments([
            new Reference('messenger.receiver_locator'),
            null,
        ]);

        $container->register(AmqpReceiver::class, AmqpReceiver::class)->addTag('messenger.receiver', ['alias' => 'amqp']);
        $container->register(DummyReceiver::class, DummyReceiver::class)->addTag('messenger.receiver', ['alias' => 'dummy']);

        (new MessengerPass())->process($container);

        $this->assertSame(['amqp', 'dummy'], $container->getDefinition('console.command.messenger_setup_transports')->getArgument(1));
    }

    /**
     * @group legacy
     */
    public function testItShouldNotThrowIfGeneratorIsReturnedInsteadOfArray()
    {
        $container = $this->getContainerBuilder($busId = 'message_bus');
        $container
            ->register(HandlerWithGenerators::class, HandlerWithGenerators::class)
            ->addTag('messenger.message_handler')
        ;

        (new MessengerPass())->process($container);

        $handlersMapping = $container->getDefinition($busId.'.messenger.handlers_locator')->getArgument(0);

        $this->assertHandlerDescriptor(
            $container,
            $handlersMapping,
            DummyMessage::class,
            [[HandlerWithGenerators::class, 'dummyMethod']]
        );

        $this->assertHandlerDescriptor(
            $container,
            $handlersMapping,
            SecondMessage::class,
            [[HandlerWithGenerators::class, 'secondMessage']]
        );
    }

    /**
     * @group legacy
     */
    public function testItRegistersHandlersOnDifferentBuses()
    {
        $container = $this->getContainerBuilder($eventsBusId = 'event_bus');
        $container->register($commandsBusId = 'command_bus', MessageBusInterface::class)->addTag('messenger.bus')->setArgument(0, []);

        $container
            ->register(HandlerOnSpecificBuses::class, HandlerOnSpecificBuses::class)
            ->addTag('messenger.message_handler');

        (new MessengerPass())->process($container);

        $eventsHandlerMapping = $container->getDefinition($eventsBusId.'.messenger.handlers_locator')->getArgument(0);

        $this->assertHandlerDescriptor(
            $container,
            $eventsHandlerMapping,
            DummyMessage::class,
            [[HandlerOnSpecificBuses::class, 'dummyMethodForEvents']],
            [['bus' => 'event_bus']]
        );

        $commandsHandlerMapping = $container->getDefinition($commandsBusId.'.messenger.handlers_locator')->getArgument(0);

        $this->assertHandlerDescriptor(
            $container,
            $commandsHandlerMapping,
            DummyMessage::class,
            [[HandlerOnSpecificBuses::class, 'dummyMethodForCommands']],
            [['bus' => 'command_bus']]
        );
    }

    /**
     * @group legacy
     */
    public function testItThrowsAnExceptionOnUnknownBus()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid configuration "returned by method "Symfony\Component\Messenger\Tests\DependencyInjection\HandlerOnUndefinedBus::getHandledMessages()"" for message "Symfony\Component\Messenger\Tests\Fixtures\DummyMessage": bus "some_undefined_bus" does not exist.');
        $container = $this->getContainerBuilder();
        $container
            ->register(HandlerOnUndefinedBus::class, HandlerOnUndefinedBus::class)
            ->addTag('messenger.message_handler')
        ;

        (new MessengerPass())->process($container);
    }

    public function testUndefinedMessageClassForHandler()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid handler service "Symfony\Component\Messenger\Tests\DependencyInjection\UndefinedMessageHandler": class or interface "Symfony\Component\Messenger\Tests\DependencyInjection\UndefinedMessage" "used as argument type in method "Symfony\Component\Messenger\Tests\DependencyInjection\UndefinedMessageHandler::__invoke()"" not found.');
        $container = $this->getContainerBuilder();
        $container
            ->register(UndefinedMessageHandler::class, UndefinedMessageHandler::class)
            ->addTag('messenger.message_handler')
        ;

        (new MessengerPass())->process($container);
    }

    /**
     * @group legacy
     */
    public function testUndefinedMessageClassForHandlerImplementingMessageHandlerInterface()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid handler service "Symfony\Component\Messenger\Tests\DependencyInjection\UndefinedMessageHandlerViaHandlerInterface": class or interface "Symfony\Component\Messenger\Tests\DependencyInjection\UndefinedMessage" "used as argument type in method "Symfony\Component\Messenger\Tests\DependencyInjection\UndefinedMessageHandlerViaHandlerInterface::__invoke()"" not found.');
        $container = $this->getContainerBuilder();
        $container
            ->register(UndefinedMessageHandlerViaHandlerInterface::class, UndefinedMessageHandlerViaHandlerInterface::class)
            ->addTag('messenger.message_handler')
        ;

        (new MessengerPass())->process($container);
    }

    /**
     * @group legacy
     */
    public function testUndefinedMessageClassForHandlerImplementingMessageSubscriberInterface()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid handler service "Symfony\Component\Messenger\Tests\DependencyInjection\UndefinedMessageHandlerViaSubscriberInterface": class or interface "Symfony\Component\Messenger\Tests\DependencyInjection\UndefinedMessage" "returned by method "Symfony\Component\Messenger\Tests\DependencyInjection\UndefinedMessageHandlerViaSubscriberInterface::getHandledMessages()"" not found.');
        $container = $this->getContainerBuilder();
        $container
            ->register(UndefinedMessageHandlerViaSubscriberInterface::class, UndefinedMessageHandlerViaSubscriberInterface::class)
            ->addTag('messenger.message_handler')
        ;

        (new MessengerPass())->process($container);
    }

    public function testNotInvokableHandler()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid handler service "Symfony\Component\Messenger\Tests\DependencyInjection\NotInvokableHandler": class "Symfony\Component\Messenger\Tests\DependencyInjection\NotInvokableHandler" must have an "__invoke()" method.');
        $container = $this->getContainerBuilder();
        $container
            ->register(NotInvokableHandler::class, NotInvokableHandler::class)
            ->addTag('messenger.message_handler')
        ;

        (new MessengerPass())->process($container);
    }

    public function testMissingArgumentHandler()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid handler service "Symfony\Component\Messenger\Tests\DependencyInjection\MissingArgumentHandler": method "Symfony\Component\Messenger\Tests\DependencyInjection\MissingArgumentHandler::__invoke()" requires at least one argument, first one being the message it handles.');
        $container = $this->getContainerBuilder();
        $container
            ->register(MissingArgumentHandler::class, MissingArgumentHandler::class)
            ->addTag('messenger.message_handler')
        ;

        (new MessengerPass())->process($container);
    }

    public function testMissingArgumentTypeHandler()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid handler service "Symfony\Component\Messenger\Tests\DependencyInjection\MissingArgumentTypeHandler": argument "$message" of method "Symfony\Component\Messenger\Tests\DependencyInjection\MissingArgumentTypeHandler::__invoke()" must have a type-hint corresponding to the message class it handles.');
        $container = $this->getContainerBuilder();
        $container
            ->register(MissingArgumentTypeHandler::class, MissingArgumentTypeHandler::class)
            ->addTag('messenger.message_handler')
        ;

        (new MessengerPass())->process($container);
    }

    public function testBuiltinArgumentTypeHandler()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid handler service "Symfony\Component\Messenger\Tests\DependencyInjection\BuiltinArgumentTypeHandler": type-hint of argument "$message" in method "Symfony\Component\Messenger\Tests\DependencyInjection\BuiltinArgumentTypeHandler::__invoke()" must be a class , "string" given.');
        $container = $this->getContainerBuilder();
        $container
            ->register(BuiltinArgumentTypeHandler::class, BuiltinArgumentTypeHandler::class)
            ->addTag('messenger.message_handler')
        ;

        (new MessengerPass())->process($container);
    }

    public function testUnionTypeArgumentsTypeHandler()
    {
        $container = $this->getContainerBuilder($busId = 'message_bus');
        $container
            ->register(UnionTypeArgumentHandler::class, UnionTypeArgumentHandler::class)
            ->addTag('messenger.message_handler')
        ;

        (new MessengerPass())->process($container);

        $handlersMapping = $container->getDefinition($busId.'.messenger.handlers_locator')->getArgument(0);

        $this->assertArrayHasKey(ChildDummyMessage::class, $handlersMapping);
        $this->assertArrayHasKey(DummyMessage::class, $handlersMapping);
        $this->assertHandlerDescriptor($container, $handlersMapping, ChildDummyMessage::class, [UnionTypeArgumentHandler::class]);
        $this->assertHandlerDescriptor($container, $handlersMapping, DummyMessage::class, [UnionTypeArgumentHandler::class]);
    }

    public function testUnionBuiltinArgumentTypeHandler()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(sprintf('Invalid handler service "%s": type-hint of argument "$message" in method "%s::__invoke()" must be a class , "string|int" given.', UnionBuiltinTypeArgumentHandler::class, UnionBuiltinTypeArgumentHandler::class));
        $container = $this->getContainerBuilder();
        $container
            ->register(UnionBuiltinTypeArgumentHandler::class, UnionBuiltinTypeArgumentHandler::class)
            ->addTag('messenger.message_handler')
        ;

        (new MessengerPass())->process($container);
    }

    /**
     * @group legacy
     */
    public function testNeedsToHandleAtLeastOneMessage()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid handler service "Symfony\Component\Messenger\Tests\DependencyInjection\HandleNoMessageHandler": method "Symfony\Component\Messenger\Tests\DependencyInjection\HandleNoMessageHandler::getHandledMessages()" must return one or more messages.');
        $container = $this->getContainerBuilder();
        $container
            ->register(HandleNoMessageHandler::class, HandleNoMessageHandler::class)
            ->addTag('messenger.message_handler')
        ;

        (new MessengerPass())->process($container);
    }

    public function testRegistersTraceableBusesToCollector()
    {
        $container = $this->getContainerBuilder($fooBusId = 'messenger.bus.foo');
        $container->register('data_collector.messenger', MessengerDataCollector::class);
        $container->setParameter('kernel.debug', true);

        (new MessengerPass())->process($container);

        $this->assertTrue($container->hasDefinition($debuggedFooBusId = 'debug.traced.'.$fooBusId));
        $this->assertSame([$fooBusId, null, 0], $container->getDefinition($debuggedFooBusId)->getDecoratedService());
        $this->assertEquals([['registerBus', [$fooBusId, new Reference($debuggedFooBusId)]]], $container->getDefinition('data_collector.messenger')->getMethodCalls());
    }

    public function testRegistersMiddlewareFromServices()
    {
        $container = $this->getContainerBuilder($fooBusId = 'messenger.bus.foo');
        $container->register('middleware_with_factory', UselessMiddleware::class)->addArgument('some_default')->setAbstract(true);
        $container->register('middleware_with_factory_using_default', UselessMiddleware::class)->addArgument('some_default')->setAbstract(true);
        $container->register(UselessMiddleware::class, UselessMiddleware::class);

        $container->setParameter($middlewareParameter = $fooBusId.'.middleware', [
            ['id' => UselessMiddleware::class],
            ['id' => 'middleware_with_factory', 'arguments' => $factoryChildMiddlewareArgs1 = ['index_0' => 'foo', 'bar']],
            ['id' => 'middleware_with_factory', 'arguments' => $factoryChildMiddlewareArgs2 = ['index_0' => 'baz']],
            ['id' => 'middleware_with_factory_using_default'],
        ]);

        (new MessengerPass())->process($container);
        (new ResolveChildDefinitionsPass())->process($container);

        $this->assertTrue($container->hasDefinition(
            $factoryChildMiddlewareArgs1Id = $fooBusId.'.middleware.middleware_with_factory'
        ));
        $this->assertEquals(
            ['foo', 'bar'],
            $container->getDefinition($factoryChildMiddlewareArgs1Id)->getArguments(),
            'parent default argument is overridden, and next ones appended'
        );

        $this->assertTrue($container->hasDefinition(
            $factoryChildMiddlewareArgs2Id = $fooBusId.'.middleware.middleware_with_factory.'.ContainerBuilder::hash($factoryChildMiddlewareArgs2)
        ));
        $this->assertEquals(
            ['baz'],
            $container->getDefinition($factoryChildMiddlewareArgs2Id)->getArguments(),
            'parent default argument is overridden, and next ones appended'
        );

        $this->assertTrue($container->hasDefinition(
            $factoryWithDefaultChildMiddlewareId = $fooBusId.'.middleware.middleware_with_factory_using_default'
        ));
        $this->assertEquals(
            ['some_default'],
            $container->getDefinition($factoryWithDefaultChildMiddlewareId)->getArguments(),
            'parent default argument is used'
        );

        $this->assertEquals([
            new Reference(UselessMiddleware::class),
            new Reference($factoryChildMiddlewareArgs1Id),
            new Reference($factoryChildMiddlewareArgs2Id),
            new Reference($factoryWithDefaultChildMiddlewareId),
        ], $container->getDefinition($fooBusId)->getArgument(0)->getValues());
        $this->assertFalse($container->hasParameter($middlewareParameter));
    }

    public function testCannotRegistersAnUndefinedMiddleware()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid middleware: service "not_defined_middleware" not found.');
        $container = $this->getContainerBuilder($fooBusId = 'messenger.bus.foo');
        $container->setParameter($middlewareParameter = $fooBusId.'.middleware', [
            ['id' => 'not_defined_middleware', 'arguments' => []],
        ]);

        (new MessengerPass())->process($container);
    }

    public function testMiddlewareFactoryDefinitionMustBeAbstract()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid middleware factory "not_an_abstract_definition": a middleware factory must be an abstract definition.');
        $container = $this->getContainerBuilder($fooBusId = 'messenger.bus.foo');
        $container->register('not_an_abstract_definition', UselessMiddleware::class);
        $container->setParameter($middlewareParameter = $fooBusId.'.middleware', [
            ['id' => 'not_an_abstract_definition', 'arguments' => ['foo']],
        ]);

        (new MessengerPass())->process($container);
    }

    public function testItRegistersTheDebugCommand()
    {
        $container = $this->getContainerBuilder($commandBusId = 'command_bus');
        $container->register($queryBusId = 'query_bus', MessageBusInterface::class)->setArgument(0, [])->addTag('messenger.bus');
        $container->register($emptyBus = 'empty_bus', MessageBusInterface::class)->setArgument(0, [])->addTag('messenger.bus');
        $container->register('messenger.middleware.handle_message', HandleMessageMiddleware::class)
            ->addArgument(null)
            ->setAbstract(true)
        ;

        $container->register('console.command.messenger_debug', DebugCommand::class)->addArgument([]);

        $middlewareHandlers = [['id' => 'handle_message']];

        $container->setParameter($commandBusId.'.middleware', $middlewareHandlers);
        $container->setParameter($queryBusId.'.middleware', $middlewareHandlers);

        $container->register(DummyCommandHandler::class)->addTag('messenger.message_handler', ['bus' => $commandBusId]);
        $container->register(DummyQueryHandler::class)->addTag('messenger.message_handler', ['bus' => $queryBusId]);
        $container->register(MultipleBusesMessageHandler::class)
            ->addTag('messenger.message_handler', ['bus' => $commandBusId])
            ->addTag('messenger.message_handler', ['bus' => $queryBusId])
        ;

        (new ResolveClassPass())->process($container);
        (new MessengerPass())->process($container);

        $this->assertEquals([
            $commandBusId => [
                DummyCommand::class => [[DummyCommandHandler::class, []]],
                MultipleBusesMessage::class => [[MultipleBusesMessageHandler::class, []]],
            ],
            $queryBusId => [
                DummyQuery::class => [[DummyQueryHandler::class, []]],
                MultipleBusesMessage::class => [[MultipleBusesMessageHandler::class, []]],
            ],
            $emptyBus => [],
        ], $container->getDefinition('console.command.messenger_debug')->getArgument(0));
    }

    private function getContainerBuilder(string $busId = 'message_bus'): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', true);

        $container->register($busId, MessageBusInterface::class)->addTag('messenger.bus')->setArgument(0, []);
        if ('message_bus' !== $busId) {
            $container->setAlias('message_bus', $busId);
        }

        $container->register('messenger.receiver_locator', ServiceLocator::class)
            ->addArgument(new Reference('service_container'))
        ;

        return $container;
    }

    private function assertHandlerDescriptor(ContainerBuilder $container, array $mapping, string $message, array $handlerClasses, array $options = [])
    {
        $this->assertArrayHasKey($message, $mapping);
        $this->assertCount(\count($handlerClasses), $mapping[$message]->getValues());

        foreach ($handlerClasses as $index => $class) {
            $handlerReference = $mapping[$message]->getValues()[$index];

            if (\is_array($class)) {
                $reference = [new Reference($class[0]), $class[1]];
                $options[$index] = array_merge(['method' => $class[1]], $options[$index] ?? []);
            } else {
                $reference = new Reference($class);
            }

            $definitionArguments = $container->getDefinition($handlerReference)->getArguments();

            if (\is_array($class)) {
                $methodDefinition = $container->getDefinition($definitionArguments[0]);

                $this->assertEquals(['Closure', 'fromCallable'], $methodDefinition->getFactory());
                $this->assertEquals([$reference], $methodDefinition->getArguments());
            } else {
                $this->assertEquals($reference, $definitionArguments[0]);
            }

            $this->assertEquals($options[$index] ?? [], $definitionArguments[1]);
        }
    }

    public function testFailedCommandsRegisteredWithServiceLocatorArgumentReplaced()
    {
        $globalReceiverName = 'global_failure_transport';
        $container = $this->getContainerBuilder($messageBusId = 'message_bus');

        $container->register('console.command.messenger_failed_messages_retry', FailedMessagesRetryCommand::class)
            ->setArgument(0, $globalReceiverName)
            ->setArgument(1, null)
            ->setArgument(2, new Reference($messageBusId));
        $container->register('console.command.messenger_failed_messages_show', FailedMessagesShowCommand::class)
            ->setArgument(0, $globalReceiverName)
            ->setArgument(1, null);
        $container->register('console.command.messenger_failed_messages_remove', FailedMessagesRetryCommand::class)
            ->setArgument(0, $globalReceiverName)
            ->setArgument(1, null);

        (new MessengerPass())->process($container);

        $retryDefinition = $container->getDefinition('console.command.messenger_failed_messages_retry');
        $this->assertNotNull($retryDefinition->getArgument(1));

        $showDefinition = $container->getDefinition('console.command.messenger_failed_messages_show');
        $this->assertNotNull($showDefinition->getArgument(1));

        $removeDefinition = $container->getDefinition('console.command.messenger_failed_messages_remove');
        $this->assertNotNull($removeDefinition->getArgument(1));
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
    public function get(): iterable
    {
        yield new Envelope(new DummyMessage('Dummy'));
    }

    public function stop(): void
    {
    }

    public function ack(Envelope $envelope): void
    {
    }

    public function reject(Envelope $envelope): void
    {
    }
}

class UndefinedMessageHandler
{
    public function __invoke(UndefinedMessage $message)
    {
    }
}

class UndefinedMessageHandlerViaHandlerInterface implements MessageHandlerInterface
{
    public function __invoke(UndefinedMessage $message)
    {
    }
}

class UndefinedMessageHandlerViaSubscriberInterface implements MessageSubscriberInterface
{
    public static function getHandledMessages(): iterable
    {
        return [UndefinedMessage::class];
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
        return [
            DummyMessage::class,
            SecondMessage::class,
        ];
    }

    public function __invoke()
    {
    }
}

class PrioritizedHandler implements MessageSubscriberInterface
{
    public static function getHandledMessages(): iterable
    {
        yield SecondMessage::class => ['priority' => 10];
    }

    public function __invoke()
    {
    }
}

class HandlerMappingMethods implements MessageSubscriberInterface
{
    public static function getHandledMessages(): iterable
    {
        yield DummyMessage::class => 'dummyMethod';
        yield SecondMessage::class => ['method' => 'secondMessage', 'priority' => 20];
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
        return [
            DummyMessage::class => 'dummyMethod',
        ];
    }
}

class HandleNoMessageHandler implements MessageSubscriberInterface
{
    public static function getHandledMessages(): iterable
    {
        return [];
    }

    public function __invoke()
    {
    }
}

class HandlerWithGenerators implements MessageSubscriberInterface
{
    public static function getHandledMessages(): iterable
    {
        yield DummyMessage::class => 'dummyMethod';
        yield SecondMessage::class => 'secondMessage';
    }

    public function dummyMethod()
    {
    }

    public function secondMessage()
    {
    }
}

class HandlerOnSpecificBuses implements MessageSubscriberInterface
{
    public static function getHandledMessages(): iterable
    {
        yield DummyMessage::class => ['method' => 'dummyMethodForEvents', 'bus' => 'event_bus'];
        yield DummyMessage::class => ['method' => 'dummyMethodForCommands', 'bus' => 'command_bus'];
    }

    public function dummyMethodForEvents()
    {
    }

    public function dummyMethodForCommands()
    {
    }
}

class HandlerOnUndefinedBus implements MessageSubscriberInterface
{
    public static function getHandledMessages(): iterable
    {
        yield DummyMessage::class => ['method' => 'dummyMethodForSomeBus', 'bus' => 'some_undefined_bus'];
    }

    public function dummyMethodForSomeBus()
    {
    }
}

class UselessMiddleware implements MiddlewareInterface
{
    public function handle(Envelope $message, StackInterface $stack): Envelope
    {
        return $stack->next()->handle($message, $stack);
    }
}
