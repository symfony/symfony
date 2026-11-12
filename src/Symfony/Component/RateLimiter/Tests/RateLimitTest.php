<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RateLimiter\Tests;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\RateLimiter\Exception\RateLimitExceededException;
use Symfony\Component\RateLimiter\RateLimit;

#[Group('time-sensitive')]
class RateLimitTest extends TestCase
{
    public function testEnsureAcceptedDoesNotThrowExceptionIfAccepted()
    {
        $rateLimit = new RateLimit(10, new \DateTimeImmutable(), true, 10);

        $this->assertSame($rateLimit, $rateLimit->ensureAccepted());
    }

    public function testEnsureAcceptedThrowsRateLimitExceptionIfNotAccepted()
    {
        $rateLimit = new RateLimit(10, $retryAfter = new \DateTimeImmutable(), false, 10);

        try {
            $rateLimit->ensureAccepted();
        } catch (RateLimitExceededException $exception) {
            $this->assertSame($rateLimit, $exception->getRateLimit());
            $this->assertSame(10, $exception->getRemainingTokens());
            $this->assertSame($retryAfter, $exception->getRetryAfter());

            return;
        }

        $this->fail('RateLimitExceededException not thrown.');
    }

    public function testWaitUsesMicrotime()
    {
        ClockMock::register(RateLimit::class);
        $retryAfter = time() + 2.5; // get timestamp in the middle of a second (xxx.5)
        $rateLimit = new RateLimit(10, \DateTimeImmutable::createFromTimestamp($retryAfter), true, 10);

        $rateLimit->wait(); // wait until $retryAfter (~2.5 seconds)
        $this->assertEqualsWithDelta($retryAfter, microtime(true), 0.49);
    }

    public function testWaitForMoreThanUsleepCanTakeOn32BitPlatforms()
    {
        ClockMock::register(RateLimit::class);
        $retryAfter = time() + 3600.5;
        $rateLimit = new RateLimit(10, \DateTimeImmutable::createFromFormat('U.u', $retryAfter), true, 10);

        $rateLimit->wait();
        $this->assertEqualsWithDelta($retryAfter, microtime(true), 0.49);
    }

    public function testWaitSleepsOnTheSymfonyClock()
    {
        $clock = new MockClock('@1000000000.25');
        $rateLimit = new RateLimit(10, \DateTimeImmutable::createFromFormat('U.u', '1000000003.750000'), true, 10, null, $clock);

        $rateLimit->wait();

        $this->assertSame('1000000003.750000', $clock->now()->format('U.u'));
    }

    public function testWaitDoesNotSleepWhenRetryAfterIsPassedOnTheClock()
    {
        ClockMock::register(RateLimit::class);
        ClockMock::withClockMock(1000000000.0);
        $clock = new MockClock('@2000000000');
        $rateLimit = new RateLimit(10, \DateTimeImmutable::createFromTimestamp(1500000000), true, 10, null, $clock);

        $rateLimit->wait();

        $this->assertSame('2000000000.000000', $clock->now()->format('U.u'));
        $this->assertEqualsWithDelta(1000000000.0, microtime(true), 0.01);
    }

    public function testWaitMeasuresTheDelayFromAPsrClock()
    {
        ClockMock::register(RateLimit::class);
        ClockMock::withClockMock(1500000000.0);
        $clock = new class implements ClockInterface {
            public function now(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('@1000000000');
            }
        };
        $rateLimit = new RateLimit(10, \DateTimeImmutable::createFromTimestamp(1000000002), true, 10, null, $clock);

        $rateLimit->wait();

        // a PSR clock cannot sleep, so the native functions wait for the delay measured with it
        $this->assertEqualsWithDelta(1500000002.0, microtime(true), 0.01);
    }

    public function testWithResetAt()
    {
        $clock = new MockClock('@1000000000');
        $retryAfter = \DateTimeImmutable::createFromTimestamp(1000000005);
        $resetAt = \DateTimeImmutable::createFromTimestamp(1000000010);
        $newResetAt = \DateTimeImmutable::createFromTimestamp(1000000060);
        $rateLimit = new RateLimit(3, $retryAfter, false, 10, $resetAt, $clock);

        $new = $rateLimit->withResetAt($newResetAt);

        $this->assertNotSame($rateLimit, $new);
        $this->assertSame($newResetAt, $new->getResetAt());
        $this->assertSame($resetAt, $rateLimit->getResetAt());
        $this->assertSame(3, $new->getRemainingTokens());
        $this->assertSame($retryAfter, $new->getRetryAfter());
        $this->assertFalse($new->isAccepted());
        $this->assertSame(10, $new->getLimit());

        // the clock is kept
        $new->wait();
        $this->assertSame('1000000005', $clock->now()->format('U'));
    }

    public function testWithResetAtAcceptsNull()
    {
        $rateLimit = new RateLimit(3, new \DateTimeImmutable(), true, 10, new \DateTimeImmutable());

        $this->assertNull($rateLimit->withResetAt(null)->getResetAt());
    }
}
