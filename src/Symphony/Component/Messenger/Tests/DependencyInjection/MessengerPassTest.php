<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Messenger\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symphony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Reference;
use Symphony\Component\DependencyInjection\ServiceLocator;
use Symphony\Component\Messenger\ContainerHandlerLocator;
use Symphony\Component\Messenger\DependencyInjection\MessengerPass;
use Symphony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symphony\Component\Messenger\Transport\ReceiverInterface;

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
            ->register(DummyReceiver::class, DummyReceiver::class)
            ->addTag('messenger.receiver')
        ;

        (new MessengerPass())->process($container);

        $handlerLocatorDefinition = $container->getDefinition($container->getDefinition('messenger.handler_resolver')->getArgument(0));
        $this->assertSame(ServiceLocator::class, $handlerLocatorDefinition->getClass());
        $this->assertEquals(
            array('handler.'.DummyMessage::class => new ServiceClosureArgument(new Reference(DummyHandler::class))),
            $handlerLocatorDefinition->getArgument(0)
        );

        $this->assertEquals(
            array(DummyReceiver::class => new Reference(DummyReceiver::class)),
            $container->getDefinition('messenger.receiver_locator')->getArgument(0)
        );
    }

    /**
     * @expectedException \Symphony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Invalid handler service "Symphony\Component\Messenger\Tests\DependencyInjection\UndefinedMessageHandler": message class "Symphony\Component\Messenger\Tests\DependencyInjection\UndefinedMessage" used as argument type in method "Symphony\Component\Messenger\Tests\DependencyInjection\UndefinedMessageHandler::__invoke()" does not exist.
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
     * @expectedException \Symphony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Invalid handler service "Symphony\Component\Messenger\Tests\DependencyInjection\NotInvokableHandler": class "Symphony\Component\Messenger\Tests\DependencyInjection\NotInvokableHandler" must have an "__invoke()" method.
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
     * @expectedException \Symphony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Invalid handler service "Symphony\Component\Messenger\Tests\DependencyInjection\MissingArgumentHandler": method "Symphony\Component\Messenger\Tests\DependencyInjection\MissingArgumentHandler::__invoke()" must have exactly one argument corresponding to the message it handles.
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
     * @expectedException \Symphony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Invalid handler service "Symphony\Component\Messenger\Tests\DependencyInjection\MissingArgumentTypeHandler": argument "$message" of method "Symphony\Component\Messenger\Tests\DependencyInjection\MissingArgumentTypeHandler::__invoke()" must have a type-hint corresponding to the message class it handles.
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
     * @expectedException \Symphony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Invalid handler service "Symphony\Component\Messenger\Tests\DependencyInjection\BuiltinArgumentTypeHandler": type-hint of argument "$message" in method "Symphony\Component\Messenger\Tests\DependencyInjection\BuiltinArgumentTypeHandler::__invoke()" must be a class , "string" given.
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

    private function getContainerBuilder(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', true);
        $container->register('message_bus', ContainerHandlerLocator::class);

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
    public function receive(): iterable
    {
        for ($i = 0; $i < 3; ++$i) {
            yield new DummyMessage("Dummy $i");
        }
    }
}

class UndefinedMessageHandler
{
    public function __invoke(UndefinedMessage $message)
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
