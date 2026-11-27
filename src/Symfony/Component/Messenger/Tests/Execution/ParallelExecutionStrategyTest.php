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
use Amp\Sync\ChannelException;
use PHPUnit\Framework\TestCase;
use Revolt\EventLoop;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Execution\Message\DeferredEnvelopeMessage;
use Symfony\Component\Messenger\Execution\Message\DispatchEnvelopeMessage;
use Symfony\Component\Messenger\Execution\Message\FlushBatchHandlersMessage;
use Symfony\Component\Messenger\Execution\Message\HandledEnvelopeMessage;
use Symfony\Component\Messenger\Execution\MessageExecutionCallback;
use Symfony\Component\Messenger\Execution\ParallelExecutionStrategy;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Tests\Fixtures\ChildDummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\SecondMessage;

class ParallelExecutionStrategyTest extends TestCase
{
    private array $fakeChannels = [];

    protected function setUp(): void
    {
        if (!class_exists(ContextWorkerFactory::class)) {
            $this->markTestSkipped(ContextWorkerFactory::class.' is not available.');
        }
    }

    protected function tearDown(): void
    {
        // A receive still pending when the test ends leaves its fiber suspended, and destroying a suspended fiber while an exception unwinds makes PHP skip the catch block that should handle that exception.
        // Collect it now, instead of in whatever later test the garbage collector happens to run.
        gc_collect_cycles();

        foreach ($this->fakeChannels as $channel) {
            $this->assertNull($channel->get());
        }
    }

    public function testPublicPhaseCallbacksKeepTheirArity()
    {
        foreach (['wait', 'flush'] as $method) {
            $envelope = new Envelope(new DummyMessage('Hello'));
            $channel = $this->createFakeChannel([new DeferredEnvelopeMessage(1)]);
            $strategy = $this->createStrategy($channel);
            $initial = [];
            $calls = [];

            $strategy->execute($envelope, 'async', $this->createOnHandled($initial));
            $channel->push(new HandledEnvelopeMessage(1, $envelope, null));
            $strategy->$method(static function (Envelope $envelope, string $transportName, bool &$acked, ...$arguments) use (&$calls) {
                $calls[] = [\func_num_args(), \count($arguments)];
            });

            $this->assertSame([], $initial);
            $this->assertSame([[4, 1]], $calls);
        }
    }

    public function testInternalCompletionsFollowOutOfOrderRequests()
    {
        $envelope = new Envelope(new DummyMessage('Hello'));
        $channel = $this->createFakeChannel([new DeferredEnvelopeMessage(1)]);
        $strategy = $this->createStrategy($channel);
        $first = $second = $phase = [];

        $strategy->execute($envelope, 'async', new MessageExecutionCallback($this->createOnHandled($first), static function () {}));
        $strategy->execute($envelope, 'async', new MessageExecutionCallback($this->createOnHandled($second), static function () {}));
        $completion = new MessageExecutionCallback($this->createOnHandled($phase), static function () {});

        $channel->push(new HandledEnvelopeMessage(2, $envelope, null));
        $strategy->wait($completion);
        $counts = [[\count($first), \count($second)]];

        $channel->push(new HandledEnvelopeMessage(1, $envelope, null));
        $strategy->wait($completion);
        $counts[] = [\count($first), \count($second)];
        $this->assertSame([[0, 1], [1, 1]], $counts);
        $this->assertSame([], $phase);
    }

    public function testAProbeDoesNotPauseConsumptionOfOtherKeys()
    {
        $probeChannel = $this->createFakeChannel();
        $channel = $this->createFakeChannel();
        $strategy = $this->createStrategy([$probeChannel, $channel], 2);
        $calls = [];

        $strategy->execute(new Envelope(new DummyMessage('slow')), 'async', $this->createOnHandled($calls));

        $this->assertFalse($strategy->shouldPauseConsumption());

        $strategy->execute(new Envelope(new SecondMessage()), 'async', $this->createOnHandled($calls));

        $this->assertSame([], $calls);
        $this->assertCount(1, $probeChannel->sent);
        $this->assertCount(1, $channel->sent);
    }

    public function testMessagesOfAProbedKeyAreHeldUntilItsProbeDefers()
    {
        $probeChannel = $this->createFakeChannel();
        $channel = $this->createFakeChannel();
        $strategy = $this->createStrategy([$probeChannel, $channel], 2);
        $calls = [];

        $strategy->execute(new Envelope(new DummyMessage('probe')), 'async', $this->createOnHandled($calls));
        $strategy->execute(new Envelope(new DummyMessage('sibling')), 'async', $this->createOnHandled($calls));

        $this->assertSame([], $calls);
        $this->assertCount(1, $probeChannel->sent);
        $this->assertSame([], $channel->sent);

        $probeChannel->push(new DeferredEnvelopeMessage(1));
        $this->assertTrue($strategy->wait($this->createOnHandled($calls)));

        $this->assertSame([], $calls);
        $this->assertCount(2, $probeChannel->sent);
        $this->assertSame([], $channel->sent);
        $this->assertFalse($strategy->shouldPauseConsumption());
    }

    public function testHeldMessagesRunOnceAProbeCompletesWithoutBatching()
    {
        $probeChannel = $this->createFakeChannel();
        $channel = $this->createFakeChannel();
        $strategy = $this->createStrategy([$probeChannel, $channel], 2);
        $calls = [];

        $strategy->execute($probe = new Envelope(new DummyMessage('probe')), 'async', $this->createOnHandled($calls));
        $strategy->execute($sibling = new Envelope(new DummyMessage('sibling')), 'async', $this->createOnHandled($calls));
        $this->assertSame([], $calls);

        $probeChannel->push(new HandledEnvelopeMessage(1, $probe, null));
        $this->assertTrue($strategy->wait($this->createOnHandled($calls)));

        $this->assertCount(1, $calls);
        $this->assertSame('probe', $calls[0][0]->getMessage()->getMessage());
        $this->assertCount(2, $probeChannel->sent);

        $probeChannel->push(new HandledEnvelopeMessage(2, $sibling, null));
        $this->assertTrue($strategy->wait($this->createOnHandled($calls)));

        $this->assertCount(2, $calls);
        $this->assertSame('sibling', $calls[1][0]->getMessage()->getMessage());
    }

    public function testHeldMessagesCountTowardsTheConcurrencyLimit()
    {
        $probeChannel = $this->createFakeChannel();
        $channel = $this->createFakeChannel();
        $strategy = $this->createStrategy([$probeChannel, $channel], 2);
        $calls = [];
        $onHandled = $this->createOnHandled($calls);

        $strategy->execute($probe = new Envelope(new DummyMessage('A')), 'async', $onHandled);
        $strategy->execute($envelopeB = new Envelope(new DummyMessage('B')), 'async', $onHandled);
        $strategy->execute($envelopeC = new Envelope(new DummyMessage('C')), 'async', $onHandled);

        $this->assertTrue($strategy->shouldPauseConsumption());

        $probeChannel->push(new HandledEnvelopeMessage(1, $probe, null));
        $this->assertTrue($strategy->wait($onHandled));

        // the held messages are dispatched once the probe completed: their workers are busy now
        $this->assertTrue($strategy->shouldPauseConsumption());

        $this->pushHandled($probeChannel, $channel, 2, $envelopeB);
        $this->assertTrue($strategy->wait($onHandled));
        $this->assertFalse($strategy->shouldPauseConsumption());

        $this->pushHandled($probeChannel, $channel, 3, $envelopeC);
        $this->assertTrue($strategy->wait($onHandled));
        $this->assertFalse($strategy->shouldPauseConsumption());
        $this->assertCount(3, $calls);
    }

    public function testFlushForceDoesNotStrandHeldMessages()
    {
        $probe = new Envelope(new DummyMessage('probe'));
        $sibling = new Envelope(new DummyMessage('sibling'));
        $probeChannel = $this->createFakeChannel([], [new HandledEnvelopeMessage(1, $probe, null)]);
        $channel = $this->createFakeChannel([], [new HandledEnvelopeMessage(2, $sibling, null)]);
        $strategy = $this->createStrategy([$probeChannel, $channel], 2);
        $calls = [];

        $strategy->execute($probe, 'async', $this->createOnHandled($calls));
        $strategy->execute($sibling, 'async', $this->createOnHandled($calls));
        $this->assertSame([], $calls);

        $this->assertTrue($strategy->flush($this->createOnHandled($calls), true));

        $this->assertCount(2, $calls);
        $this->assertFalse($strategy->shouldPauseConsumption());
    }

    public function testTheHeldMessagesOfAProbeWhoseWorkerFailedProbeAgain()
    {
        $failingChannel = $this->createFakeChannel();
        $channel = $this->createFakeChannel();
        $strategy = $this->createStrategy([$failingChannel, $channel]);
        $calls = [];

        $strategy->execute($probe = new Envelope(new DummyMessage('probe')), 'async', $this->createOnHandled($calls));
        $strategy->execute($sibling = new Envelope(new DummyMessage('sibling')), 'async', $this->createOnHandled($calls));
        $this->assertSame([], $channel->sent);

        $failingChannel->push(new ChannelException('Channel source closed unexpectedly'));
        $this->assertTrue($strategy->wait($this->createOnHandled($calls)));

        $this->assertCount(1, $calls);
        $this->assertSame($probe, $calls[0][0]);
        $this->assertSame('The parallel worker stopped before returning a result.', $calls[0][2]->getMessage());
        $this->assertSame([$sibling], array_map(static fn (DispatchEnvelopeMessage $message) => $message->envelope, $channel->sent));
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

    public function testWaitReturnsOnceItsTimeoutElapses()
    {
        $envelope = new Envelope(new DummyMessage('Hello'));
        $channel = $this->createFakeChannel();
        $strategy = $this->createStrategy($channel);
        $calls = [];

        $strategy->execute($envelope, 'async', $this->createOnHandled($calls));
        $lateResponse = EventLoop::delay(2, static fn () => $channel->push(new HandledEnvelopeMessage(1, $envelope, null)));

        try {
            $this->assertFalse($strategy->wait($this->createOnHandled($calls), 0.2));
        } finally {
            EventLoop::cancel($lateResponse);
        }

        $this->assertSame([], $calls);
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

    public function testFlushSkipsTheWorkersThatAreInsideAHandler()
    {
        $first = new Envelope(new DummyMessage('A'));
        $second = new Envelope(new SecondMessage());
        // the first worker runs a handler and does not answer: it is not reading its channel either, so it only answers once a flush reaches it
        $busy = $this->createFakeChannel([], [new HandledEnvelopeMessage(1, $first, null)]);
        $batching = $this->createFakeChannel([new DeferredEnvelopeMessage(2)], [new HandledEnvelopeMessage(2, $second, null)]);
        $strategy = $this->createStrategy([$busy, $batching], 2);
        $this->setPrivateProperty($strategy, 'keyModes', ['message.bus||'.SecondMessage::class => 2]);
        $calls = [];

        $strategy->execute($first, 'async', $this->createOnHandled($calls));
        $strategy->execute($second, 'async', $this->createOnHandled($calls));
        $this->assertCount(1, $busy->sent);
        $this->assertCount(1, $batching->sent);

        $this->assertTrue($strategy->flush($this->createOnHandled($calls), 30.0));

        $this->assertCount(1, $busy->sent);
        $this->assertCount(2, $batching->sent);
        $this->assertInstanceOf(FlushBatchHandlersMessage::class, $batching->sent[1]);
        $this->assertSame(30.0, $batching->sent[1]->force);
        $this->assertCount(1, $calls);
        $this->assertSame($second->getMessage(), $calls[0][0]->getMessage());

        $this->assertTrue($strategy->flush($this->createOnHandled($calls), true));

        $this->assertCount(2, $busy->sent);
        $this->assertInstanceOf(FlushBatchHandlersMessage::class, $busy->sent[1]);
        $this->assertTrue($busy->sent[1]->force);
        $this->assertCount(2, $calls);
        $this->assertFalse($strategy->shouldPauseConsumption());
    }

    public function testFlushSkipsTheWorkersThatDidNotAnswerThePreviousFlush()
    {
        $envelope = new Envelope(new DummyMessage('Hello'));
        $channel = $this->createFakeChannel([new DeferredEnvelopeMessage(1)]);
        $strategy = $this->createStrategy($channel);
        $calls = [];

        $strategy->execute($envelope, 'async', $this->createOnHandled($calls));
        $this->assertFalse($strategy->flush($this->createOnHandled($calls)));
        $this->assertFalse($strategy->flush($this->createOnHandled($calls), 30.0));

        $this->assertCount(2, $channel->sent);
        $this->assertFalse($channel->sent[1]->force);

        $channel->push(new FlushBatchHandlersMessage(false));
        $this->assertFalse($strategy->flush($this->createOnHandled($calls), 30.0));

        $this->assertCount(3, $channel->sent);
        $this->assertSame(30.0, $channel->sent[2]->force);

        $this->setPrivateProperty($channel, 'flushResponses', [new HandledEnvelopeMessage(1, $envelope, null)]);
        $this->assertTrue($strategy->flush($this->createOnHandled($calls), true));

        $this->assertCount(4, $channel->sent);
        $this->assertTrue($channel->sent[3]->force);
        $this->assertCount(1, $calls);
        $this->assertFalse($strategy->shouldPauseConsumption());
    }

    public function testMessagesAvoidTheWorkersThatDidNotAnswerAFlush()
    {
        $flushing = $this->createFakeChannel([new DeferredEnvelopeMessage(1)]);
        $idle = $this->createFakeChannel();
        $strategy = $this->createStrategy([$flushing, $idle], 2);
        $this->setPrivateProperty($strategy, 'keyModes', ['message.bus||'.SecondMessage::class => 2, 'message.bus||'.DummyMessage::class => 1]);
        $calls = [];

        $strategy->execute(new Envelope(new SecondMessage()), 'async', $this->createOnHandled($calls));
        $this->assertFalse($strategy->flush($this->createOnHandled($calls)));
        $this->assertCount(2, $flushing->sent);

        $strategy->execute($envelope = new Envelope(new DummyMessage('Hello')), 'async', $this->createOnHandled($calls));

        $this->assertCount(2, $flushing->sent);
        $this->assertSame([$envelope], array_map(static fn (DispatchEnvelopeMessage $message) => $message->envelope, $idle->sent));
        $this->assertSame([], $calls);
    }

    public function testMessagesOfABatchingKeyWaitWhileTheirWorkerIsBusy()
    {
        $channel = $this->createFakeChannel();
        $idle = $this->createFakeChannel();
        $strategy = $this->createStrategy([$channel, $idle], 2);
        $this->setPrivateProperty($strategy, 'keyModes', ['message.bus||'.SecondMessage::class => 2]);
        $this->setPrivateProperty($strategy, 'batchingKeyAffinities', ['message.bus||'.SecondMessage::class => spl_object_id($channel)]);
        $calls = [];

        $strategy->execute($first = new Envelope(new SecondMessage()), 'async', $this->createOnHandled($calls));
        $strategy->execute($second = new Envelope(new SecondMessage()), 'async', $this->createOnHandled($calls));

        $this->assertCount(1, $channel->sent);
        $this->assertTrue($strategy->shouldPauseConsumption());

        $channel->push(new DeferredEnvelopeMessage(1));
        $this->assertTrue($strategy->wait($this->createOnHandled($calls)));

        $this->assertSame([$first, $second], array_map(static fn (DispatchEnvelopeMessage $message) => $message->envelope, $channel->sent));
        $this->assertFalse($strategy->shouldPauseConsumption());

        $strategy->execute($third = new Envelope(new SecondMessage()), 'async', $this->createOnHandled($calls));
        $this->assertCount(2, $channel->sent);

        $this->setPrivateProperty($channel, 'flushResponses', [new HandledEnvelopeMessage(1, $first, null), new HandledEnvelopeMessage(2, $second, null), new HandledEnvelopeMessage(3, $third, null)]);
        $this->assertTrue($strategy->flush($this->createOnHandled($calls), true));

        $this->assertSame([$first->getMessage(), $second->getMessage(), $third->getMessage()], array_map(static fn (array $call) => $call[0]->getMessage(), $calls));
        $this->assertSame([], $idle->sent);
        $this->assertFalse($strategy->shouldPauseConsumption());
    }

    public function testTheMessagesThatWaitForAFailedWorkerGoToAnotherWorker()
    {
        $failing = $this->createFakeChannel();
        $channel = $this->createFakeChannel();
        $strategy = $this->createStrategy([$failing, $channel]);
        $this->setPrivateProperty($strategy, 'keyModes', ['message.bus||'.SecondMessage::class => 2]);
        $this->setPrivateProperty($strategy, 'batchingKeyAffinities', ['message.bus||'.SecondMessage::class => spl_object_id($failing)]);
        $calls = [];

        $strategy->execute($first = new Envelope(new SecondMessage()), 'async', $this->createOnHandled($calls));
        $strategy->execute($second = new Envelope(new SecondMessage()), 'async', $this->createOnHandled($calls));
        $this->assertCount(1, $failing->sent);

        $failing->push(new ChannelException('Channel source closed unexpectedly'));
        $this->assertTrue($strategy->wait($this->createOnHandled($calls)));

        $this->assertCount(1, $calls);
        $this->assertSame($first, $calls[0][0]);
        $this->assertSame('The parallel worker stopped before returning a result.', $calls[0][2]->getMessage());
        $this->assertSame([$second], array_map(static fn (DispatchEnvelopeMessage $message) => $message->envelope, $channel->sent));
    }

    public function testTheMessagesThatWaitForAnExitingWorkerGoToAnotherWorkerAfterTheOnesItDidNotRead()
    {
        $exiting = $this->createFakeChannel();
        $channel = $this->createFakeChannel();
        $strategy = $this->createStrategy([$exiting, $channel]);
        $this->setPrivateProperty($strategy, 'keyModes', ['message.bus||'.SecondMessage::class => 2]);
        $this->setPrivateProperty($strategy, 'batchingKeyAffinities', ['message.bus||'.SecondMessage::class => spl_object_id($exiting)]);
        $calls = [];

        $strategy->execute($first = new Envelope(new SecondMessage()), 'async', $this->createOnHandled($calls));
        $strategy->execute($second = new Envelope(new SecondMessage()), 'async', $this->createOnHandled($calls));
        $strategy->execute($third = new Envelope(new SecondMessage()), 'async', $this->createOnHandled($calls));
        $this->assertCount(1, $exiting->sent);

        $exiting->push(new HandledEnvelopeMessage(1, $first, null));
        $this->assertTrue($strategy->wait($this->createOnHandled($calls)));
        $this->assertSame([$first, $second], array_map(static fn (DispatchEnvelopeMessage $message) => $message->envelope, $exiting->sent));

        $exiting->push(null);
        $this->assertFalse($strategy->wait($this->createOnHandled($calls), 0.2));
        $this->assertCount(1, $calls);
        $this->assertSame([$second], array_map(static fn (DispatchEnvelopeMessage $message) => $message->envelope, $channel->sent));

        $channel->push(new DeferredEnvelopeMessage($channel->sent[0]->requestId));
        $this->assertTrue($strategy->wait($this->createOnHandled($calls)));
        $this->assertSame([$second, $third], array_map(static fn (DispatchEnvelopeMessage $message) => $message->envelope, $channel->sent));
    }

    public function testAForcedFlushCompletesTheMessagesThatAnExitingWorkerDidNotRead()
    {
        $first = new Envelope(new DummyMessage('A'));
        $second = new Envelope(new DummyMessage('B'));
        $exiting = $this->createFakeChannel([], [new HandledEnvelopeMessage(1, $first, null), null]);
        $channel = $this->createFakeChannel([new DeferredEnvelopeMessage(3)], [new HandledEnvelopeMessage(3, $second, null)]);
        $strategy = $this->createStrategy([$exiting, $channel]);
        $this->setPrivateProperty($strategy, 'keyModes', ['message.bus||'.DummyMessage::class => 2]);
        $this->setPrivateProperty($strategy, 'batchingKeyAffinities', ['message.bus||'.DummyMessage::class => spl_object_id($exiting)]);
        $calls = [];

        $strategy->execute($first, 'async', $this->createOnHandled($calls));
        $strategy->execute($second, 'async', $this->createOnHandled($calls));
        $stuck = EventLoop::delay(2, static fn () => $channel->push(new ChannelException('The forced flush never reached this worker.')));

        try {
            $this->assertTrue($strategy->flush($this->createOnHandled($calls), true));
        } finally {
            EventLoop::cancel($stuck);
        }

        $this->assertSame([[$first->getMessage(), null], [$second->getMessage(), null]], array_map(static fn (array $call) => [$call[0]->getMessage(), $call[2]], $calls));
        $this->assertInstanceOf(FlushBatchHandlersMessage::class, $channel->sent[1]);
        $this->assertTrue($channel->sent[1]->force);
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

    public function testTheMessagesThatAnExitingWorkerDidNotReadAreSentToAnotherWorker()
    {
        $exitingChannel = $this->createFakeChannel([null]);
        $channel = $this->createFakeChannel();
        $strategy = $this->createStrategy([$exitingChannel, $channel]);
        $calls = [];
        $envelope = new Envelope(new DummyMessage('Hello'));

        $strategy->execute($envelope, 'async', $this->createOnHandled($calls));

        $this->assertSame([], $calls);
        $this->assertCount(1, $channel->sent);
        $this->assertSame($envelope, $channel->sent[0]->envelope);

        $channel->push(new HandledEnvelopeMessage($channel->sent[0]->requestId, $envelope, null));

        $this->assertTrue($strategy->wait($this->createOnHandled($calls)));
        $this->assertCount(1, $calls);
        $this->assertSame($envelope->getMessage(), $calls[0][0]->getMessage());
        $this->assertNull($calls[0][2]);
        $this->assertFalse($strategy->shouldPauseConsumption());
    }

    public function testAWorkerWhoseTaskFailedIsReplacedOnceItsRequestsAreSettled()
    {
        $failedChannel = $this->createFakeChannel([new DeferredEnvelopeMessage(1)]);
        $channel = $this->createFakeChannel();
        $strategy = $this->createStrategy([$failedChannel, $channel]);
        $calls = [];

        $strategy->execute(new Envelope(new DummyMessage('A')), 'async', $this->createOnHandled($calls));
        $strategy->execute($envelopeB = new Envelope(new DummyMessage('B')), 'async', $this->createOnHandled($calls));
        $failedChannel->push(new HandledEnvelopeMessage(2, $envelopeB, null));
        $failedChannel->push(new ChannelException('Channel source closed unexpectedly'));
        $this->setPrivateProperty($strategy, 'taskFailures', [spl_object_id($failedChannel) => new \RuntimeException('Reset failed.')]);

        $strategy->execute($envelopeC = new Envelope(new DummyMessage('C')), 'async', $this->createOnHandled($calls));

        $this->assertSame(['B', 'A'], array_map(static fn (array $call) => $call[0]->getMessage()->getMessage(), $calls));
        $this->assertNull($calls[0][2]);
        $this->assertSame('The parallel worker stopped before returning a result.', $calls[1][2]->getMessage());
        $this->assertSame('Reset failed.', $calls[1][2]->getPrevious()->getMessage());
        $this->assertSame([$envelopeC], array_map(static fn (DispatchEnvelopeMessage $message) => $message->envelope, $channel->sent));
    }

    public function testTheMessagesThatAWorkerWhoseTaskFailedDidNotReadAreSentToAnotherWorker()
    {
        $stoppedChannel = $this->createFakeChannel();
        $crashedChannel = $this->createFakeChannel();
        $channel = $this->createFakeChannel();
        $strategy = $this->createStrategy([$stoppedChannel, $crashedChannel, $channel]);
        $calls = [];

        $strategy->execute($envelopeA = new Envelope(new DummyMessage('A')), 'async', $this->createOnHandled($calls));
        $strategy->execute($envelopeC = new Envelope(new DummyMessage('C')), 'async', $this->createOnHandled($calls));
        $stoppedChannel->push(null);
        $crashedChannel->push(new ChannelException('Channel source closed unexpectedly'));
        $this->setPrivateProperty($strategy, 'taskFailures', [spl_object_id($stoppedChannel) => new \RuntimeException('Reset failed.'), spl_object_id($crashedChannel) => new \RuntimeException('Crashed.')]);

        $strategy->execute($envelopeB = new Envelope(new DummyMessage('B')), 'async', $this->createOnHandled($calls));

        // the message that the stopped worker did not read probes again on another worker, and the messages of its key wait for it: the crashed worker had nothing left to settle
        $this->assertSame([], $calls);
        $this->assertSame([$envelopeA], array_map(static fn (DispatchEnvelopeMessage $message) => $message->envelope, $channel->sent));

        $channel->push(new HandledEnvelopeMessage(2, $envelopeA, null));
        $this->assertTrue($strategy->wait($this->createOnHandled($calls)));

        $this->assertSame(['A'], array_map(static fn (array $call) => $call[0]->getMessage()->getMessage(), $calls));
        $this->assertSame([$envelopeA, $envelopeC, $envelopeB], array_map(static fn (DispatchEnvelopeMessage $message) => $message->envelope, $channel->sent));
    }

    public function testAFailureToSettleAnotherMessageIsNotChargedToTheDispatchedOne()
    {
        $busyChannel = $this->createFakeChannel();
        $idleChannel = $this->createFakeChannel();
        $strategy = $this->createStrategy([$busyChannel, $idleChannel], 2);
        $this->setPrivateProperty($strategy, 'keyModes', ['message.bus||'.DummyMessage::class => 1]);
        $calls = [];
        $onHandled = static function (Envelope $envelope, string $transportName, bool &$acked, ?\Throwable $error = null) use (&$calls) {
            $calls[] = [$envelope->getMessage()->getMessage(), $error?->getMessage()];

            if ('A' === $envelope->getMessage()->getMessage() && null === $error) {
                throw new TransportException('Could not acknowledge the message.');
            }
        };

        $envelopeA = new Envelope(new DummyMessage('A'));
        $strategy->execute($envelopeA, 'async', $onHandled);
        $busyChannel->push(new HandledEnvelopeMessage(1, $envelopeA, null));

        $envelopeB = new Envelope(new DummyMessage('B'));

        try {
            $strategy->execute($envelopeB, 'async', $onHandled);
            $this->fail('The failure to settle A must propagate.');
        } catch (TransportException $e) {
            $this->assertSame('Could not acknowledge the message.', $e->getMessage());
        }

        $this->assertSame([['A', null]], $calls);
        $this->assertSame($envelopeB, $idleChannel->sent[0]->envelope);

        $idleChannel->push(new HandledEnvelopeMessage(2, $envelopeB, null));
        $this->assertTrue($strategy->wait($onHandled));
        $this->assertSame([['A', null], ['B', null]], $calls);
    }

    public function testAFailureToSettleTheRequestsOfADeadWorkerIsNotChargedToTheDispatchedOne()
    {
        $deadChannel = $this->createFakeChannel();
        $busyChannel = $this->createFakeChannel();
        $strategy = $this->createStrategy([$deadChannel, $busyChannel], 2);
        $this->setPrivateProperty($strategy, 'keyModes', ['message.bus||'.DummyMessage::class => 1]);
        $calls = [];
        $onHandled = static function (Envelope $envelope, string $transportName, bool &$acked, ?\Throwable $error = null) use (&$calls) {
            $calls[] = [$envelope->getMessage()->getMessage(), $error?->getMessage()];

            if ('A' === $envelope->getMessage()->getMessage()) {
                throw new TransportException('Could not reject the message.');
            }
        };

        $strategy->execute(new Envelope(new DummyMessage('A')), 'async', $onHandled);
        $strategy->execute(new Envelope(new DummyMessage('C')), 'async', $onHandled);
        $deadChannel->sendFailure = new ChannelException('The worker died.');

        try {
            $strategy->execute(new Envelope(new DummyMessage('B')), 'async', $onHandled);
            $this->fail('The failure to settle A must propagate.');
        } catch (TransportException $e) {
            $this->assertSame('Could not reject the message.', $e->getMessage());
        }

        $this->assertSame([['A', 'The parallel worker stopped before returning a result.']], $calls);
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
        $this->setPrivateProperty($strategy, 'keyModes', ['message.bus||'.SecondMessage::class => 1, 'message.bus||'.ChildDummyMessage::class => 1]);
        $calls = [];

        $strategy->execute(new Envelope(new DummyMessage('A')), 'async', $this->createOnHandled($calls));
        $this->assertFalse($strategy->shouldPauseConsumption());

        $strategy->execute(new Envelope(new SecondMessage()), 'async', $this->createOnHandled($calls));
        $this->assertTrue($strategy->shouldPauseConsumption());

        $channel->push(new DeferredEnvelopeMessage(3));
        $strategy->execute(new Envelope(new ChildDummyMessage('C')), 'async', $this->createOnHandled($calls));

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

    private function pushHandled(object $firstChannel, object $secondChannel, int $requestId, Envelope $envelope): void
    {
        foreach ([$firstChannel, $secondChannel] as $channel) {
            foreach ($channel->sent as $message) {
                if ($message instanceof DispatchEnvelopeMessage && $message->requestId === $requestId) {
                    $channel->push(new HandledEnvelopeMessage($requestId, $envelope, null));

                    return;
                }
            }
        }

        $this->fail(\sprintf('No channel was sent the request "%d".', $requestId));
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
        $channel = new class($responses, $flushResponses) implements Channel {
            public array $sent = [];
            public ?\Throwable $sendFailure = null;
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
                if ($this->sendFailure) {
                    throw $this->sendFailure;
                }

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
        $this->fakeChannels[] = \WeakReference::create($channel);

        return $channel;
    }

    private function setPrivateProperty(object $object, string $property, mixed $value)
    {
        $reflectionProperty = new \ReflectionProperty($object, $property);
        $reflectionProperty->setValue($object, $value);
    }
}
