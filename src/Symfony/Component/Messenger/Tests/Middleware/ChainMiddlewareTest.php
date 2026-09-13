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
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\ChainMiddleware;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Middleware\StackMiddleware;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\ChainStamp;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Component\Messenger\Stamp\DispatchOnFailureStamp;
use Symfony\Component\Messenger\Stamp\NoAutoAckStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\SecondMessage;
use Symfony\Component\Messenger\Tests\Fixtures\ThirdMessage;

class ChainMiddlewareTest extends MiddlewareTestCase
{
    public function testNextMessageIsDispatchedOnceTheCurrentOneIsHandled()
    {
        $second = new SecondMessage();
        $third = new ThirdMessage();
        $envelope = new Envelope(new DummyMessage('first'), [new BusNameStamp('the_bus'), new ChainStamp($second, $third)]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (Envelope $next) use ($second, $third) {
                $this->assertSame($second, $next->getMessage());
                $this->assertCount(1, $next->all(DispatchAfterCurrentBusStamp::class));
                $this->assertSame([$third], $next->last(ChainStamp::class)->getMessages());
                $this->assertSame('the_bus', $next->last(BusNameStamp::class)->getBusName());

                return true;
            }))
            ->willReturnArgument(0);

        $middleware = new ChainMiddleware($bus);

        $handled = $middleware->handle($envelope, $this->getStackMock());

        $this->assertSame($envelope->getMessage(), $handled->getMessage());
        $this->assertSame([], $handled->all(ChainStamp::class));
    }

    public function testTheChainStampsAreConsumed()
    {
        $envelope = new Envelope(new DummyMessage('first'), [new ChainStamp(new SecondMessage())]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())->method('dispatch')->willReturnArgument(0);

        $middleware = new ChainMiddleware($bus);

        // a synchronous transport dispatches the message again, so the middleware
        // sees the same envelope twice and must start the step only once
        $handled = $middleware->handle($envelope, $this->getStackMock());
        $middleware->handle($handled, $this->getStackMock());
    }

    public function testLastMessageIsDispatchedWithoutChain()
    {
        $second = new SecondMessage();
        $envelope = new Envelope(new DummyMessage('first'), [new ChainStamp($second)]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (Envelope $next) use ($second) {
                $this->assertSame($second, $next->getMessage());
                $this->assertCount(1, $next->all(DispatchAfterCurrentBusStamp::class));
                $this->assertSame([], $next->all(ChainStamp::class));
                $this->assertNull($next->last(BusNameStamp::class));

                return true;
            }))
            ->willReturnArgument(0);

        $middleware = new ChainMiddleware($bus);
        $middleware->handle($envelope, $this->getStackMock());
    }

    public function testEnvelopeWithoutChainIsPassedThrough()
    {
        $envelope = new Envelope(new DummyMessage('first'));

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $middleware = new ChainMiddleware($bus);

        $this->assertSame($envelope, $middleware->handle($envelope, $this->getStackMock()));
    }

    public function testEnvelopeInChainKeepsItsStamps()
    {
        $second = new SecondMessage();
        $third = new ThirdMessage();
        $delayStamp = new DelayStamp(1000);
        $envelope = new Envelope(new DummyMessage('first'), [new BusNameStamp('the_bus'), new ChainStamp(new Envelope($second, [$delayStamp, new BusNameStamp('other_bus')]), $third)]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (Envelope $next) use ($second, $third, $delayStamp) {
                $this->assertSame($second, $next->getMessage());
                $this->assertSame([$delayStamp], $next->all(DelayStamp::class));
                $this->assertSame('other_bus', $next->last(BusNameStamp::class)->getBusName());
                $this->assertCount(1, $next->all(BusNameStamp::class));
                $this->assertCount(1, $next->all(DispatchAfterCurrentBusStamp::class));
                $this->assertSame([$third], $next->last(ChainStamp::class)->getMessages());

                return true;
            }))
            ->willReturnArgument(0);

        $middleware = new ChainMiddleware($bus);
        $middleware->handle($envelope, $this->getStackMock());
    }

    public function testChainStampsOfAnEnvelopeFormOneSequence()
    {
        $second = new SecondMessage();
        $third = new ThirdMessage();
        $fourth = new DummyMessage('fourth');
        $envelope = new Envelope(new DummyMessage('first'), [new ChainStamp(new Envelope($second, [new ChainStamp($third)])), new ChainStamp($fourth)]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (Envelope $next) use ($second, $third, $fourth) {
                $this->assertSame($second, $next->getMessage());
                $this->assertSame([[$third], [$fourth]], array_map(static fn (ChainStamp $stamp) => $stamp->getMessages(), $next->all(ChainStamp::class)));

                return true;
            }))
            ->willReturnArgument(0);

        $middleware = new ChainMiddleware($bus);
        $middleware->handle($envelope, $this->getStackMock());
    }

    public function testNothingIsDispatchedWhenTheMessageWasSentToATransport()
    {
        $envelope = new Envelope(new DummyMessage('first'), [new ChainStamp(new SecondMessage())]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $middleware = new ChainMiddleware($bus);
        $middleware->handle($envelope, $this->getStackAdding(new SentStamp('Some\\Sender', 'async')));
    }

    public function testTheChainContinuesWhenTheMessageWasSentToASynchronousTransport()
    {
        $envelope = new Envelope(new DummyMessage('first'), [new ChainStamp(new SecondMessage())]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())->method('dispatch')->willReturnArgument(0);

        $middleware = new ChainMiddleware($bus);
        $middleware->handle($envelope, $this->getStackAdding(new SentStamp('Some\\Sender', 'sync'), new ReceivedStamp('sync')));
    }

    public function testAMessageHandledByABatchHandlerCannotOpenAChain()
    {
        $envelope = new Envelope(new DummyMessage('first'), [new ChainStamp(new SecondMessage())]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $middleware = new ChainMiddleware($bus);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A message handled by the batch handler "Closure" cannot carry a "Symfony\\Component\\Messenger\\Stamp\\ChainStamp".');

        $middleware->handle($envelope, $this->getStackAdding(new NoAutoAckStamp(new HandlerDescriptor(static function () {}))));
    }

    public function testDispatchOnFailureStampIsCopiedToTheNextMessage()
    {
        $second = new SecondMessage();
        $failureStamp = new DispatchOnFailureStamp(new ThirdMessage());
        $envelope = new Envelope(new DummyMessage('first'), [$failureStamp, new ChainStamp($second)]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (Envelope $next) use ($second, $failureStamp) {
                $this->assertSame($second, $next->getMessage());
                $this->assertSame([$failureStamp], $next->all(DispatchOnFailureStamp::class));

                return true;
            }))
            ->willReturnArgument(0);

        $middleware = new ChainMiddleware($bus);
        $middleware->handle($envelope, $this->getStackMock());
    }

    public function testEnvelopeInChainKeepsItsOwnDispatchOnFailureStamp()
    {
        $second = new SecondMessage();
        $ownFailureStamp = new DispatchOnFailureStamp(new ThirdMessage());
        $envelope = new Envelope(new DummyMessage('first'), [new DispatchOnFailureStamp(new DummyMessage('failure')), new ChainStamp(new Envelope($second, [$ownFailureStamp]))]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (Envelope $next) use ($second, $ownFailureStamp) {
                $this->assertSame($second, $next->getMessage());
                $this->assertSame([$ownFailureStamp], $next->all(DispatchOnFailureStamp::class));

                return true;
            }))
            ->willReturnArgument(0);

        $middleware = new ChainMiddleware($bus);
        $middleware->handle($envelope, $this->getStackMock());
    }

    public function testNothingIsDispatchedWhenHandlingFails()
    {
        $envelope = new Envelope(new DummyMessage('first'), [new ChainStamp(new SecondMessage())]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $middleware = new ChainMiddleware($bus);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Thrown from next middleware.');

        $middleware->handle($envelope, $this->getThrowingStackMock());
    }

    private function getStackAdding(StampInterface ...$stamps): StackInterface
    {
        $next = $this->createMock(MiddlewareInterface::class);
        $next->expects($this->once())->method('handle')->willReturnCallback(static fn (Envelope $envelope): Envelope => $envelope->with(...$stamps));

        return new StackMiddleware($next);
    }
}
