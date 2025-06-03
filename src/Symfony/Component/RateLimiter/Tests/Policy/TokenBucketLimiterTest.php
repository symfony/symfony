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
    private InMemoryStorage $storage;

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

        $this->assertEquals(0, $limiter->reserve(5)->getWaitDuration());
        $this->assertEquals(0, $limiter->reserve(5)->getWaitDuration());
        $this->assertEquals(1, $limiter->reserve(5)->getWaitDuration());
    }

    public function testReserveMoreTokensThanBucketSize()
    {
        $limiter = $this->createLimiter();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot reserve more tokens (15) than the burst size of the rate limiter (10).');

        $limiter->reserve(15);
    }

    public function testReduceBucketSizeWhenAlreadyExistInStorageWithBiggerBucketSize()
    {
        $limiter = $this->createLimiter(100);

        $limiter->consume();

        $limiter2 = $this->createLimiter(1);
        $limiter2->consume();

        $this->assertFalse($limiter2->consume()->isAccepted());
    }

    public function testReserveMaxWaitingTime()
    {
        $limiter = $this->createLimiter(10, Rate::perMinute());

        // enough free tokens
        $this->assertEquals(0, $limiter->reserve(10, 300)->getWaitDuration());
        // waiting time within set maximum
        $this->assertEquals(300, $limiter->reserve(5, 300)->getWaitDuration());

        $this->expectException(MaxWaitDurationExceededException::class);

        // waiting time exceeded maximum time (as 5 tokens are already reserved)
        $limiter->reserve(5, 300);
    }

    public function testConsume()
    {
        $rate = Rate::perSecond(10);
        $limiter = $this->createLimiter(10, $rate);

        // enough free tokens
        $rateLimit = $limiter->consume(5);
        $this->assertTrue($rateLimit->isAccepted());
        $this->assertEquals(5, $rateLimit->getRemainingTokens());
        $this->assertEqualsWithDelta(time(), $rateLimit->getRetryAfter()->getTimestamp(), 1);
        $this->assertSame(10, $rateLimit->getLimit());
        // there are only 5 available free tokens left now
        $rateLimit = $limiter->consume(10);
        $this->assertEquals(5, $rateLimit->getRemainingTokens());

        $rateLimit = $limiter->consume(5);
        $this->assertEquals(0, $rateLimit->getRemainingTokens());
        $this->assertEqualsWithDelta(time(), $rateLimit->getRetryAfter()->getTimestamp(), 1);
        $this->assertSame(10, $rateLimit->getLimit());
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
        $this->assertEqualsWithDelta($start + 1, microtime(true), 1);
    }

    public function testWrongWindowFromCache()
    {
        $this->storage->save(new DummyWindow());
        $limiter = $this->createLimiter();
        $rateLimit = $limiter->consume();
        $this->assertTrue($rateLimit->isAccepted());
        $this->assertEquals(9, $rateLimit->getRemainingTokens());
    }

    public function testBucketResilientToTimeShifting()
    {
        $serverOneClock = microtime(true) - 1;
        $serverTwoClock = microtime(true) + 1;

        $bucket = new TokenBucket('id', 100, new Rate(\DateInterval::createFromDateString('5 minutes'), 10), $serverTwoClock);
        $this->assertSame(100, $bucket->getAvailableTokens($serverTwoClock));
        $this->assertSame(100, $bucket->getAvailableTokens($serverOneClock));

        $bucket = new TokenBucket('id', 100, new Rate(\DateInterval::createFromDateString('5 minutes'), 10), $serverOneClock);
        $this->assertSame(100, $bucket->getAvailableTokens($serverTwoClock));
        $this->assertSame(100, $bucket->getAvailableTokens($serverOneClock));
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
        $this->assertEquals(
            \DateTimeImmutable::createFromFormat('U', (string) floor(microtime(true) + 1)),
            $rateLimit->getRetryAfter()
        );
    }

    public function testBucketRefilledWithStrictFrequency()
    {
        $limiter = $this->createLimiter(1000, new Rate(\DateInterval::createFromDateString('15 seconds'), 100));
        $rateLimit = $limiter->consume(300);

        $this->assertTrue($rateLimit->isAccepted());
        $this->assertEquals(700, $rateLimit->getRemainingTokens());

        $expected = 699;

        for ($i = 1; $i <= 20; ++$i) {
            $rateLimit = $limiter->consume();
            $this->assertTrue($rateLimit->isAccepted());
            $this->assertEquals($expected, $rateLimit->getRemainingTokens());

            sleep(4);
            --$expected;

            if (\in_array($i, [4, 8, 12], true)) {
                $expected += 100;
            } elseif (\in_array($i, [15, 19], true)) {
                $expected = 999;
            }
        }
    }

    private function createLimiter($initialTokens = 10, ?Rate $rate = null)
    {
        return new TokenBucketLimiter('test', $initialTokens, $rate ?? Rate::perSecond(10), $this->storage);
    }
}
