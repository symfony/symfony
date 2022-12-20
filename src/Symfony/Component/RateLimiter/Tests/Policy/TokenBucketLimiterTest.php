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

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\RateLimiter\Exception\MaxWaitDurationExceededException;
use Symfony\Component\RateLimiter\Policy\Rate;
use Symfony\Component\RateLimiter\Policy\TokenBucket;
use Symfony\Component\RateLimiter\Policy\TokenBucketLimiter;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\RateLimiter\Tests\Resources\DummyWindow;

/**
 * @group time-sensitive
 */
class TokenBucketLimiterTest extends TestCase
{
    private $storage;

    protected function setUp(): void
    {
        $this->storage = new InMemoryStorage();

        ClockMock::register(TokenBucketLimiter::class);
        ClockMock::register(InMemoryStorage::class);
        ClockMock::register(TokenBucket::class);
        ClockMock::register(RateLimit::class);
    }

    public function testReserve()
    {
        $limiter = $this->createLimiter();

        self::assertEquals(0, $limiter->reserve(5)->getWaitDuration());
        self::assertEquals(0, $limiter->reserve(5)->getWaitDuration());
        self::assertEquals(1, $limiter->reserve(5)->getWaitDuration());
    }

    public function testReserveMoreTokensThanBucketSize()
    {
        self::expectException(\LogicException::class);
        self::expectExceptionMessage('Cannot reserve more tokens (15) than the burst size of the rate limiter (10).');

        $limiter = $this->createLimiter();
        $limiter->reserve(15);
    }

    public function testReserveMaxWaitingTime()
    {
        self::expectException(MaxWaitDurationExceededException::class);

        $limiter = $this->createLimiter(10, Rate::perMinute());

        // enough free tokens
        self::assertEquals(0, $limiter->reserve(10, 300)->getWaitDuration());
        // waiting time within set maximum
        self::assertEquals(300, $limiter->reserve(5, 300)->getWaitDuration());
        // waiting time exceeded maximum time (as 5 tokens are already reserved)
        $limiter->reserve(5, 300);
    }

    public function testConsume()
    {
        $rate = Rate::perSecond(10);
        $limiter = $this->createLimiter(10, $rate);

        // enough free tokens
        $rateLimit = $limiter->consume(5);
        self::assertTrue($rateLimit->isAccepted());
        self::assertEquals(5, $rateLimit->getRemainingTokens());
        self::assertEqualsWithDelta(time(), $rateLimit->getRetryAfter()->getTimestamp(), 1);
        self::assertSame(10, $rateLimit->getLimit());
        // there are only 5 available free tokens left now
        $rateLimit = $limiter->consume(10);
        self::assertEquals(5, $rateLimit->getRemainingTokens());

        $rateLimit = $limiter->consume(5);
        self::assertEquals(0, $rateLimit->getRemainingTokens());
        self::assertEqualsWithDelta(time(), $rateLimit->getRetryAfter()->getTimestamp(), 1);
        self::assertSame(10, $rateLimit->getLimit());
    }

    public function testWaitIntervalOnConsumeOverLimit()
    {
        $limiter = $this->createLimiter();

        // initial consume
        $limiter->consume(8);
        // consumer over the limit
        $rateLimit = $limiter->consume(4);

        $start = microtime(true);
        $rateLimit->wait(); // wait 1 second
        self::assertEqualsWithDelta($start + 1, microtime(true), 1);
    }

    public function testWrongWindowFromCache()
    {
        $this->storage->save(new DummyWindow());
        $limiter = $this->createLimiter();
        $rateLimit = $limiter->consume();
        self::assertTrue($rateLimit->isAccepted());
        self::assertEquals(9, $rateLimit->getRemainingTokens());
    }

    public function testBucketResilientToTimeShifting()
    {
        $serverOneClock = microtime(true) - 1;
        $serverTwoClock = microtime(true) + 1;

        $bucket = new TokenBucket('id', 100, new Rate(\DateInterval::createFromDateString('5 minutes'), 10), $serverTwoClock);
        self::assertSame(100, $bucket->getAvailableTokens($serverTwoClock));
        self::assertSame(100, $bucket->getAvailableTokens($serverOneClock));

        $bucket = new TokenBucket('id', 100, new Rate(\DateInterval::createFromDateString('5 minutes'), 10), $serverOneClock);
        self::assertSame(100, $bucket->getAvailableTokens($serverTwoClock));
        self::assertSame(100, $bucket->getAvailableTokens($serverOneClock));
    }

    private function createLimiter($initialTokens = 10, Rate $rate = null)
    {
        return new TokenBucketLimiter('test', $initialTokens, $rate ?? Rate::perSecond(10), $this->storage);
    }
}
