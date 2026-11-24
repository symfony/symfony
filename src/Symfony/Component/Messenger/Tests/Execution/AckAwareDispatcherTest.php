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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Execution\AckAwareDispatcher;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\AckStamp;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\NoAutoAckStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

class AckAwareDispatcherTest extends TestCase
{
    public function testItReturnsTheEnvelopeOfADispatchWithoutBatchHandler()
    {
        $dispatcher = new AckAwareDispatcher(new MessageBus());
        $acknowledged = [];
        $acked = false;

        $result = $dispatcher->dispatch(new Envelope(new DummyMessage('a')), 'transport', static function (...$args) use (&$acknowledged) {
            $acknowledged[] = $args;
        }, $acked);

        $this->assertNull($result->error);
        $this->assertFalse($acked);
        $this->assertSame([], $acknowledged);
    }

    public function testItReturnsTheFailureOfTheBus()
    {
        $bus = new MessageBus([self::middleware(static function () {
            throw new \RuntimeException('boom');
        })]);

        $acked = false;
        $result = (new AckAwareDispatcher($bus))->dispatch(new Envelope(new DummyMessage('a')), 'transport', static function () {}, $acked);

        $this->assertInstanceOf(\RuntimeException::class, $result->error);
        $this->assertFalse($acked);
    }

    public function testTheAcksSentDuringADispatchAreHeldForTheFiberItRunsIn()
    {
        $descriptor = new HandlerDescriptor(static fn () => null, ['alias' => 'batch']);
        $ackStamps = [];
        $acknowledged = [];
        $onAcknowledge = static function (...$args) use (&$acknowledged) {
            $acknowledged[] = $args;
        };

        $bus = new MessageBus([self::middleware(static function (Envelope $envelope) use (&$ackStamps, $descriptor) {
            $message = $envelope->getMessage()->getMessage();

            if ('first' === $message) {
                // a batch handler keeps the message: it is acked later, by the dispatch of another message
                $ackStamps['first'] = $envelope->last(AckStamp::class);

                return $envelope->with(new NoAutoAckStamp($descriptor));
            }

            if ('second' === $message) {
                // the batch flushes during the dispatch of this message, then this dispatch waits for some IO
                $ackStamps['first']->ack($ackStamps['first-envelope']->with(HandledStamp::fromDescriptor($descriptor, 'result')), null);
                \Fiber::suspend();
            }

            return $envelope;
        })]);
        $dispatcher = new AckAwareDispatcher($bus);
        $acked1 = $acked2 = $acked3 = false;

        $first = new Envelope(new DummyMessage('first'));
        $ackStamps['first-envelope'] = $first;
        $fiber = new \Fiber(static fn () => $dispatcher->dispatch($first, 'transport', $onAcknowledge, $acked1));
        $fiber->start();
        $this->assertTrue($fiber->isTerminated());
        $this->assertSame([], $acknowledged);

        $second = new \Fiber(static fn () => $dispatcher->dispatch(new Envelope(new DummyMessage('second')), 'transport', $onAcknowledge, $acked2));
        $second->start();
        $this->assertTrue($second->isSuspended());
        $this->assertSame([], $acknowledged, 'The ack waits for the dispatch that sent it.');

        // the dispatch of an unrelated message, running in another fiber, doesn't release it
        $third = new \Fiber(static fn () => $dispatcher->dispatch(new Envelope(new DummyMessage('third')), 'transport', $onAcknowledge, $acked3));
        $third->start();
        $this->assertTrue($third->isTerminated());
        $this->assertSame([], $acknowledged, 'The ack is held for the fiber of the second dispatch only.');

        $second->resume();
        $this->assertTrue($second->isTerminated());
        $this->assertCount(1, $acknowledged);
        [$transportName, $envelope, $error] = $acknowledged[0];
        $this->assertSame('transport', $transportName);
        $this->assertSame('first', $envelope->getMessage()->getMessage());
        $this->assertNull($error);
    }

    public function testTheAcksHeldDuringAFailedDispatchFailWithIt()
    {
        $descriptor = new HandlerDescriptor(static fn () => null, ['alias' => 'batch']);
        $ackStamps = [];
        $acknowledged = [];
        $onAcknowledge = static function (...$args) use (&$acknowledged) {
            $acknowledged[] = $args;
        };

        $bus = new MessageBus([self::middleware(static function (Envelope $envelope) use (&$ackStamps, $descriptor) {
            if ('first' === $envelope->getMessage()->getMessage()) {
                $ackStamps['first'] = $envelope->last(AckStamp::class);

                return $envelope->with(new NoAutoAckStamp($descriptor));
            }

            $ackStamps['first']->ack($ackStamps['first-envelope']->with(HandledStamp::fromDescriptor($descriptor, 'result')), null);

            throw new \RuntimeException('The dispatch fails after the flush.');
        })]);
        $dispatcher = new AckAwareDispatcher($bus);
        $acked1 = $acked2 = false;

        $first = new Envelope(new DummyMessage('first'));
        $ackStamps['first-envelope'] = $first;
        $dispatcher->dispatch($first, 'transport', $onAcknowledge, $acked1);

        $result = $dispatcher->dispatch(new Envelope(new DummyMessage('second')), 'transport', $onAcknowledge, $acked2);

        $this->assertInstanceOf(\RuntimeException::class, $result->error);
        $this->assertCount(1, $acknowledged);
        $this->assertInstanceOf(HandlerFailedException::class, $acknowledged[0][2], 'What the batch did for the first message may have been undone.');
    }

    private static function middleware(\Closure $handle): MiddlewareInterface
    {
        return new class($handle) implements MiddlewareInterface {
            public function __construct(private \Closure $handle)
            {
            }

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                return ($this->handle)($envelope);
            }
        };
    }
}
