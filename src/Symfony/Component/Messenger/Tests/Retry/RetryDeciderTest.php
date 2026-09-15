<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Retry;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Retry\RetryDecider;
use Symfony\Component\Messenger\Retry\RetryStrategyInterface;

class RetryDeciderTest extends TestCase
{
    public function testForcedRecoverableExceptionIsRetriedWithoutAskingTheStrategy()
    {
        $strategy = $this->createMock(RetryStrategyInterface::class);
        $strategy->expects($this->never())->method('isRetryable');

        $this->assertTrue(RetryDecider::shouldRetry(new RecoverableMessageHandlingException('retry'), new Envelope(new \stdClass()), $strategy));
    }

    public function testUnrecoverableExceptionIsNeverRetried()
    {
        $strategy = $this->createMock(RetryStrategyInterface::class);
        $strategy->expects($this->never())->method('isRetryable');

        $this->assertFalse(RetryDecider::shouldRetry(new UnrecoverableMessageHandlingException('stop'), new Envelope(new \stdClass()), $strategy));
    }

    #[DataProvider('provideStrategyDecidedExceptions')]
    public function testTheStrategyDecidesForOtherExceptions(\Throwable $exception)
    {
        $envelope = new Envelope(new \stdClass());

        $strategy = $this->createMock(RetryStrategyInterface::class);
        $strategy->expects($this->once())->method('isRetryable')->with($envelope, $exception)->willReturn(true);

        $this->assertTrue(RetryDecider::shouldRetry($exception, $envelope, $strategy));
    }

    public static function provideStrategyDecidedExceptions(): iterable
    {
        $envelope = new Envelope(new \stdClass());

        yield 'plain exception' => [new \RuntimeException('no!')];
        yield 'recoverable exception without forced retry' => [new RecoverableMessageHandlingException('retry', forceRetry: false)];
        yield 'nested recoverable exception without forced retry' => [new HandlerFailedException($envelope, [new RecoverableMessageHandlingException('retry', forceRetry: false)])];
        yield 'nested plain exception next to an unrecoverable one' => [new HandlerFailedException($envelope, [new UnrecoverableMessageHandlingException('stop'), new \RuntimeException('no!')])];
    }

    public function testNestedExceptionsAreNotRetriedWhenAllAreUnrecoverable()
    {
        $envelope = new Envelope(new \stdClass());
        $exception = new HandlerFailedException($envelope, [new UnrecoverableMessageHandlingException('stop'), new UnrecoverableMessageHandlingException('stop again')]);

        $strategy = $this->createMock(RetryStrategyInterface::class);
        $strategy->expects($this->never())->method('isRetryable');

        $this->assertFalse(RetryDecider::shouldRetry($exception, $envelope, $strategy));
    }

    public function testNestedForcedRecoverableExceptionWinsOverUnrecoverableOnes()
    {
        $envelope = new Envelope(new \stdClass());
        $exception = new HandlerFailedException($envelope, [new UnrecoverableMessageHandlingException('stop'), new RecoverableMessageHandlingException('retry')]);

        $strategy = $this->createMock(RetryStrategyInterface::class);
        $strategy->expects($this->never())->method('isRetryable');

        $this->assertTrue(RetryDecider::shouldRetry($exception, $envelope, $strategy));
    }

    #[DataProvider('provideExceptionDecisions')]
    public function testDecideFromException(\Throwable $exception, ?bool $expected)
    {
        $this->assertSame($expected, RetryDecider::decideFromException($exception));
    }

    public static function provideExceptionDecisions(): iterable
    {
        $envelope = new Envelope(new \stdClass());

        yield 'plain exception' => [new \RuntimeException('no!'), null];
        yield 'forced recoverable exception' => [new RecoverableMessageHandlingException('retry'), true];
        yield 'recoverable exception without forced retry' => [new RecoverableMessageHandlingException('retry', forceRetry: false), null];
        yield 'unrecoverable exception' => [new UnrecoverableMessageHandlingException('stop'), false];
        yield 'nested unrecoverable exceptions only' => [new HandlerFailedException($envelope, [new UnrecoverableMessageHandlingException('stop'), new UnrecoverableMessageHandlingException('stop again')]), false];
        yield 'nested forced recoverable exception' => [new HandlerFailedException($envelope, [new UnrecoverableMessageHandlingException('stop'), new RecoverableMessageHandlingException('retry')]), true];
        yield 'nested plain exception' => [new HandlerFailedException($envelope, [new UnrecoverableMessageHandlingException('stop'), new \RuntimeException('no!')]), null];
    }
}
