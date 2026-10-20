<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RateLimiter\Tests\Policy;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\RateLimiter\Policy\SlidingWindowLimiter;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

#[Group('time-sensitive')]
class SlidingWindowLimiterTest extends TestCase
{
    private InMemoryStorage $storage;

    protected function setUp(): void
    {
        $this->storage = new InMemoryStorage();

        ClockMock::register(InMemoryStorage::class);
        ClockMock::register(RateLimit::class);
    }

    public function testConsume()
    {
        $limiter = $this->createLimiter();

        $limiter->consume(8);
        sleep(15);

        $rateLimit = $limiter->consume();
        $this->assertTrue($rateLimit->isAccepted());
        $this->assertSame(10, $rateLimit->getLimit());

        // We are 25% into the new window
        $rateLimit = $limiter->consume(5);
        $this->assertFalse($rateLimit->isAccepted());
        $this->assertEquals(3, $rateLimit->getRemainingTokens());

        sleep(13);
        $rateLimit = $limiter->consume(10);
        $this->assertTrue($rateLimit->isAccepted());
        $this->assertSame(10, $rateLimit->getLimit());
        $this->assertSame(0, $rateLimit->getRemainingTokens());
    }

    public function testWaitIntervalOnConsumeOverLimit()
    {
        $limiter = $this->createLimiter();

        // initial consume
        $limiter->consume(8);
        // consumer over the limit
        $rateLimit = $limiter->consume(4);

        $start = microtime(true);
        // the 8 hits count in full until the window ends (12 seconds), then fade out at 8 hits per 12 seconds
        $rateLimit->wait();
        $this->assertEqualsWithDelta($start + 12 + (12 / 8), microtime(true), 1);
        $this->assertTrue($limiter->consume()->isAccepted());
    }

    public function testRetryAfterWhenRefusedByTheHitsOfTheCurrentWindow()
    {
        $limiter = $this->createLimiter();
        $limiter->consume(10);

        $rateLimit = $limiter->consume();
        $this->assertFalse($rateLimit->isAccepted());
        $this->assertGreaterThanOrEqual(microtime(true) + 12, $rateLimit->getRetryAfter()->getTimestamp());
        $this->assertLessThan(microtime(true) + 13, $rateLimit->getRetryAfter()->getTimestamp());

        sleep(11);
        $this->assertFalse($limiter->consume()->isAccepted());

        sleep(2);
        $this->assertTrue($limiter->consume()->isAccepted());
    }

    public function testConsumeLastToken()
    {
        $limiter = $this->createLimiter();
        $limiter->consume(9);

        $rateLimit = $limiter->consume(1);
        $this->assertSame(0, $rateLimit->getRemainingTokens());
        $this->assertTrue($rateLimit->isAccepted());
        $this->assertGreaterThanOrEqual(microtime(true) + 12, $rateLimit->getRetryAfter()->getTimestamp());
        $this->assertLessThan(microtime(true) + 13, $rateLimit->getRetryAfter()->getTimestamp());
    }

    public function testReserve()
    {
        $limiter = $this->createLimiter();
        $limiter->consume(8);

        // 1 hit must be released: it happens once the window has ended (12s), at 8 hits per 12 seconds
        $this->assertEqualsWithDelta(12 + (12 / 8), $limiter->reserve(4)->getWaitDuration(), 1);

        $limiter->reset();
        $this->assertEquals(0, $limiter->reserve(10)->getWaitDuration());
    }

    public function testPeekConsume()
    {
        $limiter = $this->createLimiter();

        $limiter->consume(9);

        // peek by consuming 0 tokens twice (making sure peeking doesn't claim a token)
        for ($i = 0; $i < 2; ++$i) {
            $rateLimit = $limiter->consume(0);
            $this->assertTrue($rateLimit->isAccepted());
            $this->assertSame(10, $rateLimit->getLimit());
            $this->assertEquals(
                \DateTimeImmutable::createFromFormat('U', (string) floor(microtime(true))),
                $rateLimit->getRetryAfter()
            );
        }

        $limiter->consume();

        $rateLimit = $limiter->consume(0);
        $this->assertEquals(0, $rateLimit->getRemainingTokens());
        $this->assertTrue($rateLimit->isAccepted());
        $this->assertGreaterThanOrEqual(microtime(true) + 12, $rateLimit->getRetryAfter()->getTimestamp());
        $this->assertLessThan(microtime(true) + 13, $rateLimit->getRetryAfter()->getTimestamp());
    }

    public function testNegativeConsume()
    {
        $limiter = $this->createLimiter();

        // negative consume without previous hits should have no effect
        $rateLimit = $limiter->consume(-1);
        $this->assertEquals(10, $rateLimit->getRemainingTokens());

        $limiter->consume(10);

        for ($i = 1; $i <= 3; ++$i) {
            $rateLimit = $limiter->consume(-1);
            $this->assertEquals($i, $rateLimit->getRemainingTokens());
            $this->assertTrue($rateLimit->isAccepted());
        }
    }

    public function testConsumeRefusedAtTheEndOfTheWindowDoesNotRecordTheHits()
    {
        $limiter = $this->createLimiter();
        $limiter->consume(10);
        sleep(12);

        $rateLimit = $limiter->consume();
        $this->assertFalse($rateLimit->isAccepted());
        $this->assertSame(0, $rateLimit->getRemainingTokens());

        sleep(1);
        $this->assertTrue($limiter->consume()->isAccepted());
    }

    public function testPeekConsumeWhenReservationsExceedTheLimit()
    {
        $limiter = $this->createLimiter();
        $limiter->consume(10);
        $limiter->reserve(5);

        $rateLimit = $limiter->consume(0);
        $this->assertSame(-5, $rateLimit->getRemainingTokens());
        $this->assertGreaterThan(microtime(true), $rateLimit->getRetryAfter()->getTimestamp());
    }

    public function testRetryAfterIsNotBeforeTheNextTokenIsAvailable()
    {
        ClockMock::withClockMock(1729990780.75);
        $limiter = $this->createLimiter();

        $limiter->consume(10)->wait();
        $this->assertTrue($limiter->consume()->isAccepted());
    }

    public function testStorageKeepsTheStateUntilTheHitsOfThePreviousWindowHaveFadedOut()
    {
        $limiter = new SlidingWindowLimiter('test', 100, new \DateInterval('PT10S'), $this->storage);
        $limiter->consume();
        usleep(500000);
        $limiter->consume(99);

        usleep(19250000);
        $rateLimit = $limiter->consume(100);
        $this->assertFalse($rateLimit->isAccepted());
        $this->assertSame(98, $rateLimit->getRemainingTokens());
    }

    private function createLimiter(): SlidingWindowLimiter
    {
        return new SlidingWindowLimiter('test', 10, new \DateInterval('PT12S'), $this->storage);
    }
}
