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
use Symfony\Component\Messenger\Middleware\AddIdentityStampsMiddleware;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\PropagateStampsMiddleware;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Middleware\StackMiddleware;
use Symfony\Component\Messenger\Stamp\CausationStamp;
use Symfony\Component\Messenger\Stamp\CorrelationStamp;
use Symfony\Component\Messenger\Stamp\MessageIdStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\SecondMessage;
use Symfony\Component\Messenger\Tests\Fixtures\ThirdMessage;

class AddIdentityStampsMiddlewareTest extends MiddlewareTestCase
{
    public function testARootDispatchOpensAFlow()
    {
        $middleware = new AddIdentityStampsMiddleware();

        $envelope = $middleware->handle(new Envelope(new DummyMessage('Hey')), $this->getStackMock());

        $id = $envelope->last(MessageIdStamp::class)->getId();
        $this->assertNotSame('', $id);
        $this->assertSame($id, $envelope->last(CorrelationStamp::class)->getId());
        $this->assertNull($envelope->last(CausationStamp::class));
    }

    public function testANestedDispatchIsCausedByTheMessageBeingHandled()
    {
        $middleware = new AddIdentityStampsMiddleware();
        $parent = null;

        $child = $this->handleNested($middleware, new Envelope(new DummyMessage('Hey')), new Envelope(new SecondMessage()), $parent);

        $this->assertNotSame($parent->last(MessageIdStamp::class)->getId(), $child->last(MessageIdStamp::class)->getId());
        $this->assertSame($parent->last(MessageIdStamp::class)->getId(), $child->last(CausationStamp::class)->getId());
        $this->assertNull($child->last(CorrelationStamp::class));
    }

    public function testAGrandchildIsCausedByItsParent()
    {
        $middleware = new AddIdentityStampsMiddleware();
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
        $middleware = new AddIdentityStampsMiddleware();
        $id = new MessageIdStamp('the-message-id');

        $envelope = $middleware->handle(new Envelope(new DummyMessage('Hey'), [$id]), $this->getStackMock());

        $this->assertSame([$id], $envelope->all(MessageIdStamp::class));
        $this->assertSame('the-message-id', $envelope->last(CorrelationStamp::class)->getId());
    }

    public function testAnExistingCorrelationIsKept()
    {
        $middleware = new AddIdentityStampsMiddleware();
        $correlation = new CorrelationStamp('the-request-id');

        $envelope = $middleware->handle(new Envelope(new DummyMessage('Hey'), [$correlation]), $this->getStackMock());

        $this->assertSame([$correlation], $envelope->all(CorrelationStamp::class));
        $this->assertNotNull($envelope->last(MessageIdStamp::class));
    }

    public function testAnExistingCausationIsKept()
    {
        $middleware = new AddIdentityStampsMiddleware();
        $causation = new CausationStamp('the-causing-id');
        $parent = null;

        $child = $this->handleNested($middleware, new Envelope(new DummyMessage('Hey')), new Envelope(new SecondMessage(), [$causation]), $parent);

        $this->assertSame([$causation], $child->all(CausationStamp::class));
    }

    public function testAReceivedMessageKeepsItsIdentity()
    {
        $middleware = new AddIdentityStampsMiddleware();
        $id = new MessageIdStamp('the-message-id');
        $correlation = new CorrelationStamp('the-request-id');
        $causation = new CausationStamp('the-causing-id');

        $envelope = $middleware->handle(new Envelope(new DummyMessage('Hey'), [new ReceivedStamp('async'), $id, $correlation, $causation]), $this->getStackMock());

        $this->assertSame([$id], $envelope->all(MessageIdStamp::class));
        $this->assertSame([$correlation], $envelope->all(CorrelationStamp::class));
        $this->assertSame([$causation], $envelope->all(CausationStamp::class));
    }

    public function testTheStackUnwindsWhenHandlingThrows()
    {
        $middleware = new AddIdentityStampsMiddleware();

        try {
            $middleware->handle(new Envelope(new DummyMessage('Hey')), $this->getThrowingStackMock());
            $this->fail('The exception of the next middleware should bubble up.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Thrown from next middleware.', $e->getMessage());
        }

        $envelope = $middleware->handle(new Envelope(new SecondMessage()), $this->getStackMock());

        $this->assertNull($envelope->last(CausationStamp::class));
        $this->assertNotNull($envelope->last(CorrelationStamp::class));
    }

    public function testTheGivenGeneratorIsUsed()
    {
        $middleware = new AddIdentityStampsMiddleware(static fn (): string => 'the-generated-id');

        $envelope = $middleware->handle(new Envelope(new DummyMessage('Hey')), $this->getStackMock());

        $this->assertSame('the-generated-id', $envelope->last(MessageIdStamp::class)->getId());
    }

    public function testAGeneratorReturningAStringableIsCastToString()
    {
        $middleware = new AddIdentityStampsMiddleware(static fn (): \Stringable => new class implements \Stringable {
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
        $middleware = new AddIdentityStampsMiddleware();

        $first = $middleware->handle(new Envelope(new DummyMessage('Hey')), $this->getStackMock());
        $second = $middleware->handle(new Envelope(new SecondMessage()), $this->getStackMock());

        $this->assertNotSame($first->last(MessageIdStamp::class)->getId(), $second->last(MessageIdStamp::class)->getId());
    }

    public function testAFlowIsIdentifiedThroughARealBus()
    {
        $recorder = new IdentityRecordingMiddleware();
        $bus = new MessageBus([
            new AddIdentityStampsMiddleware(),
            new PropagateStampsMiddleware(),
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

    private function handleNested(AddIdentityStampsMiddleware $middleware, Envelope $parent, Envelope $child, ?Envelope &$handledParent): Envelope
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

class IdentityRecordingMiddleware implements MiddlewareInterface
{
    /** @var list<Envelope> */
    public array $envelopes = [];

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $this->envelopes[] = $envelope;

        return $stack->next()->handle($envelope, $stack);
    }
}
