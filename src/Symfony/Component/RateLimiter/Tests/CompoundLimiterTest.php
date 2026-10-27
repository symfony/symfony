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
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\RateLimiter\CompoundLimiter;
use Symfony\Component\RateLimiter\Exception\ReserveNotSupportedException;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\Policy\FixedWindowLimiter;
use Symfony\Component\RateLimiter\Policy\Window;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\Reservation;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

#[Group('time-sensitive')]
class CompoundLimiterTest extends TestCase
{
    private InMemoryStorage $storage;

    protected function setUp(): void
    {
        $this->storage = new InMemoryStorage();

        ClockMock::register(InMemoryStorage::class);
        ClockMock::register(FixedWindowLimiter::class);
        ClockMock::register(Window::class);
    }

    public function testConsume()
    {
        $limiter1 = $this->createLimiter(4, new \DateInterval('PT1S'));
        $limiter2 = $this->createLimiter(8, new \DateInterval('PT10S'));
        $limiter3 = $this->createLimiter(16, new \DateInterval('PT30S'));
        $limiter = new CompoundLimiter([$limiter1, $limiter2, $limiter3]);

        $rateLimit = $limiter->consume(4);
        $this->assertEquals(0, $rateLimit->getRemainingTokens(), 'Limiter 1 reached the limit');
        $this->assertTrue($rateLimit->isAccepted(), 'All limiters accept (exact limit on limiter 1)');

        $rateLimit = $limiter->consume(1);
        $this->assertEquals(0, $rateLimit->getRemainingTokens(), 'Limiter 1 reached the limit');
        $this->assertFalse($rateLimit->isAccepted(), 'Limiter 1 did not accept limit');

        sleep(1); // reset limiter1's window

        $rateLimit = $limiter->consume(4);
        $this->assertEquals(0, $rateLimit->getRemainingTokens(), 'Limiter 2 consumed exactly the remaining tokens');
        $this->assertTrue($rateLimit->isAccepted(), 'All accept the request (exact limit on limiter 2)');

        sleep(1); // reset limiter1's window again, limiter2 is now the binding one

        $rateLimit = $limiter->consume(1);
        $this->assertFalse($rateLimit->isAccepted(), 'Limiter 2 did not accept the request');
        $this->assertEquals(4, $limiter1->consume(0)->getRemainingTokens(), 'Limiter 1 got its token back when limiter 2 rejected the request');
        $this->assertEquals(8, $limiter3->consume(0)->getRemainingTokens(), 'Limiter 3 was not consumed once limiter 2 rejected the request');

        sleep(30); // reset all windows

        $this->assertTrue($limiter->consume()->isAccepted());
    }

    public function testConsumeStopsAtTheFirstRejection()
    {
        $limiter1 = $this->createLimiter(1, new \DateInterval('PT10S'));
        $limiter2 = $this->createLimiter(10, new \DateInterval('PT10S'));
        $limiter = new CompoundLimiter([$limiter1, $limiter2]);

        $this->assertTrue($limiter->consume()->isAccepted());
        $this->assertFalse($limiter->consume()->isAccepted(), 'Limiter 1 rejects the second hit');
        $this->assertFalse($limiter->consume()->isAccepted());

        $this->assertEquals(9, $limiter2->consume(0)->getRemainingTokens(), 'Rejected hits must not consume the next limiters');
    }

    public function testRefund()
    {
        $limiter1 = $this->createLimiter(4, new \DateInterval('PT1S'));
        $limiter2 = $this->createLimiter(8, new \DateInterval('PT10S'));
        $limiter = new CompoundLimiter([$limiter1, $limiter2]);

        $rateLimit = $limiter->consume();
        $this->assertEquals(3, $rateLimit->getRemainingTokens(), 'Limiter 1 is the limiting one');

        $refundedRateLimit = $limiter->refund($rateLimit);

        $this->assertTrue($refundedRateLimit->isAccepted());
        $this->assertEquals(4, $refundedRateLimit->getRemainingTokens(), 'Limiter 1 is the limiting one again');
        $this->assertEquals(4, $limiter1->consume(0)->getRemainingTokens(), 'Limiter 1 got its token back');
        $this->assertEquals(8, $limiter2->consume(0)->getRemainingTokens(), 'Limiter 2 got its token back');
    }

    public function testConsumeIsAllOrNothingForAllTokens()
    {
        $limiter1 = $this->createLimiter(10, new \DateInterval('PT10S'));
        $limiter2 = $this->createLimiter(5, new \DateInterval('PT10S'));
        $limiter2->consume(3);
        $limiter = new CompoundLimiter([$limiter1, $limiter2]);

        $rateLimit = $limiter->consume(3);

        $this->assertFalse($rateLimit->isAccepted());
        $this->assertSame(10, $limiter1->consume(0)->getRemainingTokens(), 'All the tokens of the accepting limiter are given back');
        $this->assertSame(2, $limiter2->consume(0)->getRemainingTokens(), 'The rejecting limiter was not consumed');
    }

    public function testConsumeDoesNotRequireRefundOnCustomLimiters()
    {
        $customLimiter = new class implements LimiterInterface {
            public function reserve(int $tokens = 1, ?float $maxTime = null): Reservation
            {
                throw new ReserveNotSupportedException(self::class);
            }

            public function consume(int $tokens = 1): RateLimit
            {
                return new RateLimit(10, new \DateTimeImmutable(), true, 10);
            }

            public function reset(): void
            {
            }
        };
        $rejectingLimiter = $this->createLimiter(1, new \DateInterval('PT10S'));
        $rejectingLimiter->consume();
        $limiter = new CompoundLimiter([$customLimiter, $rejectingLimiter]);

        $this->assertFalse($limiter->consume()->isAccepted());
    }

    public function testRefundUsesTheRateLimitOfEachSubLimiter()
    {
        $shortLimiter = $this->createLimiter(10, new \DateInterval('PT2S'));
        $longLimiter = $this->createLimiter(3, new \DateInterval('PT60S'));
        $limiter = new CompoundLimiter([$shortLimiter, $longLimiter]);

        $rateLimit = $limiter->consume();
        $this->assertSame(2, $rateLimit->getRemainingTokens());

        sleep(3);
        $limiter->consume();

        $refundedRateLimit = $limiter->refund($rateLimit);

        $this->assertSame(2, $refundedRateLimit->getRemainingTokens());
        $this->assertSame(9, $shortLimiter->consume(0)->getRemainingTokens(), 'The short window does not refund a hit it already released');
        $this->assertSame(2, $longLimiter->consume(0)->getRemainingTokens(), 'The long window refunds the first hit only');
    }

    public function testRefundUsesTheRateLimitOfEachSubLimiterWhenTheShortWindowIsBinding()
    {
        $shortLimiter = $this->createLimiter(3, new \DateInterval('PT2S'));
        $longLimiter = $this->createLimiter(10, new \DateInterval('PT60S'));
        $limiter = new CompoundLimiter([$shortLimiter, $longLimiter]);

        $rateLimit = $limiter->consume();
        $this->assertSame(2, $rateLimit->getRemainingTokens());

        sleep(3);
        $limiter->consume();

        $refundedRateLimit = $limiter->refund($rateLimit);

        $this->assertSame(2, $refundedRateLimit->getRemainingTokens());
        $this->assertSame(2, $shortLimiter->consume(0)->getRemainingTokens(), 'The short window does not refund a hit it already released');
        $this->assertSame(9, $longLimiter->consume(0)->getRemainingTokens(), 'The long window still refunds the first hit');
    }

    public function testReserve()
    {
        $this->expectException(ReserveNotSupportedException::class);

        (new CompoundLimiter([$this->createLimiter(4, new \DateInterval('PT1S'))]))->reserve();
    }

    public function testRefundIsIgnoredWhenCalledTwice()
    {
        $limiter1 = $this->createLimiter(4, new \DateInterval('PT1S'));
        $limiter2 = $this->createLimiter(8, new \DateInterval('PT10S'));
        $limiter = new CompoundLimiter([$limiter1, $limiter2]);

        $rateLimit = $limiter->consume();
        $limiter1->consume(3);
        $limiter2->consume(3);

        $limiter->refund($rateLimit);
        $limiter->refund($rateLimit);

        $this->assertSame(1, $limiter1->consume(0)->getRemainingTokens());
        $this->assertSame(5, $limiter2->consume(0)->getRemainingTokens());
    }

    private function createLimiter(int $limit, \DateInterval $interval): FixedWindowLimiter
    {
        return new FixedWindowLimiter('test'.$limit, $limit, $interval, $this->storage);
    }
}
