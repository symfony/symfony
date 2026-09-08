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

use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\DelayedMessageHandlingException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
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
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Stamp\TrustStamp;
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

    public function testTheFailureMessagesOfTheDelayedMessagesThatFailedAreDispatchedWhenTheEnvelopeHasNone()
    {
        $handlerException = new HandlerFailedException(new Envelope($delayed = new DummyMessage('delayed'), [new DispatchOnFailureStamp($failure = new SecondMessage())]), [new \RuntimeException('The delayed message failed.')]);
        $exception = new DelayedMessageHandlingException([$handlerException]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (Envelope $dispatched) use ($failure, $delayed, $handlerException) {
                $this->assertSame($failure, $dispatched->getMessage());
                $this->assertSame($delayed, $dispatched->last(FailedMessageStamp::class)->getMessage());
                $this->assertEquals(ErrorDetailsStamp::create($handlerException), $dispatched->last(ErrorDetailsStamp::class));

                return true;
            }))
            ->willReturnArgument(0);

        $middleware = new DispatchOnFailureMiddleware($bus);

        try {
            $middleware->handle(new Envelope(new DummyMessage('failed')), $this->getThrowingStackMock($exception));
            $this->fail('The exception should have been rethrown.');
        } catch (DelayedMessageHandlingException $e) {
            $this->assertSame($exception, $e);
        }
    }

    public function testTheErrorDetailsDescribeTheCurrentFailureWhenTheEnvelopeCarriesOlderOnes()
    {
        $exception = new \RuntimeException('It failed.');
        $envelope = new Envelope(new DummyMessage('failed'), [ErrorDetailsStamp::create(new \RuntimeException('It failed before.')), new DispatchOnFailureStamp(new SecondMessage())]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (Envelope $dispatched) use ($exception) {
                $this->assertEquals([ErrorDetailsStamp::create($exception)], $dispatched->all(ErrorDetailsStamp::class));

                return true;
            }))
            ->willReturnArgument(0);

        $middleware = new DispatchOnFailureMiddleware($bus);

        $this->expectExceptionObject($exception);

        $middleware->handle($envelope, $this->getThrowingStackMock($exception));
    }

    /**
     * @param list<StampInterface> $stamps
     */
    #[DataProvider('provideTrust')]
    public function testTheFailureMessageIsUntrustedWhenTheFailedMessageIs(array $stamps, bool $trusted)
    {
        $envelope = new Envelope(new DummyMessage('failed'), [...$stamps, new DispatchOnFailureStamp(new SecondMessage())]);

        $dispatched = null;
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function (Envelope $envelope) use (&$dispatched): Envelope {
                return $dispatched = $envelope;
            });

        $middleware = new DispatchOnFailureMiddleware($bus);

        try {
            $middleware->handle($envelope, $this->getThrowingStackMock());
            $this->fail('The exception should have been rethrown.');
        } catch (\RuntimeException) {
        }

        if ($trusted) {
            $this->assertSame([], $dispatched->all(TrustStamp::class));
        } else {
            $this->assertCount(1, $dispatched->all(TrustStamp::class));
            $this->assertFalse($dispatched->last(TrustStamp::class)->isTrusted());
        }
    }

    public static function provideTrust(): iterable
    {
        yield 'dispatched in this process' => [[], true];
        yield 'trusted' => [[TrustStamp::trusted()], true];
        yield 'untrusted' => [[TrustStamp::untrusted()], false];
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

    public function testNothingIsDispatchedWhenASynchronousTransportSentTheMessageToItsFailureTransport()
    {
        $envelope = new Envelope(new DummyMessage('failed'), [new DispatchOnFailureStamp(new SecondMessage())]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $middleware = new DispatchOnFailureMiddleware($bus);
        $result = $middleware->handle($envelope, $this->getStackReturning(static fn (Envelope $envelope) => $envelope->with(new SentToFailureTransportStamp('sync'), ErrorDetailsStamp::create(new \RuntimeException('It failed.')))));

        $this->assertSame('sync', $result->last(SentToFailureTransportStamp::class)?->getOriginalReceiverName());
    }

    public function testNothingIsDispatchedWhenHandlingSucceeds()
    {
        $envelope = new Envelope(new DummyMessage('handled'), [new DispatchOnFailureStamp(new SecondMessage())]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $middleware = new DispatchOnFailureMiddleware($bus);

        $this->assertSame($envelope, $middleware->handle($envelope, $this->getStackMock()));
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
