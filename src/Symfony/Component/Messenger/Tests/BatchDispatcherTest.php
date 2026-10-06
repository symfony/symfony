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
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\BatchDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\MessageSentToTransportsEvent;
use Symfony\Component\Messenger\Exception\BatchSendFailedException;
use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\DispatchAfterCurrentBusMiddleware;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\BatchStamp;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\SecondMessage;
use Symfony\Component\Messenger\Tests\Fixtures\ThirdMessage;
use Symfony\Component\Messenger\Transport\Sender\BatchSenderInterface;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;

class BatchDispatcherTest extends TestCase
{
    public function testEmptyBatch()
    {
        $sender = new BatchDispatcherTestBatchSender();
        $bus = $this->createBus([DummyMessage::class => ['batch']], ['batch' => $sender]);

        $this->assertSame([], (new BatchDispatcher($bus))->dispatch([]));
        $this->assertSame([], $sender->batches);
    }

    public function testBatchSendersGetAllTheirMessagesAtOnce()
    {
        $sender = new BatchDispatcherTestBatchSender();
        $bus = $this->createBus([DummyMessage::class => ['batch']], ['batch' => $sender]);

        $envelopes = (new BatchDispatcher($bus))->dispatch((static function () {
            yield 'a' => new DummyMessage('a');
            yield 'b' => new Envelope(new DummyMessage('b'), [new DelayStamp(1000)]);
        })());

        $this->assertCount(1, $sender->batches);
        $this->assertSame([0, 1], array_keys($sender->batches[0]));
        $this->assertEquals(new SentStamp(BatchDispatcherTestBatchSender::class, 'batch'), $sender->batches[0][0]->last(SentStamp::class));
        $this->assertNull($sender->batches[0][0]->last(BatchStamp::class));
        $this->assertSame(1000, $sender->batches[0][1]->last(DelayStamp::class)?->getDelay());

        $this->assertSame([0, 1], array_keys($envelopes));
        $this->assertSame('batch-a', $envelopes[0]->last(TransportMessageIdStamp::class)?->getId());
        $this->assertSame('batch-b', $envelopes[1]->last(TransportMessageIdStamp::class)?->getId());
        $this->assertNull($envelopes[0]->last(BatchStamp::class));
    }

    public function testStampsAreAddedToEveryMessage()
    {
        $sender = new BatchDispatcherTestBatchSender();
        $bus = $this->createBus([DummyMessage::class => ['batch']], ['batch' => $sender]);

        (new BatchDispatcher($bus))->dispatch([new DummyMessage('a'), new DummyMessage('b')], [new DelayStamp(500)]);

        $this->assertSame(500, $sender->batches[0][0]->last(DelayStamp::class)?->getDelay());
        $this->assertSame(500, $sender->batches[0][1]->last(DelayStamp::class)?->getDelay());
    }

    public function testOtherSendersSendTheirMessagesOneByOne()
    {
        $sender = new BatchDispatcherTestSender();
        $bus = $this->createBus([DummyMessage::class => ['one']], ['one' => $sender]);

        $envelopes = (new BatchDispatcher($bus))->dispatch([new DummyMessage('a'), new DummyMessage('b')]);

        $this->assertSame(['a', 'b'], $sender->attempts);
        $this->assertSame('one-a', $envelopes[0]->last(TransportMessageIdStamp::class)?->getId());
        $this->assertSame('one-b', $envelopes[1]->last(TransportMessageIdStamp::class)?->getId());
    }

    public function testMessagesWithoutSendersAreHandledWhileTheyGoThroughTheBus()
    {
        $sender = new BatchDispatcherTestBatchSender();
        $handled = [];
        $bus = $this->createBus([DummyMessage::class => ['batch']], ['batch' => $sender], [
            SecondMessage::class => [static function (SecondMessage $message) use (&$handled, $sender) {
                $handled[] = \count($sender->batches);
            }],
        ]);

        $envelopes = (new BatchDispatcher($bus))->dispatch([new DummyMessage('a'), new SecondMessage(), new DummyMessage('b')]);

        $this->assertSame([0], $handled, 'the message is handled before the batch is sent');
        $this->assertCount(1, $sender->batches);
        $this->assertSame([0, 2], array_keys($sender->batches[0]));
        $this->assertNotNull($envelopes[1]->last(HandledStamp::class));
        $this->assertNull($envelopes[1]->last(SentStamp::class));
    }

    public function testNothingIsSentWhenAMessageFailsToGoThroughTheBus()
    {
        $sender = new BatchDispatcherTestBatchSender();
        $bus = $this->createBus([DummyMessage::class => ['batch']], ['batch' => $sender]);

        try {
            (new BatchDispatcher($bus))->dispatch([new DummyMessage('a'), new SecondMessage(), new DummyMessage('b')]);
            $this->fail('An exception should have been thrown.');
        } catch (NoHandlerForMessageException) {
        }

        $this->assertSame([], $sender->batches);
    }

    public function testEachTransportGetsTheEnvelopeReturnedByThePreviousOne()
    {
        $batchSender = new BatchDispatcherTestBatchSender();
        $sender = new BatchDispatcherTestSender();
        $bus = $this->createBus([DummyMessage::class => ['batch', 'one']], ['batch' => $batchSender, 'one' => $sender]);

        $envelopes = (new BatchDispatcher($bus))->dispatch([new DummyMessage('a')]);

        $this->assertSame('batch-a', $sender->envelopes[0]->last(TransportMessageIdStamp::class)?->getId());
        $this->assertSame(['batch', 'one'], array_map(static fn (SentStamp $stamp) => $stamp->getSenderAlias(), $sender->envelopes[0]->all(SentStamp::class)));
        $this->assertSame(['batch-a', 'one-a'], array_map(static fn (TransportMessageIdStamp $stamp) => $stamp->getId(), $envelopes[0]->all(TransportMessageIdStamp::class)));
    }

    public function testMessageSentToTransportsEventIsDispatchedOnceTheMessageIsSent()
    {
        $events = [];
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(MessageSentToTransportsEvent::class, static function (MessageSentToTransportsEvent $event) use (&$events) {
            $events[] = [$event->getEnvelope()->last(TransportMessageIdStamp::class)?->getId(), array_keys($event->getSenders())];
        });

        $bus = $this->createBus([DummyMessage::class => ['batch']], ['batch' => new BatchDispatcherTestBatchSender()], [], $eventDispatcher);

        (new BatchDispatcher($bus))->dispatch([new DummyMessage('a'), new DummyMessage('b')]);

        $this->assertSame([['batch-a', ['batch']], ['batch-b', ['batch']]], $events);
    }

    public function testPartialFailure()
    {
        $events = [];
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(MessageSentToTransportsEvent::class, static function (MessageSentToTransportsEvent $event) use (&$events) {
            $events[] = $event->getEnvelope()->getMessage()->getMessage();
        });

        $batchSender = new BatchDispatcherTestBatchSender();
        $batchSender->failures = [1 => $failure = new TransportException('Refused.')];
        $sender = new BatchDispatcherTestSender();
        $bus = $this->createBus([DummyMessage::class => ['batch', 'one']], ['batch' => $batchSender, 'one' => $sender], [], $eventDispatcher);

        try {
            (new BatchDispatcher($bus))->dispatch([new DummyMessage('a'), new DummyMessage('b'), new DummyMessage('c')]);
            $this->fail('An exception should have been thrown.');
        } catch (BatchSendFailedException $e) {
        }

        $this->assertSame('Sending 1 message of the batch failed: Refused.', $e->getMessage());
        $this->assertSame($failure, $e->getPrevious());
        $this->assertSame([1 => $failure], $e->getExceptions());
        $this->assertSame([0, 2], array_keys($e->getEnvelopes()));
        $this->assertSame('one-c', $e->getEnvelopes()[2]->last(TransportMessageIdStamp::class)?->getId());
        $this->assertSame(['a', 'c'], $sender->attempts, 'a message that failed is not sent to the next transport');
        $this->assertSame(['a', 'c'], $events);
    }

    public function testSendingOneByOneStopsAtTheFirstFailure()
    {
        $sender = new BatchDispatcherTestSender();
        $sender->failures = ['b' => $failure = new TransportException('Down.')];
        $bus = $this->createBus([DummyMessage::class => ['one']], ['one' => $sender]);

        try {
            (new BatchDispatcher($bus))->dispatch([new DummyMessage('a'), new DummyMessage('b'), new DummyMessage('c')]);
            $this->fail('An exception should have been thrown.');
        } catch (BatchSendFailedException $e) {
        }

        $this->assertSame(['a', 'b'], $sender->attempts);
        $this->assertSame([1 => $failure, 2 => $failure], $e->getExceptions());
        $this->assertSame([0], array_keys($e->getEnvelopes()));
        $this->assertSame('Sending 2 messages of the batch failed: Down.', $e->getMessage());
    }

    public function testAFailingSenderFailsAllItsMessagesWithoutStoppingTheOthers()
    {
        $batchSender = new BatchDispatcherTestBatchSender();
        $batchSender->exception = $failure = new TransportException('Down.');
        $sender = new BatchDispatcherTestSender();
        $bus = $this->createBus([DummyMessage::class => ['batch']], ['batch' => $batchSender, 'one' => $sender]);

        try {
            (new BatchDispatcher($bus))->dispatch([new DummyMessage('a'), new Envelope(new DummyMessage('b'), [new TransportNamesStamp(['one'])]), new DummyMessage('c')]);
            $this->fail('An exception should have been thrown.');
        } catch (BatchSendFailedException $e) {
        }

        $this->assertSame([0 => $failure, 2 => $failure], $e->getExceptions());
        $this->assertSame([1], array_keys($e->getEnvelopes()));
        $this->assertSame(['b'], $sender->attempts);
    }

    public function testMessagesDispatchedOnceTheBatchIsOverAreSentOnTheirOwn()
    {
        $sender = new BatchDispatcherTestSender();
        $attemptsWhileHandling = null;
        $bus = null;
        $bus = $this->createBus([DummyMessage::class => ['one']], ['one' => $sender], [
            SecondMessage::class => [static function () use (&$bus, &$attemptsWhileHandling, $sender) {
                (new BatchDispatcher($bus))->dispatch([new DummyMessage('a')], [new DispatchAfterCurrentBusStamp()]);
                $attemptsWhileHandling = $sender->attempts;
            }],
        ], null, [new DispatchAfterCurrentBusMiddleware()]);

        $bus->dispatch(new SecondMessage());

        $this->assertSame([], $attemptsWhileHandling);
        $this->assertSame(['a'], $sender->attempts);
    }

    public function testMessagesDispatchedAfterAFailedBatchAreSentOnTheirOwn()
    {
        $sender = new BatchDispatcherTestSender();
        $bus = null;
        $bus = $this->createBus([DummyMessage::class => ['one']], ['one' => $sender], [
            SecondMessage::class => [static function () use (&$bus) {
                try {
                    (new BatchDispatcher($bus))->dispatch([new Envelope(new DummyMessage('a'), [new DispatchAfterCurrentBusStamp()]), new ThirdMessage()]);
                } catch (NoHandlerForMessageException) {
                }
            }],
        ], null, [new DispatchAfterCurrentBusMiddleware()]);

        $bus->dispatch(new SecondMessage());

        $this->assertSame(['a'], $sender->attempts);
    }

    public function testRunSendsTheMessagesTheCallbackDispatched()
    {
        $sender = new BatchDispatcherTestBatchSender();
        $bus = $this->createBus([DummyMessage::class => ['batch']], ['batch' => $sender]);
        $dispatched = [];

        $envelopes = (new BatchDispatcher($bus))->run(static function (MessageBusInterface $bus) use (&$dispatched) {
            $dispatched[] = $bus->dispatch(new DummyMessage('a'));
            $dispatched[] = $bus->dispatch(new DummyMessage('b'), [new DelayStamp(1000)]);
        });

        $this->assertNotNull($dispatched[0]->last(SentStamp::class));
        $this->assertNull($dispatched[0]->last(TransportMessageIdStamp::class), 'the bus returns the envelopes before they are sent');
        $this->assertNull($dispatched[0]->last(BatchStamp::class));
        $this->assertCount(1, $sender->batches);
        $this->assertSame(1000, $sender->batches[0][1]->last(DelayStamp::class)?->getDelay());
        $this->assertSame(['batch-a', 'batch-b'], array_map(static fn (Envelope $envelope) => $envelope->last(TransportMessageIdStamp::class)?->getId(), $envelopes));
    }

    public function testRunSendsNothingWhenTheCallbackThrows()
    {
        $sender = new BatchDispatcherTestBatchSender();
        $bus = $this->createBus([DummyMessage::class => ['batch']], ['batch' => $sender]);
        $exception = new \RuntimeException('Failed.');

        try {
            (new BatchDispatcher($bus))->run(static function (MessageBusInterface $bus) use ($exception) {
                $bus->dispatch(new DummyMessage('a'));

                throw $exception;
            });
            $this->fail('An exception should have been thrown.');
        } catch (\RuntimeException $e) {
            $this->assertSame($exception, $e);
        }

        $this->assertSame([], $sender->batches);
    }

    public function testRunLeavesOutTheMessagesThatFailedToGoThroughTheBus()
    {
        $sender = new BatchDispatcherTestBatchSender();
        $failOnTheWayBack = new class implements MiddlewareInterface {
            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                $envelope = $stack->next()->handle($envelope, $stack);

                if ('boom' === $envelope->getMessage()->getMessage()) {
                    throw new \RuntimeException('Failed after sending was deferred.');
                }

                return $envelope;
            }
        };
        $bus = $this->createBus([DummyMessage::class => ['batch']], ['batch' => $sender], [], null, [$failOnTheWayBack]);

        $envelopes = (new BatchDispatcher($bus))->run(static function (MessageBusInterface $bus) {
            $bus->dispatch(new DummyMessage('a'));

            try {
                $bus->dispatch(new DummyMessage('boom'));
            } catch (\RuntimeException) {
            }

            $bus->dispatch(new DummyMessage('b'));
        });

        $this->assertSame(['a', 'b'], array_map(static fn (Envelope $envelope) => $envelope->getMessage()->getMessage(), $sender->batches[0]));
        $this->assertSame([0, 1], array_keys($sender->batches[0]));
        $this->assertSame(['batch-a', 'batch-b'], array_map(static fn (Envelope $envelope) => $envelope->last(TransportMessageIdStamp::class)?->getId(), $envelopes));
    }

    public function testTheBusGivenToTheCallbackSendsRightAwayOnceTheBatchIsOver()
    {
        $sender = new BatchDispatcherTestSender();
        $bus = $this->createBus([DummyMessage::class => ['one']], ['one' => $sender]);
        $batchBus = null;

        (new BatchDispatcher($bus))->run(static function (MessageBusInterface $bus) use (&$batchBus) {
            $batchBus = $bus;
        });

        $envelope = $batchBus->dispatch(new DummyMessage('late'));

        $this->assertSame(['late'], $sender->attempts);
        $this->assertSame('one-late', $envelope->last(TransportMessageIdStamp::class)?->getId());
    }

    private function createBus(array $routing, array $senders, array $handlers = [], ?EventDispatcher $eventDispatcher = null, array $middleware = []): MessageBus
    {
        $container = new Container();
        foreach ($senders as $alias => $sender) {
            $container->set($alias, $sender);
        }

        return new MessageBus([
            ...$middleware,
            new SendMessageMiddleware(new SendersLocator($routing, $container), $eventDispatcher),
            new HandleMessageMiddleware(new HandlersLocator($handlers)),
        ]);
    }
}

class BatchDispatcherTestBatchSender implements BatchSenderInterface
{
    public array $batches = [];
    public array $failures = [];
    public ?\Throwable $exception = null;

    public function send(Envelope $envelope): Envelope
    {
        throw new \LogicException('Batches must be sent with sendBatch().');
    }

    public function sendBatch(array $envelopes): array
    {
        $this->batches[] = $envelopes;

        if ($this->exception) {
            throw $this->exception;
        }

        $sent = array_map(static fn (Envelope $envelope) => $envelope->with(new TransportMessageIdStamp('batch-'.$envelope->getMessage()->getMessage())), array_diff_key($envelopes, $this->failures));

        if ($failures = array_intersect_key($this->failures, $envelopes)) {
            throw new BatchSendFailedException($sent, $failures);
        }

        return $sent;
    }
}

class BatchDispatcherTestSender implements SenderInterface
{
    public array $attempts = [];
    public array $envelopes = [];
    public array $failures = [];

    public function send(Envelope $envelope): Envelope
    {
        $this->attempts[] = $message = $envelope->getMessage()->getMessage();
        $this->envelopes[] = $envelope;

        if (isset($this->failures[$message])) {
            throw $this->failures[$message];
        }

        return $envelope->with(new TransportMessageIdStamp('one-'.$message));
    }
}
