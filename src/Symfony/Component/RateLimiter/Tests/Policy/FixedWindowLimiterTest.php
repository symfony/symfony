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
use Symfony\Component\RateLimiter\Policy\FixedWindowLimiter;
use Symfony\Component\RateLimiter\Policy\Window;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\RateLimiter\Tests\Resources\DummyWindow;
use Symfony\Component\RateLimiter\Util\TimeUtil;

/**
 * @group time-sensitive
 */
class FixedWindowLimiterTest extends TestCase
{
    private InMemoryStorage $storage;

    protected function setUp(): void
    {
        $this->storage = new InMemoryStorage();

        ClockMock::register(InMemoryStorage::class);
        ClockMock::register(RateLimit::class);
        ClockMock::register(Window::class);
        ClockMock::register(FixedWindowLimiter::class);
    }

    public function testConsume()
    {
        $now = time();
        $limiter = $this->createLimiter();

        // fill 9 tokens in 45 seconds
        for ($i = 0; $i < 9; ++$i) {
            $limiter->consume();
            sleep(5);
        }

        $rateLimit = $limiter->consume();
        $this->assertSame(10, $rateLimit->getLimit());
        $this->assertTrue($rateLimit->isAccepted());
        $rateLimit = $limiter->consume();
        $this->assertFalse($rateLimit->isAccepted());
        $this->assertSame(10, $rateLimit->getLimit());
        // Window ends after 1 minute
        $retryAfter = \DateTimeImmutable::createFromFormat('U', $now + 60);
        $this->assertEquals($retryAfter, $rateLimit->getRetryAfter());
    }

    public function testConsumeLastToken()
    {
        $now = time();
        $limiter = $this->createLimiter();
        $limiter->consume(9);

        $rateLimit = $limiter->consume(1);
        $this->assertSame(0, $rateLimit->getRemainingTokens());
        $this->assertTrue($rateLimit->isAccepted());
        $this->assertEquals(
            \DateTimeImmutable::createFromFormat('U', $now + 60),
            $rateLimit->getRetryAfter()
        );
    }

    /**
     * @dataProvider provideConsumeOutsideInterval
     */
    public function testConsumeOutsideInterval(string $dateIntervalString)
    {
        $limiter = $this->createLimiter($dateIntervalString);

        // start window...
        $limiter->consume();
        // ...add a max burst, 5 seconds before the end of the window...
        sleep(TimeUtil::dateIntervalToSeconds(new \DateInterval($dateIntervalString)) - 5);
        $limiter->consume(9);
        // ...try bursting again at the start of the next window, 10 seconds later
        sleep(10);
        $rateLimit = $limiter->consume(10);
        $this->assertEquals(0, $rateLimit->getRemainingTokens());
        $this->assertTrue($rateLimit->isAccepted());
    }

    public function testReserveOutsideWindow()
    {
        $limiter = $this->createLimiter();

        // initial reserve
        $limiter->reserve(10);

        // Reserve the first window and the second window
        $firstReservation = $limiter->reserve(10);
        $secondReservation = $limiter->reserve(10);

        $this->assertFalse($firstReservation->getRateLimit()->isAccepted());
        $this->assertFalse($secondReservation->getRateLimit()->isAccepted());
        $this->assertEquals(60, ceil($firstReservation->getWaitDuration()));
        $this->assertEquals(120, ceil($secondReservation->getWaitDuration()));
    }

    public function testReservePartiallyFilledWindow()
    {
        $limiter = $this->createLimiter();

        $limiter->reserve(10);
        $first = $limiter->reserve(5);
        $second = $limiter->reserve(3);
        $third = $limiter->reserve(5);

        $this->assertEquals(60, ceil($first->getWaitDuration()));
        // 3 more tokens still fit in the second window (5 + 3 = 8 <= 10)
        $this->assertEquals(60, ceil($second->getWaitDuration()));
        // these 5 tokens overflow into the third window (8 + 5 = 13 > 10)
        $this->assertEquals(120, ceil($third->getWaitDuration()));
    }

    public function testReserveExactlyAvailable()
    {
        $limiter = $this->createLimiter('PT1S');

        $this->assertEquals(0, $limiter->reserve(5)->getWaitDuration());
        $this->assertEquals(0, $limiter->reserve(5)->getWaitDuration());
        $this->assertEquals(1, $limiter->reserve(5)->getWaitDuration());
    }

    public function testWaitIntervalOnConsumeOverLimit()
    {
        $limiter = $this->createLimiter();

        // initial consume
        $limiter->consume(8);
        // consumer over the limit
        $rateLimit = $limiter->consume(4);

        $start = microtime(true);
        $rateLimit->wait(); // wait 1 minute
        $this->assertEqualsWithDelta($start + 60, microtime(true), 1);
    }

    public function testWrongWindowFromCache()
    {
        $this->storage->save(new DummyWindow());
        $limiter = $this->createLimiter();
        $rateLimit = $limiter->consume();
        $this->assertTrue($rateLimit->isAccepted());
        $this->assertEquals(9, $rateLimit->getRemainingTokens());
    }

    public function testWindowResilientToTimeShifting()
    {
        $serverOneClock = microtime(true) - 1;
        $serverTwoClock = microtime(true) + 1;
        $window = new Window('id', 300, 100, $serverTwoClock);
        $this->assertSame(100, $window->getAvailableTokens($serverTwoClock));
        $this->assertSame(100, $window->getAvailableTokens($serverOneClock));

        $window = new Window('id', 300, 100, $serverOneClock);
        $this->assertSame(100, $window->getAvailableTokens($serverTwoClock));
        $this->assertSame(100, $window->getAvailableTokens($serverOneClock));
    }

    public function testWindowCarriesOverReservedTokensAfterReset()
    {
        $now = microtime(true);
        $window = new Window('id', 10, 10, $now);

        // 15 hits = 5 borrowed from the next window
        $window->add(15, $now);
        $this->assertSame(15, $window->getHitCount());

        // After one window has elapsed, the 5-token debt must remain instead of being zeroed out
        $window->add(0, $now + 11);
        $this->assertSame(5, $window->getHitCount());

        // A second elapsed window clears the remaining debt
        $window->add(0, $now + 22);
        $this->assertSame(0, $window->getHitCount());
    }

    public function testWindowClearsLargeDebtWhenManyWindowsElapse()
    {
        $now = microtime(true);
        $window = new Window('id', 10, 10, $now);

        // 25 hits = 2 full windows of debt + 5
        $window->add(25, $now);
        $this->assertSame(25, $window->getHitCount());

        // 35s later, three windows have elapsed (3 * 10 = 30 tokens recovered)
        $window->add(0, $now + 35);
        $this->assertSame(0, $window->getHitCount());
    }

    public function testWindowAvailableTokensAccountsForCarriedDebt()
    {
        $now = microtime(true);
        $window = new Window('id', 10, 10, $now);

        // 15 hits = 5 borrowed from the next window
        $window->add(15, $now);

        // Inside the current window, 0 tokens are available
        $this->assertSame(-5, $window->getAvailableTokens($now));

        // After one window has elapsed, only 5 tokens are free (debt subtracted)
        $this->assertSame(5, $window->getAvailableTokens($now + 11));

        // After two windows, all 10 tokens are free
        $this->assertSame(10, $window->getAvailableTokens($now + 22));
    }

    public function testReservedTokensCarryAcrossWindowBoundaryViaStorage()
    {
        $limiter = new FixedWindowLimiter('test', 10, new \DateInterval('PT10S'), $this->storage);

        // Window 1: A consumes 8, B reserves 7 — borrows 5 tokens from window 2
        $this->assertEqualsWithDelta(0.0, $limiter->reserve(8)->getWaitDuration(), 1.0);
        $this->assertEqualsWithDelta(10.0, $limiter->reserve(7)->getWaitDuration(), 1.0);

        // Cross the window boundary while staying idle. The storage entry must
        // outlive the bare interval so the carried 5-token debt persists.
        sleep(11);

        // Only 5 fresh tokens should be available in window 2 — consuming 6 must throttle
        $this->assertFalse($limiter->consume(6)->isAccepted());

        // Consuming exactly 5 must succeed and leave the window full
        $rateLimit = $limiter->consume(5);
        $this->assertTrue($rateLimit->isAccepted());
        $this->assertSame(0, $rateLimit->getRemainingTokens());
    }

    public function testWindowExpirationGrowsWithCarriedDebt()
    {
        $now = microtime(true);
        $window = new Window('id', 10, 10, $now);

        // No hits yet — TTL is one interval, just to cover the current window
        $this->assertSame(10, $window->getExpirationTime());

        // 15 hits = 5 debt; entry must outlive 1 extra interval to carry it forward
        $window->add(15, $now);
        $this->assertSame(20, $window->getExpirationTime());

        // 25 hits = 15 debt; needs 2 extra intervals
        $window = new Window('id', 10, 10, $now);
        $window->add(25, $now);
        $this->assertSame(30, $window->getExpirationTime());
    }

    public function testLoweredLimitIsAppliedToStoredWindow()
    {
        $limiter = new FixedWindowLimiter('test', 20, new \DateInterval('PT1M'), $this->storage);
        $limiter->consume(5);
        sleep(30);
        $limiter->consume(10);

        $limiter = $this->createLimiter();
        $this->assertFalse($limiter->consume()->isAccepted());

        sleep(31);

        $this->assertTrue($limiter->consume(10)->isAccepted());
        $this->assertFalse($limiter->consume()->isAccepted());
    }

    public function testLoweredLimitKeepsReservationDebt()
    {
        $limiter = new FixedWindowLimiter('test', 20, new \DateInterval('PT1M'), $this->storage);
        $limiter->reserve(20);
        $limiter->reserve(20);

        $limiter = $this->createLimiter();
        $this->assertEquals(120, $limiter->reserve()->getWaitDuration());
        $this->assertEquals(180, $limiter->reserve(10)->getWaitDuration());
    }

    public function testRaisedLimitKeepsReservationDebt()
    {
        $limiter = $this->createLimiter();
        $limiter->reserve(10);
        $limiter->reserve(10);
        $limiter->reserve(5);

        $limiter = new FixedWindowLimiter('test', 20, new \DateInterval('PT1M'), $this->storage);
        $this->assertEquals(120, $limiter->reserve()->getWaitDuration());

        sleep(121);

        $this->assertSame(14, $limiter->consume(0)->getRemainingTokens());
    }

    public function testChangedIntervalIsAppliedToStoredWindow()
    {
        (new FixedWindowLimiter('test', 10, new \DateInterval('PT1H'), $this->storage))->consume(10);

        $limiter = $this->createLimiter();
        $this->assertFalse($limiter->consume()->isAccepted());

        sleep(61);

        $this->assertTrue($limiter->consume(10)->isAccepted());
    }

    public function testPeekConsume()
    {
        $limiter = $this->createLimiter();

        $limiter->consume(9);

        // peek by consuming 0 tokens twice (making sure peeking doesn't claim a token)
        for ($i = 0; $i < 2; ++$i) {
            $rateLimit = $limiter->consume(0);
            $this->assertSame(10, $rateLimit->getLimit());
            $this->assertTrue($rateLimit->isAccepted());
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
            \DateTimeImmutable::createFromFormat('U', (string) floor(microtime(true) + 60)),
            $rateLimit->getRetryAfter()
        );
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

    public static function provideConsumeOutsideInterval(): \Generator
    {
        yield ['PT15S'];

        yield ['PT1M'];

        yield ['PT1H'];

        yield ['P1M'];

        yield ['P1Y'];
    }

    private function createLimiter(string $dateIntervalString = 'PT1M'): FixedWindowLimiter
    {
        return new FixedWindowLimiter('test', 10, new \DateInterval($dateIntervalString), $this->storage);
    }
}
