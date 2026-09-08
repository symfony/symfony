<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\DependencyInjection\MessengerPass;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Messenger\Worker;

class HandlerTransportIntegrationTest extends TestCase
{
    public function testHandlersReceiveMessagesFromTheirDeclaredTransportOnly()
    {
        $container = new ContainerBuilder();
        $container->register('message_bus', MessageBus::class)->setPublic(true)->addTag('messenger.bus')->setArgument(0, []);
        $container->setParameter('message_bus.middleware', [['id' => 'send_message'], ['id' => 'handle_message']]);
        $container->register('messenger.middleware.send_message', SendMessageMiddleware::class)->setAbstract(true)->setArgument(0, new Reference('messenger.senders_locator'));
        $container->register('messenger.middleware.handle_message', HandleMessageMiddleware::class)->setAbstract(true)->setArgument(0, null);
        $container->register('messenger.transport.a', InMemoryTransport::class)->setPublic(true)->addTag('messenger.receiver', ['alias' => 'a']);
        $container->register('messenger.transport.b', InMemoryTransport::class)->setPublic(true)->addTag('messenger.receiver', ['alias' => 'b']);
        $container->register('messenger.receiver_locator', ServiceLocator::class)->setArgument(0, [])->addTag('container.service_locator');
        $container->register('messenger.senders_locator', SendersLocator::class)->setArguments([[], ServiceLocatorTagPass::register($container, ['a' => new Reference('messenger.transport.a'), 'b' => new Reference('messenger.transport.b')])]);
        $container->registerAttributeForAutoconfiguration(AsMessageHandler::class, static function (ChildDefinition $definition, AsMessageHandler $attribute, \ReflectionClass|\ReflectionMethod $reflector): void {
            $tagAttributes = get_object_vars($attribute);
            $tagAttributes['from_transport'] = $tagAttributes['fromTransport'];
            unset($tagAttributes['fromTransport']);
            if ($reflector instanceof \ReflectionMethod) {
                if (isset($tagAttributes['method'])) {
                    throw new LogicException(\sprintf('AsMessageHandler attribute cannot declare a method on "%s::%s()".', $reflector->class, $reflector->name));
                }
                $tagAttributes['method'] = $reflector->getName();
            }
            $definition->addTag('messenger.message_handler', $tagAttributes);
        });
        foreach ([HandlerBoundToTransportA::class, HandlerBoundToTransportB::class, UnboundHandler::class] as $handlerClass) {
            $container->register($handlerClass, $handlerClass)->setAutoconfigured(true);
        }
        $container->addCompilerPass(new MessengerPass());
        $container->compile();

        RecordingHandler::$calls = [];
        $bus = $container->get('message_bus');
        $transportA = $container->get('messenger.transport.a');
        $transportB = $container->get('messenger.transport.b');

        $bus->dispatch(new DummyMessage('Hello'));

        $this->assertCount(1, $transportA->getSent());
        $this->assertCount(1, $transportB->getSent());
        $this->assertSame([], RecordingHandler::$calls);

        $this->consume('a', $transportA, $bus);

        $this->assertSame([HandlerBoundToTransportA::class, UnboundHandler::class], RecordingHandler::$calls);

        $this->consume('b', $transportB, $bus);

        $this->assertSame([HandlerBoundToTransportA::class, UnboundHandler::class, HandlerBoundToTransportB::class, UnboundHandler::class], RecordingHandler::$calls);
    }

    private function consume(string $transportName, TransportInterface $transport, MessageBusInterface $bus): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));

        (new Worker([$transportName => $transport], $bus, $dispatcher))->run();
    }
}

abstract class RecordingHandler
{
    public static array $calls = [];

    public function __invoke(DummyMessage $message): void
    {
        self::$calls[] = static::class;
    }
}

#[AsMessageHandler(transport: 'a')]
class HandlerBoundToTransportA extends RecordingHandler
{
}

#[AsMessageHandler(transport: 'b')]
class HandlerBoundToTransportB extends RecordingHandler
{
}

#[AsMessageHandler]
class UnboundHandler extends RecordingHandler
{
}
