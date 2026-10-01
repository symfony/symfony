<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Middleware;

use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\DelayedMessageHandlingException;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\DispatchAfterCurrentBusMiddleware;
use Symfony\Component\Messenger\Middleware\FlowContextMiddleware;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Middleware\StackMiddleware;
use Symfony\Component\Messenger\Stamp\CausationStamp;
use Symfony\Component\Messenger\Stamp\CorrelationStamp;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Component\Messenger\Stamp\MessageIdStamp;
use Symfony\Component\Messenger\Stamp\PropagatedStampInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;
use Symfony\Component\Messenger\Tests\Fixtures\AnEnvelopeStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\SecondMessage;
use Symfony\Component\Messenger\Tests\Fixtures\ThirdMessage;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Transport\Sync\SyncTransport;

class FlowContextMiddlewareTest extends MiddlewareTestCase
{
    public function testNothingIsCopiedOnRootDispatches()
    {
        $middleware = new FlowContextMiddleware();
        $middleware->handle(new Envelope(new DummyMessage('Hey'), [new TraceStamp('abc')]), $this->getStackMock());

        $envelope = new Envelope(new SecondMessage());

        $this->assertSame($envelope, $middleware->handle($envelope, $this->getStackMock()));
    }

    public function testPropagatedStampsAreCopiedOnNestedDispatches()
    {
        $middleware = new FlowContextMiddleware();
        $trace = new TraceStamp('abc');

        $nested = $this->handleNested($middleware, new Envelope(new DummyMessage('Hey'), [$trace, new AnEnvelopeStamp()]), new Envelope(new SecondMessage()));

        $this->assertSame([$trace], $nested->all(TraceStamp::class));
        $this->assertSame([], $nested->all(AnEnvelopeStamp::class));
    }

    public function testAllStampsOfAPropagatedClassAreCopiedInOrder()
    {
        $middleware = new FlowContextMiddleware();
        $first = new TraceStamp('first');
        $second = new TraceStamp('second');

        $nested = $this->handleNested($middleware, new Envelope(new DummyMessage('Hey'), [$first, $second]), new Envelope(new SecondMessage()));

        $this->assertSame([$first, $second], $nested->all(TraceStamp::class));
    }

    public function testAnExplicitStampOnTheNestedEnvelopeWins()
    {
        $middleware = new FlowContextMiddleware();
        $tenant = new TenantStamp('acme');
        $childTrace = new TraceStamp('child');

        $nested = $this->handleNested($middleware, new Envelope(new DummyMessage('Hey'), [new TraceStamp('parent'), $tenant]), new Envelope(new SecondMessage(), [$childTrace]));

        $this->assertSame([$childTrace], $nested->all(TraceStamp::class));
        $this->assertSame([$tenant], $nested->all(TenantStamp::class));
    }

    public function testAReceivedEnvelopePropagatesToNestedDispatches()
    {
        $middleware = new FlowContextMiddleware();
        $trace = new TraceStamp('abc');

        $nested = $this->handleNested($middleware, new Envelope(new DummyMessage('Hey'), [new ReceivedStamp('async'), $trace]), new Envelope(new SecondMessage()));

        $this->assertSame([$trace], $nested->all(TraceStamp::class));
    }

    public function testAReceivedEnvelopeIsNeverEnriched()
    {
        $middleware = new FlowContextMiddleware();
        $received = new Envelope(new SecondMessage(), [new ReceivedStamp('sync')]);

        $this->assertSame($received, $this->handleNested($middleware, new Envelope(new DummyMessage('Hey'), [new TraceStamp('abc')]), $received));
    }

    public function testStampsPropagateAcrossSeveralLevels()
    {
        $middleware = new FlowContextMiddleware();
        $trace = new TraceStamp('abc');
        $tenant = new TenantStamp('acme');
        $grandchild = null;

        $middleware->handle(new Envelope(new DummyMessage('Hey'), [$trace]), $this->getNestingStackMock(function () use ($middleware, $tenant, &$grandchild) {
            $middleware->handle(new Envelope(new SecondMessage(), [$tenant]), $this->getNestingStackMock(function () use ($middleware, &$grandchild) {
                $grandchild = $middleware->handle(new Envelope(new ThirdMessage()), $this->getStackMock());
            }));
        }));

        $this->assertSame([$trace], $grandchild->all(TraceStamp::class));
        $this->assertSame([$tenant], $grandchild->all(TenantStamp::class));
    }

    public function testStampsPropagateThroughARealBus()
    {
        $trace = new TraceStamp('abc');
        $recorder = new EnvelopeRecordingMiddleware();
        $bus = new MessageBus([
            new FlowContextMiddleware(),
            $recorder,
            new HandleMessageMiddleware(new HandlersLocator([
                DummyMessage::class => [static function () use (&$bus) { $bus->dispatch(new SecondMessage()); }],
                SecondMessage::class => [static function () {}],
            ])),
        ]);

        $bus->dispatch(new DummyMessage('Hey'), [$trace]);

        $this->assertCount(2, $recorder->envelopes);
        $this->assertInstanceOf(SecondMessage::class, $recorder->envelopes[1]->getMessage());
        $this->assertSame([$trace], $recorder->envelopes[1]->all(TraceStamp::class));
    }

    public function testAnInstanceSharedByTwoBusesPropagatesAcrossBuses()
    {
        $middleware = new FlowContextMiddleware();
        $trace = new TraceStamp('abc');
        $recorder = new EnvelopeRecordingMiddleware();

        $eventBus = new MessageBus([$middleware, $recorder]);
        $commandBus = new MessageBus([$middleware, new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [static function () use ($eventBus) { $eventBus->dispatch(new SecondMessage()); }],
        ]))]);

        $commandBus->dispatch(new DummyMessage('Hey'), [$trace]);

        $this->assertCount(1, $recorder->envelopes);
        $this->assertSame([$trace], $recorder->envelopes[0]->all(TraceStamp::class));
    }

    public function testSeparateInstancesDoNotPropagateAcrossBuses()
    {
        $recorder = new EnvelopeRecordingMiddleware();

        $eventBus = new MessageBus([new FlowContextMiddleware(), $recorder]);
        $commandBus = new MessageBus([new FlowContextMiddleware(), new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [static function () use ($eventBus) { $eventBus->dispatch(new SecondMessage()); }],
        ]))]);

        $commandBus->dispatch(new DummyMessage('Hey'), [new TraceStamp('abc')]);

        $this->assertCount(1, $recorder->envelopes);
        $this->assertSame([], $recorder->envelopes[0]->all(TraceStamp::class));
    }

    public function testTheCorrelationStampIsPropagated()
    {
        $middleware = new FlowContextMiddleware();
        $correlation = new CorrelationStamp('the-request-id');

        $nested = $this->handleNested($middleware, new Envelope(new DummyMessage('Hey'), [$correlation]), new Envelope(new SecondMessage()));

        $this->assertSame([$correlation], $nested->all(CorrelationStamp::class));
    }

    public function testIdentityStampsAreOptIn()
    {
        $middleware = new FlowContextMiddleware(false, static fn (): string => 'the-generated-id');

        $child = $this->handleNested($middleware, new Envelope(new DummyMessage('Hey')), new Envelope(new SecondMessage()), $parent);

        $this->assertSame([], $parent->all());
        $this->assertSame([], $child->all());
    }

    public function testARootDispatchOpensAFlow()
    {
        $middleware = new FlowContextMiddleware(true);

        $envelope = $middleware->handle(new Envelope(new DummyMessage('Hey')), $this->getStackMock());

        $id = $envelope->last(MessageIdStamp::class)->getId();
        $this->assertNotSame('', $id);
        $this->assertSame($id, $envelope->last(CorrelationStamp::class)->getId());
        $this->assertNull($envelope->last(CausationStamp::class));
    }

    public function testANestedDispatchIsCausedByTheMessageBeingHandledAndInheritsItsCorrelation()
    {
        $middleware = new FlowContextMiddleware(true);

        $child = $this->handleNested($middleware, new Envelope(new DummyMessage('Hey')), new Envelope(new SecondMessage()), $parent);

        $this->assertNotSame($parent->last(MessageIdStamp::class)->getId(), $child->last(MessageIdStamp::class)->getId());
        $this->assertSame($parent->last(MessageIdStamp::class)->getId(), $child->last(CausationStamp::class)->getId());
        $this->assertSame([$parent->last(CorrelationStamp::class)], $child->all(CorrelationStamp::class));
    }

    public function testAGrandchildIsCausedByItsParent()
    {
        $middleware = new FlowContextMiddleware(true);
        $child = null;
        $grandchild = null;

        $middleware->handle(new Envelope(new DummyMessage('Hey')), $this->getNestingStackMock(function () use ($middleware, &$child, &$grandchild) {
            $child = $middleware->handle(new Envelope(new SecondMessage()), $this->getNestingStackMock(function () use ($middleware, &$grandchild) {
                $grandchild = $middleware->handle(new Envelope(new ThirdMessage()), $this->getStackMock());
            }));
        }));

        $this->assertSame($child->last(MessageIdStamp::class)->getId(), $grandchild->last(CausationStamp::class)->getId());
    }

    public function testAnExistingMessageIdIsKept()
    {
        $middleware = new FlowContextMiddleware(true);
        $id = new MessageIdStamp('the-message-id');

        $envelope = $middleware->handle(new Envelope(new DummyMessage('Hey'), [$id]), $this->getStackMock());

        $this->assertSame([$id], $envelope->all(MessageIdStamp::class));
        $this->assertSame('the-message-id', $envelope->last(CorrelationStamp::class)->getId());
    }

    public function testAnExistingCorrelationIsKept()
    {
        $middleware = new FlowContextMiddleware(true);
        $correlation = new CorrelationStamp('the-request-id');

        $envelope = $middleware->handle(new Envelope(new DummyMessage('Hey'), [$correlation]), $this->getStackMock());

        $this->assertSame([$correlation], $envelope->all(CorrelationStamp::class));
        $this->assertNotNull($envelope->last(MessageIdStamp::class));
    }

    public function testAnExistingCausationIsKept()
    {
        $middleware = new FlowContextMiddleware(true);
        $causation = new CausationStamp('the-causing-id');

        $child = $this->handleNested($middleware, new Envelope(new DummyMessage('Hey')), new Envelope(new SecondMessage(), [$causation]));

        $this->assertSame([$causation], $child->all(CausationStamp::class));
    }

    public function testAReceivedMessageKeepsItsIdentity()
    {
        $middleware = new FlowContextMiddleware(true);
        $id = new MessageIdStamp('the-message-id');
        $correlation = new CorrelationStamp('the-request-id');
        $causation = new CausationStamp('the-causing-id');

        $envelope = $middleware->handle(new Envelope(new DummyMessage('Hey'), [new ReceivedStamp('async'), $id, $correlation, $causation]), $this->getStackMock());

        $this->assertSame([$id], $envelope->all(MessageIdStamp::class));
        $this->assertSame([$correlation], $envelope->all(CorrelationStamp::class));
        $this->assertSame([$causation], $envelope->all(CausationStamp::class));
    }

    public function testAMessageDispatchedAgainWhileItIsHandledIsNotItsOwnCause()
    {
        $middleware = new FlowContextMiddleware(true);
        $copy = new Envelope(new DummyMessage('Hey'), [new MessageIdStamp('the-message-id'), new CorrelationStamp('the-message-id'), new ReceivedStamp('sync')]);

        $copy = $this->handleNested($middleware, new Envelope(new DummyMessage('Hey'), [new MessageIdStamp('the-message-id')]), $copy);

        $this->assertNull($copy->last(CausationStamp::class));
        $this->assertSame('the-message-id', $copy->last(MessageIdStamp::class)->getId());
    }

    public function testAMessageHandledThroughTheSyncTransportIsNotItsOwnCause()
    {
        $recorder = new EnvelopeRecordingMiddleware();
        $syncTransport = new SyncTransport(new class($bus) implements MessageBusInterface {
            public function __construct(private ?MessageBusInterface &$bus)
            {
            }

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                return $this->bus->dispatch($message, $stamps);
            }
        });
        $bus = new MessageBus([
            new FlowContextMiddleware(true),
            $recorder,
            new SendMessageMiddleware(new SendersLocator([DummyMessage::class => ['sync']], new ServiceLocator(['sync' => static fn () => $syncTransport]))),
            new HandleMessageMiddleware(new HandlersLocator([
                DummyMessage::class => [static function () use (&$bus) { $bus->dispatch(new SecondMessage()); }],
                SecondMessage::class => [static function () {}],
            ])),
        ]);

        $bus->dispatch(new DummyMessage('Hey'));

        $this->assertCount(3, $recorder->envelopes);
        [$sent, $handled, $child] = $recorder->envelopes;

        $id = $sent->last(MessageIdStamp::class)->getId();
        $this->assertNotNull($handled->last(ReceivedStamp::class));
        $this->assertSame($id, $handled->last(MessageIdStamp::class)->getId());
        $this->assertNull($handled->last(CausationStamp::class));
        $this->assertSame($id, $child->last(CausationStamp::class)->getId());
        $this->assertSame($id, $child->last(CorrelationStamp::class)->getId());
    }

    public function testTheGivenGeneratorIsUsed()
    {
        $middleware = new FlowContextMiddleware(true, static fn (): string => 'the-generated-id');

        $envelope = $middleware->handle(new Envelope(new DummyMessage('Hey')), $this->getStackMock());

        $this->assertSame('the-generated-id', $envelope->last(MessageIdStamp::class)->getId());
    }

    public function testAGeneratorReturningAStringableIsCastToString()
    {
        $middleware = new FlowContextMiddleware(true, static fn (): \Stringable => new class implements \Stringable {
            public function __toString(): string
            {
                return 'the-generated-id';
            }
        });

        $envelope = $middleware->handle(new Envelope(new DummyMessage('Hey')), $this->getStackMock());

        $this->assertSame('the-generated-id', $envelope->last(MessageIdStamp::class)->getId());
    }

    public function testTheDefaultGeneratorProducesDistinctIds()
    {
        $middleware = new FlowContextMiddleware(true);

        $first = $middleware->handle(new Envelope(new DummyMessage('Hey')), $this->getStackMock());
        $second = $middleware->handle(new Envelope(new SecondMessage()), $this->getStackMock());

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $first->last(MessageIdStamp::class)->getId());
        $this->assertNotSame($first->last(MessageIdStamp::class)->getId(), $second->last(MessageIdStamp::class)->getId());
    }

    public function testAFlowIsIdentifiedThroughARealBus()
    {
        $recorder = new EnvelopeRecordingMiddleware();
        $bus = new MessageBus([
            new FlowContextMiddleware(true),
            $recorder,
            new HandleMessageMiddleware(new HandlersLocator([
                DummyMessage::class => [static function () use (&$bus) { $bus->dispatch(new SecondMessage()); }],
                SecondMessage::class => [static function () use (&$bus) { $bus->dispatch(new ThirdMessage()); }],
                ThirdMessage::class => [static function () {}],
            ])),
        ]);

        $bus->dispatch(new DummyMessage('Hey'));

        $this->assertCount(3, $recorder->envelopes);
        [$root, $child, $grandchild] = $recorder->envelopes;

        $correlation = $root->last(CorrelationStamp::class)->getId();
        $this->assertSame($root->last(MessageIdStamp::class)->getId(), $correlation);
        $this->assertSame($correlation, $child->last(CorrelationStamp::class)->getId());
        $this->assertSame($correlation, $grandchild->last(CorrelationStamp::class)->getId());

        $this->assertNull($root->last(CausationStamp::class));
        $this->assertSame($root->last(MessageIdStamp::class)->getId(), $child->last(CausationStamp::class)->getId());
        $this->assertSame($child->last(MessageIdStamp::class)->getId(), $grandchild->last(CausationStamp::class)->getId());

        $ids = array_map(static fn (Envelope $envelope): string => $envelope->last(MessageIdStamp::class)->getId(), $recorder->envelopes);
        $this->assertSame($ids, array_unique($ids));
    }

    public function testTheStackUnwindsWhenHandlingThrows()
    {
        $middleware = new FlowContextMiddleware(true);

        try {
            $middleware->handle(new Envelope(new DummyMessage('Hey'), [new TraceStamp('abc')]), $this->getThrowingStackMock());
            $this->fail('The exception of the next middleware should bubble up.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Thrown from next middleware.', $e->getMessage());
        }

        $envelope = $middleware->handle(new Envelope(new SecondMessage()), $this->getStackMock());

        $this->assertNull($envelope->last(CausationStamp::class));
        $this->assertSame($envelope->last(MessageIdStamp::class)->getId(), $envelope->last(CorrelationStamp::class)->getId());
        $this->assertSame([], $envelope->all(TraceStamp::class));
    }

    public function testAMessageHandledAfterTheCurrentBusIsTheContextOfWhatItsHandlerDispatches()
    {
        $recorder = new EnvelopeRecordingMiddleware();
        $bus = $this->createDelayingBus($recorder, static function (DummyMessage $message) use (&$bus) {
            match ($message->getMessage()) {
                'root' => $bus->dispatch(new DummyMessage('delayed'), [new DispatchAfterCurrentBusStamp(), new TraceStamp('delayed')]),
                'delayed' => $bus->dispatch(new DummyMessage('child')),
                'child' => null,
            };
        });
        $tenant = new TenantStamp('acme');

        $bus->dispatch(new DummyMessage('root'), [new TraceStamp('root'), $tenant]);

        $this->assertSame(['root', 'delayed', 'child'], $this->getHandledMessages($recorder));
        [$root, $delayed, $child] = $recorder->envelopes;

        $this->assertSame($root->last(MessageIdStamp::class)->getId(), $delayed->last(CausationStamp::class)->getId());
        $this->assertSame($delayed->last(MessageIdStamp::class)->getId(), $child->last(CausationStamp::class)->getId());
        $this->assertSame($root->last(CorrelationStamp::class)->getId(), $delayed->last(CorrelationStamp::class)->getId());
        $this->assertSame($root->last(CorrelationStamp::class)->getId(), $child->last(CorrelationStamp::class)->getId());

        $this->assertSame(['delayed'], $this->getTraceIds($delayed));
        $this->assertSame([$tenant], $delayed->all(TenantStamp::class));
        $this->assertSame(['delayed'], $this->getTraceIds($child));
        $this->assertSame([$tenant], $child->all(TenantStamp::class));
    }

    public function testAMessageHandledAfterTheCurrentBusIsTheContextOfTheMessageItDelays()
    {
        $recorder = new EnvelopeRecordingMiddleware();
        $bus = $this->createDelayingBus($recorder, static function (DummyMessage $message) use (&$bus) {
            match ($message->getMessage()) {
                'root' => $bus->dispatch(new DummyMessage('delayed'), [new DispatchAfterCurrentBusStamp(), new TraceStamp('delayed')]),
                'delayed' => $bus->dispatch(new DummyMessage('delayed again'), [new DispatchAfterCurrentBusStamp()]),
                'delayed again' => $bus->dispatch(new DummyMessage('child')),
                'child' => null,
            };
        });

        $bus->dispatch(new DummyMessage('root'), [new TraceStamp('root')]);

        $this->assertSame(['root', 'delayed', 'delayed again', 'child'], $this->getHandledMessages($recorder));
        [$root, $delayed, $delayedAgain, $child] = $recorder->envelopes;

        $this->assertNull($root->last(CausationStamp::class));
        $this->assertSame($root->last(MessageIdStamp::class)->getId(), $delayed->last(CausationStamp::class)->getId());
        $this->assertSame($delayed->last(MessageIdStamp::class)->getId(), $delayedAgain->last(CausationStamp::class)->getId());
        $this->assertSame($delayedAgain->last(MessageIdStamp::class)->getId(), $child->last(CausationStamp::class)->getId());

        $this->assertSame(['delayed'], $this->getTraceIds($delayedAgain));
        $this->assertSame(['delayed'], $this->getTraceIds($child));
    }

    public function testTheStackUnwindsWhenAMessageHandledAfterTheCurrentBusFails()
    {
        $recorder = new EnvelopeRecordingMiddleware();
        $bus = $this->createDelayingBus($recorder, static function (DummyMessage $message) use (&$bus) {
            match ($message->getMessage()) {
                'root' => [
                    $bus->dispatch(new DummyMessage('failing'), [new DispatchAfterCurrentBusStamp(), new TraceStamp('failing')]),
                    $bus->dispatch(new DummyMessage('delayed'), [new DispatchAfterCurrentBusStamp(), new TraceStamp('delayed')]),
                ],
                'failing' => throw new \RuntimeException('Failing delayed handler.'),
                'delayed' => $bus->dispatch(new DummyMessage('child')),
                'child', 'next root' => null,
            };
        });

        try {
            $bus->dispatch(new DummyMessage('root'), [new TraceStamp('root')]);
            $this->fail('The failure of the delayed handler should bubble up.');
        } catch (DelayedMessageHandlingException $e) {
            $this->assertSame('Failing delayed handler.', current($e->getWrappedExceptions(\RuntimeException::class, true))->getMessage());
        }

        $bus->dispatch(new DummyMessage('next root'));

        $this->assertSame(['root', 'failing', 'delayed', 'child', 'next root'], $this->getHandledMessages($recorder));
        [, , $delayed, $child, $nextRoot] = $recorder->envelopes;

        $this->assertSame($delayed->last(MessageIdStamp::class)->getId(), $child->last(CausationStamp::class)->getId());
        $this->assertSame(['delayed'], $this->getTraceIds($child));
        $this->assertNull($nextRoot->last(CausationStamp::class));
        $this->assertSame($nextRoot->last(MessageIdStamp::class)->getId(), $nextRoot->last(CorrelationStamp::class)->getId());
        $this->assertSame([], $nextRoot->all(TraceStamp::class));
    }

    private function createDelayingBus(EnvelopeRecordingMiddleware $recorder, \Closure $handler): MessageBus
    {
        return new MessageBus([
            new FlowContextMiddleware(true),
            new DispatchAfterCurrentBusMiddleware(),
            $recorder,
            new HandleMessageMiddleware(new HandlersLocator([DummyMessage::class => [$handler]])),
        ]);
    }

    /**
     * @return list<string>
     */
    private function getHandledMessages(EnvelopeRecordingMiddleware $recorder): array
    {
        return array_map(static fn (Envelope $envelope): string => $envelope->getMessage()->getMessage(), $recorder->envelopes);
    }

    /**
     * @return list<string>
     */
    private function getTraceIds(Envelope $envelope): array
    {
        return array_map(static fn (TraceStamp $stamp): string => $stamp->id, $envelope->all(TraceStamp::class));
    }

    private function handleNested(FlowContextMiddleware $middleware, Envelope $parent, Envelope $child, ?Envelope &$handledParent = null): Envelope
    {
        $nested = null;
        $handledParent = $middleware->handle($parent, $this->getNestingStackMock(function () use ($middleware, $child, &$nested) {
            $nested = $middleware->handle($child, $this->getStackMock());
        }));

        return $nested;
    }

    private function getNestingStackMock(\Closure $nested): StackInterface
    {
        $next = $this->createMock(MiddlewareInterface::class);
        $next->expects($this->once())->method('handle')->willReturnCallback(static function (Envelope $envelope) use ($nested): Envelope {
            $nested();

            return $envelope;
        });

        return new StackMiddleware($next);
    }
}

class TraceStamp implements PropagatedStampInterface
{
    public function __construct(
        public readonly string $id,
    ) {
    }
}

class TenantStamp implements PropagatedStampInterface
{
    public function __construct(
        public readonly string $name,
    ) {
    }
}

class EnvelopeRecordingMiddleware implements MiddlewareInterface
{
    /** @var list<Envelope> */
    public array $envelopes = [];

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $this->envelopes[] = $envelope;

        return $stack->next()->handle($envelope, $stack);
    }
}
