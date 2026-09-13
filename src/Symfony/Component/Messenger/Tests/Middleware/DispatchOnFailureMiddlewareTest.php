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

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\DispatchOnFailureMiddleware;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Middleware\StackMiddleware;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\DispatchOnFailureStamp;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\FailedMessageStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\SecondMessage;

class DispatchOnFailureMiddlewareTest extends MiddlewareTestCase
{
    public function testReceivedEnvelopeIsPassedThrough()
    {
        $envelope = new Envelope(new DummyMessage('failed'), [new ReceivedStamp('async'), new DispatchOnFailureStamp(new SecondMessage())]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $middleware = new DispatchOnFailureMiddleware($bus);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Thrown from next middleware.');

        $middleware->handle($envelope, $this->getThrowingStackMock());
    }

    public function testEnvelopeWithoutTheStampIsPassedThrough()
    {
        $envelope = new Envelope(new DummyMessage('failed'));

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $middleware = new DispatchOnFailureMiddleware($bus);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Thrown from next middleware.');

        $middleware->handle($envelope, $this->getThrowingStackMock());
    }

    public function testFailureMessageIsDispatchedWhenHandlingThrows()
    {
        $failed = new DummyMessage('failed');
        $failure = new SecondMessage();
        $exception = new \RuntimeException('It failed.');
        $envelope = new Envelope($failed, [new BusNameStamp('the_bus'), new DispatchOnFailureStamp($failure)]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (Envelope $dispatched) use ($failed, $failure, $exception) {
                $this->assertSame($failure, $dispatched->getMessage());
                $this->assertSame($failed, $dispatched->last(FailedMessageStamp::class)->getMessage());
                $this->assertEquals(ErrorDetailsStamp::create($exception), $dispatched->last(ErrorDetailsStamp::class));
                $this->assertSame('the_bus', $dispatched->last(BusNameStamp::class)->getBusName());
                $this->assertSame([], $dispatched->all(DispatchOnFailureStamp::class));

                return true;
            }))
            ->willReturnArgument(0);

        $middleware = new DispatchOnFailureMiddleware($bus);

        try {
            $middleware->handle($envelope, $this->getThrowingStackMock($exception));
            $this->fail('The exception should have been rethrown.');
        } catch (\RuntimeException $e) {
            $this->assertSame($exception, $e);
        }
    }

    public function testTheOriginalExceptionIsRethrownWhenTheFailureMessageCannotBeDispatched()
    {
        $exception = new \RuntimeException('It failed.');
        $dispatchException = new \LogicException('No handler for the failure message.');
        $envelope = new Envelope(new DummyMessage('failed'), [new DispatchOnFailureStamp(new SecondMessage())]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())->method('dispatch')->willThrowException($dispatchException);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with($this->callback(function (string $message) {
                $this->assertStringContainsString('{failure_class}', $message);
                $this->assertStringContainsString('{class}', $message);

                return true;
            }), $this->callback(function (array $context) use ($dispatchException) {
                $this->assertSame(DummyMessage::class, $context['class']);
                $this->assertSame(SecondMessage::class, $context['failure_class']);
                $this->assertSame($dispatchException, $context['exception']);

                return true;
            }));

        $middleware = new DispatchOnFailureMiddleware($bus, $logger);

        try {
            $middleware->handle($envelope, $this->getThrowingStackMock($exception));
            $this->fail('The exception should have been rethrown.');
        } catch (\Throwable $e) {
            $this->assertSame($exception, $e);
        }
    }

    public function testFailureMessageIsDispatchedWhenTheMessageWasSentToAFailureTransport()
    {
        $failed = new DummyMessage('failed');
        $failure = new SecondMessage();
        $errorDetailsStamp = ErrorDetailsStamp::create(new \RuntimeException('Recorded by the transport.'));
        $envelope = new Envelope($failed, [new DispatchOnFailureStamp($failure)]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (Envelope $dispatched) use ($failed, $failure, $errorDetailsStamp) {
                $this->assertSame($failure, $dispatched->getMessage());
                $this->assertSame($failed, $dispatched->last(FailedMessageStamp::class)->getMessage());
                $this->assertSame([$errorDetailsStamp], $dispatched->all(ErrorDetailsStamp::class));

                return true;
            }))
            ->willReturnArgument(0);

        $middleware = new DispatchOnFailureMiddleware($bus);
        $result = $middleware->handle($envelope, $this->getStackReturning(static fn (Envelope $envelope) => $envelope->with(new SentToFailureTransportStamp('sync'), $errorDetailsStamp)));

        $this->assertNotNull($result->last(SentToFailureTransportStamp::class));
    }

    public function testNothingIsDispatchedWhenHandlingSucceeds()
    {
        $envelope = new Envelope(new DummyMessage('handled'), [new DispatchOnFailureStamp(new SecondMessage())]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $middleware = new DispatchOnFailureMiddleware($bus);

        $this->assertSame($envelope, $middleware->handle($envelope, $this->getStackMock()));
    }

    public function testOnlyANewSentToFailureTransportStampCounts()
    {
        $envelope = new Envelope(new DummyMessage('failed before'), [new SentToFailureTransportStamp('sync'), new DispatchOnFailureStamp(new SecondMessage())]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $middleware = new DispatchOnFailureMiddleware($bus);

        $this->assertSame($envelope, $middleware->handle($envelope, $this->getStackMock()));
    }

    public function testAnotherSentToFailureTransportStampCounts()
    {
        $envelope = new Envelope(new DummyMessage('failed before'), [new SentToFailureTransportStamp('sync'), new DispatchOnFailureStamp(new SecondMessage())]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())->method('dispatch')->willReturnArgument(0);

        $middleware = new DispatchOnFailureMiddleware($bus);
        $middleware->handle($envelope, $this->getStackReturning(static fn (Envelope $envelope) => $envelope->with(new SentToFailureTransportStamp('sync'))));
    }

    private function getStackReturning(callable $callback): StackMiddleware
    {
        $nextMiddleware = $this->createMock(MiddlewareInterface::class);
        $nextMiddleware
            ->expects($this->once())
            ->method('handle')
            ->willReturnCallback(static fn (Envelope $envelope, StackInterface $stack): Envelope => $callback($envelope))
        ;

        return new StackMiddleware($nextMiddleware);
    }
}
