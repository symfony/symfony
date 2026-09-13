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

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\PropagateStampsMiddleware;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Middleware\StackMiddleware;
use Symfony\Component\Messenger\Stamp\CorrelationStamp;
use Symfony\Component\Messenger\Stamp\PropagatedStampInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;
use Symfony\Component\Messenger\Tests\Fixtures\AnEnvelopeStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\SecondMessage;
use Symfony\Component\Messenger\Tests\Fixtures\ThirdMessage;

class PropagateStampsMiddlewareTest extends MiddlewareTestCase
{
    public function testNothingIsCopiedOnRootDispatches()
    {
        $middleware = new PropagateStampsMiddleware();
        $middleware->handle(new Envelope(new DummyMessage('Hey'), [new TraceStamp('abc')]), $this->getStackMock());

        $envelope = new Envelope(new SecondMessage());

        $this->assertSame($envelope, $middleware->handle($envelope, $this->getStackMock()));
    }

    public function testPropagatedStampsAreCopiedOnNestedDispatches()
    {
        $middleware = new PropagateStampsMiddleware();
        $trace = new TraceStamp('abc');

        $nested = $this->handleNested($middleware, new Envelope(new DummyMessage('Hey'), [$trace, new AnEnvelopeStamp()]), new Envelope(new SecondMessage()));

        $this->assertSame([$trace], $nested->all(TraceStamp::class));
        $this->assertSame([], $nested->all(AnEnvelopeStamp::class));
    }

    public function testAllStampsOfAPropagatedClassAreCopiedInOrder()
    {
        $middleware = new PropagateStampsMiddleware();
        $first = new TraceStamp('first');
        $second = new TraceStamp('second');

        $nested = $this->handleNested($middleware, new Envelope(new DummyMessage('Hey'), [$first, $second]), new Envelope(new SecondMessage()));

        $this->assertSame([$first, $second], $nested->all(TraceStamp::class));
    }

    public function testAnExplicitStampOnTheNestedEnvelopeWins()
    {
        $middleware = new PropagateStampsMiddleware();
        $tenant = new TenantStamp('acme');
        $childTrace = new TraceStamp('child');

        $nested = $this->handleNested($middleware, new Envelope(new DummyMessage('Hey'), [new TraceStamp('parent'), $tenant]), new Envelope(new SecondMessage(), [$childTrace]));

        $this->assertSame([$childTrace], $nested->all(TraceStamp::class));
        $this->assertSame([$tenant], $nested->all(TenantStamp::class));
    }

    public function testAReceivedEnvelopePropagatesToNestedDispatches()
    {
        $middleware = new PropagateStampsMiddleware();
        $trace = new TraceStamp('abc');

        $nested = $this->handleNested($middleware, new Envelope(new DummyMessage('Hey'), [new ReceivedStamp('async'), $trace]), new Envelope(new SecondMessage()));

        $this->assertSame([$trace], $nested->all(TraceStamp::class));
    }

    public function testAReceivedEnvelopeIsNeverEnriched()
    {
        $middleware = new PropagateStampsMiddleware();
        $received = new Envelope(new SecondMessage(), [new ReceivedStamp('sync')]);

        $this->assertSame($received, $this->handleNested($middleware, new Envelope(new DummyMessage('Hey'), [new TraceStamp('abc')]), $received));
    }

    public function testStampsPropagateAcrossSeveralLevels()
    {
        $middleware = new PropagateStampsMiddleware();
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

    public function testTheStackUnwindsWhenHandlingThrows()
    {
        $middleware = new PropagateStampsMiddleware();

        try {
            $middleware->handle(new Envelope(new DummyMessage('Hey'), [new TraceStamp('abc')]), $this->getThrowingStackMock());
            $this->fail('The exception of the next middleware should bubble up.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Thrown from next middleware.', $e->getMessage());
        }

        $envelope = new Envelope(new SecondMessage());

        $this->assertSame($envelope, $middleware->handle($envelope, $this->getStackMock()));
    }

    public function testStampsPropagateThroughARealBus()
    {
        $trace = new TraceStamp('abc');
        $recorder = new EnvelopeRecordingMiddleware();
        $bus = new MessageBus([
            new PropagateStampsMiddleware(),
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
        $middleware = new PropagateStampsMiddleware();
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

        $eventBus = new MessageBus([new PropagateStampsMiddleware(), $recorder]);
        $commandBus = new MessageBus([new PropagateStampsMiddleware(), new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [static function () use ($eventBus) { $eventBus->dispatch(new SecondMessage()); }],
        ]))]);

        $commandBus->dispatch(new DummyMessage('Hey'), [new TraceStamp('abc')]);

        $this->assertCount(1, $recorder->envelopes);
        $this->assertSame([], $recorder->envelopes[0]->all(TraceStamp::class));
    }

    public function testTheCorrelationStampIsPropagated()
    {
        $middleware = new PropagateStampsMiddleware();
        $correlation = new CorrelationStamp('the-request-id');

        $nested = $this->handleNested($middleware, new Envelope(new DummyMessage('Hey'), [$correlation]), new Envelope(new SecondMessage()));

        $this->assertSame([$correlation], $nested->all(CorrelationStamp::class));
    }

    private function handleNested(PropagateStampsMiddleware $middleware, Envelope $parent, Envelope $child): Envelope
    {
        $nested = null;
        $middleware->handle($parent, $this->getNestingStackMock(function () use ($middleware, $child, &$nested) {
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
