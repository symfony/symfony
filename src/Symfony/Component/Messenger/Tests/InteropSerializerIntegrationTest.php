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
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\EventListener\SendFailedMessageForRetryListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnIdleListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\AddBusNameStampMiddleware;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;
use Symfony\Component\Messenger\RoutableMessageBus;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Sender\OutboxSender;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Transport\Serialization\InteropSerializer;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Messenger\Worker;

class InteropSerializerIntegrationTest extends TestCase
{
    private array $attempts = [];

    public function testAppConsumesAMessageSentByAnotherAppThroughABusItDoesNotHave()
    {
        $transport = new InMemoryTransport(new InteropSerializer(Serializer::create()));

        $this->sendFromAppA($transport, $message = new DummyMessage('Hello'));
        $this->consumeInAppB($transport);

        $this->assertEquals([$message], $this->attempts);
        $this->assertCount(1, $transport->getAcknowledged());
    }

    public function testWithoutInteropSerializerTheBusOfTheSenderBreaksTheConsumer()
    {
        $transport = new InMemoryTransport(Serializer::create());

        $this->sendFromAppA($transport, new DummyMessage('Hello'));
        $this->consumeInAppB($transport);

        $this->assertSame([], $this->attempts);
        $this->assertSame([], $transport->getAcknowledged());
    }

    public function testConsumerRetriesAreCountedAndStop()
    {
        $transport = new InMemoryTransport(new InteropSerializer(Serializer::create()));

        $this->sendFromAppA($transport, new DummyMessage('Hello'));
        $this->consumeInAppB($transport, failing: true);

        $this->assertCount(3, $this->attempts, 'The message is handled once, then retried twice.');
        $this->assertCount(3, $transport->getSent(), 'The message is sent by app A, then twice by app B for retries.');
    }

    public function testAppConsumesAMessageRelayedByTheOutboxOfAnotherApp()
    {
        $transport = new InMemoryTransport(new InteropSerializer(Serializer::create()));
        $outbox = new InMemoryTransport();
        $ordersSender = new OutboxSender($transport, $outbox, 'orders');

        $this->sendFromAppA($ordersSender, $message = new DummyMessage('Hello'));

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnIdleListener());
        (new Worker(['outbox' => $outbox], $this->createBusOfAppA($ordersSender), $dispatcher))->run();

        $this->consumeInAppB($transport);

        $this->assertEquals([$message], $this->attempts);
        $this->assertCount(1, $transport->getAcknowledged());
    }

    private function sendFromAppA(SenderInterface $sender, object $message): void
    {
        $this->createBusOfAppA($sender)->dispatch($message);
    }

    private function createBusOfAppA(SenderInterface $sender): MessageBus
    {
        return new MessageBus([
            new AddBusNameStampMiddleware('app_a.bus'),
            new SendMessageMiddleware(new SendersLocator([DummyMessage::class => ['orders']], new ServiceLocator(['orders' => static fn () => $sender]))),
        ]);
    }

    private function consumeInAppB(InMemoryTransport $transport, bool $failing = false): void
    {
        $bus = new MessageBus([
            new HandleMessageMiddleware(new HandlersLocator([DummyMessage::class => [function (DummyMessage $message) use ($failing) {
                $this->attempts[] = $message;

                if ($failing) {
                    throw new \RuntimeException('Handling failed.');
                }
            }]])),
        ]);

        $dispatcher = new EventDispatcher();
        // stops the worker if retries are never counted
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(10));
        $dispatcher->addSubscriber(new StopWorkerOnIdleListener());
        $dispatcher->addSubscriber(new SendFailedMessageForRetryListener(
            new ServiceLocator(['orders' => static fn () => $transport]),
            new ServiceLocator(['orders' => static fn () => new MultiplierRetryStrategy(2, 0, 1, 0, 0)]),
        ));

        (new Worker(['orders' => $transport], new RoutableMessageBus(new ServiceLocator(['app_b.bus' => static fn () => $bus]), $bus), $dispatcher))->run();
    }
}
