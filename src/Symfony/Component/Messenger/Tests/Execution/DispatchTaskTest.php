<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Execution;

use Amp\Cancellation;
use Amp\Parallel\Worker\ContextWorkerFactory;
use Amp\Sync\Channel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Execution\DispatchTask;
use Symfony\Component\Messenger\Execution\Message\DeferredEnvelopeMessage;
use Symfony\Component\Messenger\Execution\Message\DispatchEnvelopeMessage;
use Symfony\Component\Messenger\Execution\Message\FlushBatchHandlersMessage;
use Symfony\Component\Messenger\Execution\Message\HandledEnvelopeMessage;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Tests\Fixtures\App\BatchHandlingMiddleware;
use Symfony\Component\Messenger\Tests\Fixtures\App\ConnectionStamp;
use Symfony\Component\Messenger\Tests\Fixtures\App\ConnectStamp;
use Symfony\Component\Messenger\Tests\Fixtures\App\FailingResetStamp;
use Symfony\Component\Messenger\Tests\Fixtures\App\HandledByBusStamp;
use Symfony\Component\Messenger\Tests\Fixtures\App\ResettableService;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\DummyStampHoldingAnObject;
use Symfony\Component\Messenger\Tests\Fixtures\SecondMessage;
use Symfony\Component\Messenger\Tests\Fixtures\ThirdMessage;
use Symfony\Contracts\Service\ContainerProviderInterface;

class DispatchTaskTest extends TestCase
{
    private int $resetInterval = 1;
    private ?int $memoryLimit = null;

    protected function setUp(): void
    {
        if (!class_exists(ContextWorkerFactory::class)) {
            $this->markTestSkipped(ContextWorkerFactory::class.' is not available.');
        }
    }

    public function testItBootstrapsTheApplicationUsingConsoleEntryPoint()
    {
        $envelope = $this->runTask(new Envelope(new DummyMessage('Hello')));

        $this->assertInstanceOf(Envelope::class, $envelope);
        $this->assertSame('Hello', $envelope->getMessage()->getMessage());
    }

    public function testItPreservesStampedBusRoutingWhenBootstrappingTheApplication()
    {
        $envelope = $this->runTask((new Envelope(new DummyMessage('Hello')))->with(new BusNameStamp('other.bus')));

        $this->assertSame('other.bus', $envelope->last(HandledByBusStamp::class)?->getBusName());
    }

    public function testFixtureConsoleReturnsAContainerAwareApplicationDirectly()
    {
        $app = (static fn () => require __DIR__.'/../Fixtures/App/bin/console')->bindTo(null, null)();

        $this->assertInstanceOf(ContainerProviderInterface::class, $app);
    }

    public function testAFullBatchIsProcessedInADispatchOfItsOwn()
    {
        BatchHandlingMiddleware::$log = [];

        $sent = $this->runTaskWith(new DispatchEnvelopeMessage(1, new Envelope(new DummyMessage('Hey'))), new DispatchEnvelopeMessage(2, new Envelope(new DummyMessage('Bob'))));

        $this->assertSame(['begin Hey', 'end Hey', 'begin Bob', 'end Bob', 'begin flush', 'process Hey,Bob', 'end flush'], BatchHandlingMiddleware::$log);
        $this->assertSame([DeferredEnvelopeMessage::class, HandledEnvelopeMessage::class, HandledEnvelopeMessage::class], array_map(get_debug_type(...), $sent));
        $this->assertSame([1, 1, 2], array_map(static fn (object $message) => $message->requestId, $sent));
    }

    public function testAFullBatchThatFailsIsCalledAgainOnRetry()
    {
        BatchHandlingMiddleware::$log = [];

        $sent = $this->runTaskWith(new DispatchEnvelopeMessage(1, new Envelope(new DummyMessage('Hey'))), new DispatchEnvelopeMessage(2, new Envelope(new DummyMessage('fail'))));

        $this->assertSame(['begin Hey', 'end Hey', 'begin fail', 'end fail', 'begin flush', 'process Hey,fail', 'end flush'], BatchHandlingMiddleware::$log);
        $this->assertSame([DeferredEnvelopeMessage::class, HandledEnvelopeMessage::class, HandledEnvelopeMessage::class], array_map(get_debug_type(...), $sent));
        $this->assertSame([1, 1, 2], array_map(static fn (object $message) => $message->requestId, $sent));

        foreach ([$sent[1], $sent[2]] as $handled) {
            $this->assertInstanceOf(HandlerFailedException::class, $handled->error);
            $this->assertSame([], $handled->envelope->all(HandledStamp::class));
        }
    }

    public function testItStopsAfterAFailedDispatchOnceItsMemoryLimitIsReached()
    {
        $this->memoryLimit = 1;

        $sent = $this->runTaskWith(new DispatchEnvelopeMessage(1, new Envelope(new \stdClass())), new DispatchEnvelopeMessage(2, new Envelope(new DummyMessage('Hey'))));

        $this->assertSame([HandledEnvelopeMessage::class, 'null'], array_map(get_debug_type(...), $sent));
        $this->assertSame(1, $sent[0]->requestId);
        $this->assertNotNull($sent[0]->error);
    }

    public function testFailedDispatchesCountTowardsTheResetInterval()
    {
        ResettableService::$resets = 0;
        $this->resetInterval = 2;

        $sent = $this->runTaskWith(new DispatchEnvelopeMessage(1, new Envelope(new SecondMessage())), new DispatchEnvelopeMessage(2, new Envelope(new SecondMessage())));

        $this->assertCount(2, $sent);
        $this->assertInstanceOf(HandlerFailedException::class, $sent[0]->error);
        $this->assertInstanceOf(HandlerFailedException::class, $sent[1]->error);
        $this->assertSame(1, ResettableService::$resets);
    }

    public function testItStopsLikeOnItsMemoryLimitWhenTheResetOfServicesFails()
    {
        try {
            $handled = $this->runTaskWith(new DispatchEnvelopeMessage(1, new Envelope(new DummyMessage('Hey'), [new FailingResetStamp()])), new DispatchEnvelopeMessage(2, new Envelope(new DummyMessage('Bob'))));
            $failed = $this->runTaskWith(new DispatchEnvelopeMessage(1, new Envelope(new SecondMessage(), [new FailingResetStamp()])), new DispatchEnvelopeMessage(2, new Envelope(new DummyMessage('Bob'))));
        } finally {
            ResettableService::$failing = false;
        }

        $this->assertSame([DeferredEnvelopeMessage::class, HandledEnvelopeMessage::class, 'null', \RuntimeException::class], array_map(get_debug_type(...), $handled));
        $this->assertSame(1, $handled[1]->envelope->last(HandledStamp::class)?->getResult());
        $this->assertSame('Reset failed.', $handled[3]->getMessage());
        $this->assertSame([HandledEnvelopeMessage::class, 'null', \RuntimeException::class], array_map(get_debug_type(...), $failed));
        $this->assertInstanceOf(HandlerFailedException::class, $failed[0]->error);
    }

    public function testItDoesNotSendBackTheNonSendableStampsThatItAdded()
    {
        foreach ([new ThirdMessage(), new DummyMessage('Hello')] as $message) {
            $envelope = $this->runTask(new Envelope($message, [new ReceivedStamp('async'), new ConnectStamp()]));

            $this->assertNull($envelope->last(ConnectionStamp::class));
            $this->assertSame('async', $envelope->last(ReceivedStamp::class)?->getTransportName());

            $envelope = $this->runTask(new Envelope($message, [$stamp = new ConnectionStamp('42'), new ConnectStamp()]));

            $this->assertSame([$stamp], $envelope->all(ConnectionStamp::class));
        }
    }

    public function testAnOutcomeThatCannotBeSentEndsTheTask()
    {
        $stamp = new DummyStampHoldingAnObject(static fn () => null);

        $flushed = $this->runTaskWith(new DispatchEnvelopeMessage(1, new Envelope(new DummyMessage('Hey'), [$stamp])), new FlushBatchHandlersMessage(true));
        $full = $this->runTaskWith(new DispatchEnvelopeMessage(1, new Envelope(new DummyMessage('Hey'))), new DispatchEnvelopeMessage(2, new Envelope(new DummyMessage('Bob'), [$stamp])));

        $this->assertSame([DeferredEnvelopeMessage::class, \Exception::class], array_map(get_debug_type(...), $flushed));
        $this->assertSame([DeferredEnvelopeMessage::class, HandledEnvelopeMessage::class, \Exception::class], array_map(get_debug_type(...), $full));
        $this->assertSame("Serialization of 'Closure' is not allowed", $full[2]->getMessage());
    }

    public function testAnOutcomeThatCannotBeSentDoesNotLoseTheOtherOutcomesOfItsBatch()
    {
        $sent = $this->runTaskWith(new DispatchEnvelopeMessage(1, new Envelope(new DummyMessage('Hey'), [new DummyStampHoldingAnObject(static fn () => null)])), new DispatchEnvelopeMessage(2, new Envelope(new DummyMessage('Bob'))));

        $this->assertSame([DeferredEnvelopeMessage::class, HandledEnvelopeMessage::class, \Exception::class], array_map(get_debug_type(...), $sent));
        $this->assertSame(2, $sent[1]->requestId);
        $this->assertNull($sent[1]->error);
        $this->assertSame(2, $sent[1]->envelope->last(HandledStamp::class)?->getResult());
    }

    private function runTask(Envelope $envelope): Envelope
    {
        $sent = $this->runTaskWith(new DispatchEnvelopeMessage(1, $envelope), new FlushBatchHandlersMessage(true));
        $result = end($sent);

        self::assertInstanceOf(HandledEnvelopeMessage::class, $result);

        return $result->envelope;
    }

    /**
     * @return list<object>
     */
    private function runTaskWith(object ...$messages): array
    {
        $sent = [];

        $channel = new class($messages, $sent) implements Channel {
            public function __construct(
                private array $messages,
                private array &$sent,
            ) {
            }

            public function send(mixed $data): void
            {
                // like a real channel, fail on what cannot be serialized
                serialize($data);
                $this->sent[] = $data;
            }

            public function receive(?Cancellation $cancellation = null): mixed
            {
                return array_shift($this->messages);
            }

            public function close(): void
            {
            }

            public function isClosed(): bool
            {
                return false;
            }

            public function onClose(\Closure $onClose): void
            {
            }
        };

        $task = new DispatchTask(__DIR__.'/../Fixtures/App/bin/console', $this->resetInterval, $this->memoryLimit);

        $cancellation = new class implements Cancellation {
            public function subscribe(\Closure $callback): string
            {
                return 'subscription';
            }

            public function unsubscribe(string $id): void
            {
            }

            public function throwIfRequested(): void
            {
            }

            public function isRequested(): bool
            {
                return false;
            }
        };

        try {
            $task->run($channel, $cancellation);
        } catch (\Throwable $e) {
            $sent[] = $e;
        }

        return $sent;
    }
}
