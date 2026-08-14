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
use Amp\DeferredFuture;
use Amp\Parallel\Worker\ContextWorkerFactory;
use Amp\Sync\Channel;
use PHPUnit\Framework\TestCase;
use Revolt\EventLoop;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Execution\Message\DeferredEnvelopeMessage;
use Symfony\Component\Messenger\Execution\Message\DispatchEnvelopeMessage;
use Symfony\Component\Messenger\Execution\Message\FlushBatchHandlersMessage;
use Symfony\Component\Messenger\Execution\Message\HandledEnvelopeMessage;
use Symfony\Component\Messenger\Execution\ParallelExecutionStrategy;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\SecondMessage;

class ParallelExecutionStrategyTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(ContextWorkerFactory::class)) {
            $this->markTestSkipped(ContextWorkerFactory::class.' is not available.');
        }
    }

    public function testShouldPauseConsumptionWhileAProbeIsPending()
    {
        $strategy = new ParallelExecutionStrategy(__DIR__.'/../Fixtures/App/bin/console');

        $this->setPrivateProperty($strategy, 'pendingProbeRequestId', 1);

        $this->assertTrue($strategy->shouldPauseConsumption());
    }

    public function testShouldPauseConsumptionWhenAllWorkersAreBusy()
    {
        $strategy = new ParallelExecutionStrategy(__DIR__.'/../Fixtures/App/bin/console', 2);

        $this->setPrivateProperty($strategy, 'busyChannels', [1 => true, 2 => true]);

        $this->assertTrue($strategy->shouldPauseConsumption());
    }

    public function testShouldNotPauseConsumptionWhileMoreWorkersCanBeCreated()
    {
        $strategy = new ParallelExecutionStrategy(__DIR__.'/../Fixtures/App/bin/console', 2);

        $this->setPrivateProperty($strategy, 'busyChannels', [1 => true]);

        $this->assertFalse($strategy->shouldPauseConsumption());
    }

    public function testExecuteDispatchesTheEnvelopeToAWorkerAndProbesTheMessageKey()
    {
        $channel = $this->createFakeChannel();
        $strategy = $this->createStrategy($channel);
        $envelope = new Envelope(new DummyMessage('Hello'));
        $calls = [];

        $strategy->execute($envelope, 'async', $this->createOnHandled($calls));

        $this->assertCount(1, $channel->sent);
        $this->assertInstanceOf(DispatchEnvelopeMessage::class, $channel->sent[0]);
        $this->assertSame($envelope, $channel->sent[0]->envelope);
        $this->assertSame([], $calls);
        $this->assertTrue($strategy->shouldPauseConsumption());
    }

    public function testHandledResponseCompletesTheRequestAndReleasesTheWorker()
    {
        $envelope = new Envelope(new DummyMessage('Hello'));
        $channel = $this->createFakeChannel([new HandledEnvelopeMessage(1, $envelope, null)]);
        $strategy = $this->createStrategy($channel);
        $calls = [];

        $strategy->execute($envelope, 'async', $this->createOnHandled($calls));

        $this->assertCount(1, $calls);
        $this->assertSame([$envelope->getMessage(), 'async', null], [$calls[0][0]->getMessage(), $calls[0][1], $calls[0][2]]);
        $this->assertFalse($strategy->shouldPauseConsumption());
    }

    public function testDeferredResponseReleasesTheWorkerWhileKeepingTheRequestPending()
    {
        $envelope = new Envelope(new DummyMessage('Hello'));
        $channel = $this->createFakeChannel([new DeferredEnvelopeMessage(1)]);
        $strategy = $this->createStrategy($channel);
        $calls = [];

        $strategy->execute($envelope, 'async', $this->createOnHandled($calls));

        $this->assertSame([], $calls);
        $this->assertFalse($strategy->shouldPauseConsumption());
    }

    public function testDuplicateDeferredResponsesAreIgnored()
    {
        $envelope = new Envelope(new DummyMessage('Hello'));
        $channel = $this->createFakeChannel([new DeferredEnvelopeMessage(1), new DeferredEnvelopeMessage(1)]);
        $strategy = $this->createStrategy($channel);
        $calls = [];

        $strategy->execute($envelope, 'async', $this->createOnHandled($calls));
        $strategy->flush($this->createOnHandled($calls));

        $this->assertSame([], $calls);
        $this->assertFalse($strategy->shouldPauseConsumption());
    }

    public function testForcedFlushDeliversDeferredEnvelopes()
    {
        $envelope = new Envelope(new DummyMessage('Hello'));
        $channel = $this->createFakeChannel([new DeferredEnvelopeMessage(1)], [new HandledEnvelopeMessage(1, $envelope, null)]);
        $strategy = $this->createStrategy($channel);
        $calls = [];

        $strategy->execute($envelope, 'async', $this->createOnHandled($calls));
        $this->assertSame([], $calls);

        $this->assertTrue($strategy->flush($this->createOnHandled($calls), true));

        $this->assertInstanceOf(FlushBatchHandlersMessage::class, $channel->sent[1]);
        $this->assertTrue($channel->sent[1]->force);
        $this->assertCount(1, $calls);
        $this->assertSame([$envelope->getMessage(), 'async', null], [$calls[0][0]->getMessage(), $calls[0][1], $calls[0][2]]);
        $this->assertFalse($strategy->shouldPauseConsumption());
    }

    public function testHandlerErrorsAreRebuiltAroundTheLocalEnvelope()
    {
        $envelope = new Envelope(new DummyMessage('Hello'));
        $remoteError = new HandlerFailedException($envelope, ['handler' => new \RuntimeException('boom')]);
        $channel = $this->createFakeChannel([new HandledEnvelopeMessage(1, $envelope, $remoteError)]);
        $strategy = $this->createStrategy($channel);
        $calls = [];

        $strategy->execute($envelope, 'async', $this->createOnHandled($calls));

        $this->assertCount(1, $calls);
        [$handledEnvelope, $transportName, $error] = $calls[0];
        $this->assertSame('async', $transportName);
        $this->assertSame($envelope->getMessage(), $handledEnvelope->getMessage());
        $this->assertInstanceOf(HandlerFailedException::class, $error);
        $this->assertNotSame($remoteError, $error);
        $this->assertSame('boom', $error->getWrappedExceptions()['handler']->getMessage());
    }

    public function testAFailedWorkerFailsItsPendingRequests()
    {
        $envelope = new Envelope(new DummyMessage('Hello'));
        $channel = $this->createFakeChannel([new \RuntimeException('worker crashed')]);
        $strategy = $this->createStrategy($channel);
        $calls = [];

        $strategy->execute($envelope, 'async', $this->createOnHandled($calls));

        $this->assertCount(1, $calls);
        [$failedEnvelope, $transportName, $error] = $calls[0];
        $this->assertSame($envelope, $failedEnvelope);
        $this->assertSame('async', $transportName);
        $this->assertInstanceOf(\RuntimeException::class, $error);
        $this->assertSame('The parallel worker stopped before returning a result.', $error->getMessage());
        $this->assertSame('worker crashed', $error->getPrevious()->getMessage());
        $this->assertFalse($strategy->shouldPauseConsumption());
    }

    public function testHandledResponseReportsTheRequestsOwnTransport()
    {
        $channel = $this->createFakeChannel([new DeferredEnvelopeMessage(1)]);
        $strategy = $this->createStrategy($channel);
        $calls = [];

        $strategy->execute(new Envelope(new DummyMessage('A')), 'transport_a', $this->createOnHandled($calls));
        $this->assertSame([], $calls);

        $envelopeB = new Envelope(new SecondMessage());
        $channel->push(new HandledEnvelopeMessage(2, $envelopeB, null));
        $strategy->execute($envelopeB, 'transport_b', $this->createOnHandled($calls));

        $this->assertCount(1, $calls);
        $this->assertSame('transport_b', $calls[0][1]);
    }

    public function testDeferredResponseKeepsTheChannelBusyWhileAnotherDispatchIsInFlight()
    {
        $channel = $this->createFakeChannel([new DeferredEnvelopeMessage(1)]);
        $strategy = $this->createStrategy($channel);
        $this->setPrivateProperty($strategy, 'keyModes', ['message.bus||'.SecondMessage::class => 1]);
        $calls = [];

        $strategy->execute(new Envelope(new DummyMessage('A')), 'async', $this->createOnHandled($calls));
        $this->assertFalse($strategy->shouldPauseConsumption());

        $strategy->execute(new Envelope(new SecondMessage()), 'async', $this->createOnHandled($calls));
        $this->assertTrue($strategy->shouldPauseConsumption());

        $channel->push(new DeferredEnvelopeMessage(3));
        $strategy->execute(new Envelope(new DummyMessage('C')), 'async', $this->createOnHandled($calls));

        $this->assertSame([], $calls);
        $this->assertTrue($strategy->shouldPauseConsumption());
    }

    public function testHandledEnvelopeKeepsTheOriginalMessageIdentity()
    {
        $message = new DummyMessage('Hello');
        $remoteEnvelope = new Envelope(new DummyMessage('Hello'), [new BusNameStamp('the-bus')]);
        $channel = $this->createFakeChannel([new HandledEnvelopeMessage(1, $remoteEnvelope, null)]);
        $strategy = $this->createStrategy($channel);
        $calls = [];

        $strategy->execute(new Envelope($message), 'async', $this->createOnHandled($calls));

        $this->assertCount(1, $calls);
        $this->assertSame($message, $calls[0][0]->getMessage());
        $this->assertSame('the-bus', $calls[0][0]->last(BusNameStamp::class)->getBusName());
    }

    public function testWaitSkipsStaleResponses()
    {
        $envelope = new Envelope(new DummyMessage('Hello'));
        $channel = $this->createFakeChannel();
        $strategy = $this->createStrategy($channel);
        $calls = [];

        $strategy->execute($envelope, 'async', $this->createOnHandled($calls));

        $channel->push(new HandledEnvelopeMessage(999, $envelope, null));
        $channel->push(new HandledEnvelopeMessage(1, $envelope, null));

        $this->assertTrue($strategy->wait($this->createOnHandled($calls)));
        $this->assertCount(1, $calls);
        $this->assertFalse($strategy->shouldPauseConsumption());
    }

    public function testBatchingMessagesKeepTheirWorkerAffinity()
    {
        $channelA = $this->createFakeChannel([new DeferredEnvelopeMessage(1)]);
        $channelB = $this->createFakeChannel();
        $strategy = $this->createStrategy([$channelA, $channelB], 2);
        $calls = [];

        $strategy->execute(new Envelope(new DummyMessage('First')), 'async', $this->createOnHandled($calls));
        $strategy->execute(new Envelope(new DummyMessage('Second')), 'async', $this->createOnHandled($calls));

        $this->assertCount(2, $channelA->sent);
        $this->assertSame([], $channelB->sent);
    }

    private function createStrategy(object|array $channels, int $concurrencyLimit = 1): ParallelExecutionStrategy
    {
        $strategy = new ParallelExecutionStrategy(__DIR__.'/../Fixtures/App/bin/console', $concurrencyLimit);

        $indexedChannels = [];
        foreach (\is_array($channels) ? $channels : [$channels] as $channel) {
            $indexedChannels[spl_object_id($channel)] = $channel;
        }
        $this->setPrivateProperty($strategy, 'channels', $indexedChannels);

        return $strategy;
    }

    private function createOnHandled(array &$calls): \Closure
    {
        return static function (Envelope $envelope, string $transportName, bool &$acked, ?\Throwable $error = null) use (&$calls) {
            $calls[] = [$envelope, $transportName, $error];
        };
    }

    private function createFakeChannel(array $responses = [], array $flushResponses = []): Channel
    {
        return new class($responses, $flushResponses) implements Channel {
            public array $sent = [];
            private array $queue;
            private ?DeferredFuture $waiter = null;
            private int $receiveCalls = 0;

            public function __construct(
                array $responses,
                private array $flushResponses,
            ) {
                $this->queue = $responses;
            }

            public function push(mixed $response): void
            {
                if ($waiter = $this->waiter) {
                    $this->waiter = null;
                    $waiter->complete($response);
                } else {
                    $this->queue[] = $response;
                }
            }

            public function send(mixed $data): void
            {
                $this->sent[] = $data;

                if ($data instanceof FlushBatchHandlersMessage) {
                    foreach ($this->flushResponses as $response) {
                        $this->push($response);
                    }
                    $this->flushResponses = [];
                }
            }

            public function receive(?Cancellation $cancellation = null): mixed
            {
                if (100 < ++$this->receiveCalls) {
                    throw new \LogicException('Too many receive() calls, the strategy is likely stuck in a wait loop.');
                }

                if ($this->queue) {
                    $response = array_shift($this->queue);
                } else {
                    // a real channel is stream-backed and keeps the event loop referenced
                    // while waiting; emulate this so that Revolt's deadlock detection
                    // does not kill the parked receive when the loop goes idle
                    $keepAlive = EventLoop::repeat(60, static fn () => null);
                    $this->waiter = new DeferredFuture();

                    try {
                        $response = $this->waiter->getFuture()->await($cancellation);
                    } finally {
                        EventLoop::cancel($keepAlive);
                    }
                }

                if ($response instanceof \Throwable) {
                    throw $response;
                }

                return $response;
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
    }

    private function setPrivateProperty(object $object, string $property, mixed $value)
    {
        $reflectionProperty = new \ReflectionProperty($object, $property);
        $reflectionProperty->setValue($object, $value);
    }
}
