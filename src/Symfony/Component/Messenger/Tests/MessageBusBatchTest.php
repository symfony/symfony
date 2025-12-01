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
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\BatchSentToTransportsEvent;
use Symfony\Component\Messenger\Event\MessageSentToTransportsEvent;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Stamp\BatchStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\Sender\BatchSenderInterface;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;

class MessageBusBatchTest extends TestCase
{
    public function testDispatchBatchWithEmptyArray()
    {
        $bus = new MessageBus();
        $result = $bus->dispatchBatch([]);

        $this->assertSame([], $result);
    }

    public function testDispatchBatchAddsBatchStampToEachMessage()
    {
        $message1 = new DummyMessage('Hello');
        $message2 = new DummyMessage('World');

        $bus = new MessageBus();
        $envelopes = $bus->dispatchBatch([$message1, $message2]);

        $this->assertCount(2, $envelopes);

        // Check first envelope
        $batchStamp1 = $envelopes[0]->last(BatchStamp::class);
        $this->assertNotNull($batchStamp1);
        $this->assertSame(0, $batchStamp1->getBatchIndex());
        $this->assertSame(2, $batchStamp1->getBatchSize());

        // Check second envelope
        $batchStamp2 = $envelopes[1]->last(BatchStamp::class);
        $this->assertNotNull($batchStamp2);
        $this->assertSame(1, $batchStamp2->getBatchIndex());
        $this->assertSame(2, $batchStamp2->getBatchSize());

        // Both should have the same batch ID
        $this->assertSame($batchStamp1->getBatchId(), $batchStamp2->getBatchId());
    }

    public function testDispatchBatchSendsToTransportViaBatchSender()
    {
        $message1 = new DummyMessage('Hello');
        $message2 = new DummyMessage('World');

        $sender = $this->createMock(BatchSenderInterface::class);
        $sender->expects($this->once())
            ->method('sendBatch')
            ->with($this->callback(function (array $envelopes) {
                return 2 === \count($envelopes)
                    && $envelopes[0]->getMessage() instanceof DummyMessage
                    && $envelopes[1]->getMessage() instanceof DummyMessage;
            }))
            ->willReturnCallback(function (array $envelopes) {
                return array_map(fn (Envelope $e) => $e->with(new TransportMessageIdStamp('id-'.spl_object_id($e))), $envelopes);
            });

        $sendersLocator = $this->createSendersLocator([DummyMessage::class => ['async']], ['async' => $sender]);
        $middleware = new SendMessageMiddleware($sendersLocator);

        $bus = new MessageBus([$middleware]);
        $envelopes = $bus->dispatchBatch([$message1, $message2]);

        $this->assertCount(2, $envelopes);
        $this->assertNotNull($envelopes[0]->last(TransportMessageIdStamp::class));
        $this->assertNotNull($envelopes[1]->last(TransportMessageIdStamp::class));
        $this->assertNotNull($envelopes[0]->last(SentStamp::class));
        $this->assertNotNull($envelopes[1]->last(SentStamp::class));
    }

    public function testDispatchBatchFallsBackToIndividualSendsForNonBatchSender()
    {
        $message1 = new DummyMessage('Hello');
        $message2 = new DummyMessage('World');

        $sender = $this->createMock(SenderInterface::class);
        $sender->expects($this->exactly(2))
            ->method('send')
            ->willReturnCallback(fn (Envelope $e) => $e->with(new TransportMessageIdStamp('id-'.spl_object_id($e))));

        $sendersLocator = $this->createSendersLocator([DummyMessage::class => ['async']], ['async' => $sender]);
        $middleware = new SendMessageMiddleware($sendersLocator);

        $bus = new MessageBus([$middleware]);
        $envelopes = $bus->dispatchBatch([$message1, $message2]);

        $this->assertCount(2, $envelopes);
        $this->assertNotNull($envelopes[0]->last(TransportMessageIdStamp::class));
        $this->assertNotNull($envelopes[1]->last(TransportMessageIdStamp::class));
    }

    public function testDispatchBatchWithMixedMessages()
    {
        // Message with sender
        $message1 = new DummyMessage('Async');
        // Message without sender (will be handled synchronously)
        $message2 = new \stdClass();

        $sender = $this->createMock(BatchSenderInterface::class);
        $sender->expects($this->once())
            ->method('sendBatch')
            ->willReturnCallback(fn (array $envelopes) => array_map(
                fn (Envelope $e) => $e->with(new TransportMessageIdStamp('id')),
                $envelopes
            ));

        $sendersLocator = $this->createSendersLocator([DummyMessage::class => ['async']], ['async' => $sender]);
        $middleware = new SendMessageMiddleware($sendersLocator);

        $bus = new MessageBus([$middleware]);
        $envelopes = $bus->dispatchBatch([$message1, $message2]);

        $this->assertCount(2, $envelopes);

        // First message should have been sent
        $this->assertNotNull($envelopes[0]->last(SentStamp::class));
        $this->assertNotNull($envelopes[0]->last(TransportMessageIdStamp::class));

        // Second message should NOT have been sent (no sender configured)
        $this->assertNull($envelopes[1]->last(SentStamp::class));
        $this->assertNull($envelopes[1]->last(TransportMessageIdStamp::class));
    }

    public function testDispatchBatchDispatchesEvents()
    {
        $message1 = new DummyMessage('Hello');
        $message2 = new DummyMessage('World');

        $sender = $this->createMock(BatchSenderInterface::class);
        $sender->method('sendBatch')->willReturnArgument(0);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $dispatchedEvents = [];
        $dispatcher->method('dispatch')
            ->willReturnCallback(function (object $event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event::class;

                return $event;
            });

        $sendersLocator = $this->createSendersLocator([DummyMessage::class => ['async']], ['async' => $sender]);
        $middleware = new SendMessageMiddleware($sendersLocator, $dispatcher);

        $bus = new MessageBus([$middleware], $dispatcher);
        $bus->dispatchBatch([$message1, $message2]);

        // Should have SendMessageToTransportsEvent for each message (2x)
        // MessageSentToTransportsEvent for each sent message (2x)
        // BatchSentToTransportsEvent once
        $this->assertContains(SendMessageToTransportsEvent::class, $dispatchedEvents);
        $this->assertContains(MessageSentToTransportsEvent::class, $dispatchedEvents);
        $this->assertContains(BatchSentToTransportsEvent::class, $dispatchedEvents);
    }

    public function testDispatchBatchPreservesMessageOrder()
    {
        $messages = [];
        for ($i = 0; $i < 10; ++$i) {
            $messages[] = new DummyMessage("Message $i");
        }

        $sender = $this->createMock(BatchSenderInterface::class);
        $sender->method('sendBatch')->willReturnCallback(function (array $envelopes) {
            // Transport must return envelopes in same order (per BatchSenderInterface contract)
            // Add a stamp to verify they went through
            return array_map(
                fn (Envelope $e) => $e->with(new TransportMessageIdStamp('id-'.spl_object_id($e))),
                $envelopes
            );
        });

        $sendersLocator = $this->createSendersLocator([DummyMessage::class => ['async']], ['async' => $sender]);
        $middleware = new SendMessageMiddleware($sendersLocator);

        $bus = new MessageBus([$middleware]);
        $envelopes = $bus->dispatchBatch($messages);

        $this->assertCount(10, $envelopes);

        // Verify order matches input
        for ($i = 0; $i < 10; ++$i) {
            $this->assertSame("Message $i", $envelopes[$i]->getMessage()->getMessage());
        }
    }

    private function createSendersLocator(array $sendersMap, array $senders): SendersLocator
    {
        $container = new Container();

        foreach ($senders as $id => $sender) {
            $container->set($id, $sender);
        }

        return new SendersLocator($sendersMap, $container);
    }
}
