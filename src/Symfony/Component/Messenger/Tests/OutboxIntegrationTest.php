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
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\EventListener\AddErrorDetailsStampListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageForRetryListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageToFailureTransportListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\FailedMessageProcessingMiddleware;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\OutboxStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Sender\OutboxSender;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Worker;

class OutboxIntegrationTest extends TestCase
{
    private MockClock $clock;
    private InMemoryTransport $target;
    private InMemoryTransport $outbox;
    private InMemoryTransport $failed;
    private OutboxTestTargetSender $targetSender;
    private OutboxTestHandler $handler;
    private MessageBus $bus;
    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->clock = new MockClock();
        $this->target = new InMemoryTransport(clock: $this->clock);
        $this->outbox = new InMemoryTransport(clock: $this->clock);
        $this->failed = new InMemoryTransport(clock: $this->clock);
        $this->targetSender = new OutboxTestTargetSender($this->target);
        $this->handler = new OutboxTestHandler();

        $senders = new Container();
        $senders->set('orders', new OutboxSender($this->targetSender, $this->outbox, 'orders'));
        $senders->set('outbox', $this->outbox);
        $senders->set('failed', $this->failed);

        $retryStrategies = new Container();
        $retryStrategies->set('orders', new MultiplierRetryStrategy(1, 1000, 1, 0, 0));
        $retryStrategies->set('outbox', new MultiplierRetryStrategy(1, 1000, 1, 0, 0));

        $this->bus = new MessageBus([
            new FailedMessageProcessingMiddleware(),
            new SendMessageMiddleware(new SendersLocator([DummyMessage::class => ['orders']], $senders)),
            new HandleMessageMiddleware(new HandlersLocator([DummyMessage::class => [new HandlerDescriptor($this->handler, ['from_transport' => 'orders'])]])),
        ]);

        $this->dispatcher = new EventDispatcher();
        $this->dispatcher->addSubscriber(new AddErrorDetailsStampListener());
        $this->dispatcher->addSubscriber(new SendFailedMessageForRetryListener($senders, $retryStrategies));
        $this->dispatcher->addSubscriber(new SendFailedMessageToFailureTransportListener(new ServiceLocator(['orders' => fn () => $this->failed, 'outbox' => fn () => $this->failed])));
        $this->dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));
    }

    public function testMessagesAreStoredInTheOutboxThenForwardedToTheTarget()
    {
        // make the message ids of the outbox differ from the ones of the target
        $this->outbox->reject($this->outbox->send(new Envelope(new DummyMessage('warm-up'))));

        $this->bus->dispatch(new DummyMessage('Hey'));

        $this->assertCount(1, $stored = $this->outbox->get());
        $this->assertSame('orders', $stored[0]->last(OutboxStamp::class)?->getTransportName());
        $this->assertSame([], $this->target->get());
        $this->assertSame(0, $this->handler->calls);

        $this->assertNull($this->runWorker('outbox', $this->outbox));

        $this->assertSame([], $this->outbox->get(), 'the outbox acknowledged the forwarded message');
        $this->assertCount(1, $forwarded = $this->target->get());
        $this->assertNull($forwarded[0]->last(OutboxStamp::class));
        $this->assertSame(0, $this->handler->calls, 'the relay does not handle the message');

        $this->assertNull($this->runWorker('orders', $this->target));

        $this->assertSame(1, $this->handler->calls);
        $this->assertSame([], $this->target->get());
    }

    public function testTheDelayIsAppliedByTheOutboxOnly()
    {
        $this->bus->dispatch(new DummyMessage('Hey'), [new DelayStamp(5000)]);

        $this->assertSame([], $this->outbox->get());
        $this->clock->sleep(6);
        $this->assertCount(1, $this->outbox->get());

        $this->assertNull($this->runWorker('outbox', $this->outbox));

        $this->assertCount(1, $forwarded = $this->target->get(), 'the target delivers the message without delaying it again');
        $this->assertNull($forwarded[0]->last(DelayStamp::class));
    }

    public function testARelayFailureIsRetriedThenSentToTheFailureTransportThenRetriedFromThere()
    {
        $this->targetSender->failures = 2;
        $this->bus->dispatch(new DummyMessage('Hey'));

        $this->assertInstanceOf(TransportException::class, $this->runWorker('outbox', $this->outbox));

        $this->assertSame([], $this->target->get());
        $this->assertSame([], $this->failed->get());
        $this->clock->sleep(2);
        $this->assertCount(1, $retried = $this->outbox->get(), 'the retry strategy of the outbox applies to the relay');
        $this->assertSame(1, $retried[0]->last(RedeliveryStamp::class)?->getRetryCount());

        $this->assertInstanceOf(TransportException::class, $this->runWorker('outbox', $this->outbox));

        $this->assertSame([], $this->outbox->get());
        $this->assertSame([], $this->target->get());
        $this->clock->sleep(1);
        $this->assertCount(1, $failed = $this->failed->get(), 'the failure transport of the outbox receives the message');
        $this->assertSame('outbox', $failed[0]->last(SentToFailureTransportStamp::class)?->getOriginalReceiverName());
        $this->assertSame('orders', $failed[0]->last(OutboxStamp::class)?->getTransportName());

        // messenger:failed:retry consumes the failure transport: the message re-enters the relay
        $this->assertNull($this->runWorker('failed', $this->failed));

        $this->assertSame([], $this->failed->get());
        $this->assertSame([], $this->outbox->get());
        $this->assertCount(1, $forwarded = $this->target->get());
        foreach ([OutboxStamp::class, RedeliveryStamp::class, SentToFailureTransportStamp::class, ErrorDetailsStamp::class, DelayStamp::class] as $stampFqcn) {
            $this->assertNull($forwarded[0]->last($stampFqcn), $stampFqcn.' must not reach the target');
        }

        $this->assertNull($this->runWorker('orders', $this->target));

        $this->assertSame(1, $this->handler->calls);
    }

    public function testARetryFromTheTargetDoesNotGoThroughTheOutbox()
    {
        $this->handler->shouldFail = true;
        $this->bus->dispatch(new DummyMessage('Hey'));
        $this->assertNull($this->runWorker('outbox', $this->outbox));

        $this->assertInstanceOf(HandlerFailedException::class, $this->runWorker('orders', $this->target));

        $this->assertSame(1, $this->handler->calls);
        $this->assertCount(1, $this->outbox->getSent(), 'the retry is sent to the target directly');
        $this->clock->sleep(2);
        $this->assertCount(1, $retried = $this->target->get());
        $this->assertSame(1, $retried[0]->last(RedeliveryStamp::class)?->getRetryCount());
        $this->assertNull($retried[0]->last(OutboxStamp::class));

        $this->handler->shouldFail = false;
        $this->assertNull($this->runWorker('orders', $this->target));

        $this->assertSame(2, $this->handler->calls);
        $this->assertSame([], $this->target->get());
    }

    private function runWorker(string $transportName, ReceiverInterface $receiver): ?\Throwable
    {
        $throwable = null;
        $listener = static function (WorkerMessageFailedEvent $event) use (&$throwable) {
            $throwable = $event->getThrowable();
        };
        $this->dispatcher->addListener(WorkerMessageFailedEvent::class, $listener);

        (new Worker([$transportName => $receiver], $this->bus, $this->dispatcher, clock: $this->clock))->run();

        $this->dispatcher->removeListener(WorkerMessageFailedEvent::class, $listener);

        return $throwable;
    }
}

class OutboxTestTargetSender implements SenderInterface
{
    public int $failures = 0;

    public function __construct(
        private SenderInterface $target,
    ) {
    }

    public function send(Envelope $envelope): Envelope
    {
        if (0 < $this->failures--) {
            throw new TransportException('The target is unreachable.');
        }

        return $this->target->send($envelope);
    }
}

class OutboxTestHandler
{
    public int $calls = 0;
    public bool $shouldFail = false;

    public function __invoke(DummyMessage $message): void
    {
        ++$this->calls;

        if ($this->shouldFail) {
            throw new \RuntimeException('Handling failed.');
        }
    }
}
