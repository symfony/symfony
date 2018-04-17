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
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Adapter\AmqpExt\AmqpReceiver;
use Symfony\Component\Messenger\Adapter\AmqpExt\AmqpSender;
use Symfony\Component\Messenger\ContainerHandlerLocator;
use Symfony\Component\Messenger\DependencyInjection\MessengerPass;
use Symfony\Component\Messenger\Handler\ChainHandler;
use Symfony\Component\Messenger\Handler\MessageSubscriberInterface;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\SecondMessage;
use Symfony\Component\Messenger\Transport\ReceiverInterface;

class MessengerPassTest extends TestCase
{
    public function testProcess()
    {
        $container = $this->getContainerBuilder();
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

        $handlerLocatorDefinition = $container->getDefinition($container->getDefinition('messenger.handler_resolver')->getArgument(0));
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

    public function testGetClassesFromTheHandlerSubscriberInterface()
    {
        $container = $this->getContainerBuilder();
        $container
            ->register(HandlerWithMultipleMessages::class, HandlerWithMultipleMessages::class)
            ->addTag('messenger.message_handler')
        ;
        $container
            ->register(PrioritizedHandler::class, PrioritizedHandler::class)
            ->addTag('messenger.message_handler')
        ;

        (new MessengerPass())->process($container);

        $handlerLocatorDefinition = $container->getDefinition($container->getDefinition('messenger.handler_resolver')->getArgument(0));
        $handlerMapping = $handlerLocatorDefinition->getArgument(0);

        $this->assertArrayHasKey('handler.'.DummyMessage::class, $handlerMapping);
        $this->assertEquals(new ServiceClosureArgument(new Reference(HandlerWithMultipleMessages::class)), $handlerMapping['handler.'.DummyMessage::class]);

        $this->assertArrayHasKey('handler.'.SecondMessage::class, $handlerMapping);
        $handlerReference = (string) $handlerMapping['handler.'.SecondMessage::class]->getValues()[0];
        $definition = $container->getDefinition($handlerReference);

        $this->assertSame(ChainHandler::class, $definition->getClass());
        $this->assertEquals(array(new Reference(PrioritizedHandler::class), new Reference(HandlerWithMultipleMessages::class)), $definition->getArgument(0));
    }

    public function testItRegistersReceivers()
    {
        $container = $this->getContainerBuilder();
        $container->register(AmqpReceiver::class, AmqpReceiver::class)->addTag('messenger.receiver', array('name' => 'amqp'));

        (new MessengerPass())->process($container);

        $this->assertEquals(array('amqp' => new Reference(AmqpReceiver::class), AmqpReceiver::class => new Reference(AmqpReceiver::class)), $container->getDefinition('messenger.receiver_locator')->getArgument(0));
    }

    public function testItRegistersReceiversWithoutTagName()
    {
        $container = $this->getContainerBuilder();
        $container->register(AmqpReceiver::class, AmqpReceiver::class)->addTag('messenger.receiver');

        (new MessengerPass())->process($container);

        $this->assertEquals(array(AmqpReceiver::class => new Reference(AmqpReceiver::class)), $container->getDefinition('messenger.receiver_locator')->getArgument(0));
    }

    public function testItRegistersSenders()
    {
        $container = $this->getContainerBuilder();
        $container->register(AmqpSender::class, AmqpSender::class)->addTag('messenger.sender', array('name' => 'amqp'));

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

    private function getContainerBuilder(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', true);
        $container->register('message_bus', ContainerHandlerLocator::class);

        $container
            ->register('messenger.sender_locator', ServiceLocator::class)
            ->addArgument(new Reference('service_container'))
        ;

        $container
            ->register('messenger.handler_resolver', ContainerHandlerLocator::class)
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
            $handler(new DummyMessage("Dummy $i"));
        }
    }

    public function stop(): void
    {
    }
}

class UndefinedMessageHandler
{
    public function __invoke(UndefinedMessage $message)
    {
    }
}

class UndefinedMessageHandlerViaInterface implements MessageSubscriberInterface
{
    public static function getHandledMessages(): array
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
    public static function getHandledMessages(): array
    {
        return array(
            DummyMessage::class,
            SecondMessage::class,
        );
    }
}

class PrioritizedHandler implements MessageSubscriberInterface
{
    public static function getHandledMessages(): array
    {
        return array(
            array(SecondMessage::class, 10),
        );
    }
}

class HandleNoMessageHandler implements MessageSubscriberInterface
{
    public static function getHandledMessages(): array
    {
        return array();
    }
}
