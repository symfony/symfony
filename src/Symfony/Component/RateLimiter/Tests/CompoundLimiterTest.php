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
use Symfony\Component\Clock\MockClock;
use Symfony\Component\RateLimiter\CompoundLimiter;
use Symfony\Component\RateLimiter\Exception\ReserveNotSupportedException;
use Symfony\Component\RateLimiter\Policy\FixedWindowLimiter;
use Symfony\Component\RateLimiter\RateLimit;
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
        ClockMock::register(RateLimit::class);
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
        $this->assertEquals(3, $limiter1->consume(0)->getRemainingTokens(), 'Limiter 1 was consumed before limiter 2 rejected the request');
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

    public function testConsumeIsRefusedWhenOneLimiterRefuses()
    {
        $limiter1 = $this->createLimiter(10, new \DateInterval('PT1M'));
        $limiter2 = $this->createLimiter(100, new \DateInterval('PT1M'));
        $limiter1->consume(7);
        $limiter2->consume(94);
        $limiter = new CompoundLimiter([$limiter1, $limiter2]);

        $rateLimit = $limiter->consume(5);

        $this->assertFalse($rateLimit->isAccepted());
        $this->assertSame(3, $rateLimit->getRemainingTokens());
    }

    public function testConsumeOfTheLastTokensTellsTheLatestRetryAfter()
    {
        ClockMock::withClockMock(1729990780.75);
        $limiter1 = $this->createLimiter(10, new \DateInterval('PT1M'));
        $limiter2 = $this->createLimiter(20, new \DateInterval('PT1H'));
        $limiter1->consume(9);
        $limiter2->consume(19);
        $limiter = new CompoundLimiter([$limiter1, $limiter2]);

        $rateLimit = $limiter->consume();
        $this->assertTrue($rateLimit->isAccepted());
        $this->assertSame(0, $rateLimit->getRemainingTokens());
        $this->assertSame(1729994381, $rateLimit->getRetryAfter()->getTimestamp());

        $rateLimit->wait();
        $this->assertTrue($limiter->consume()->isAccepted());
    }

    public function testConsumeTellsWhenAllLimitersAreBackToFullCapacity()
    {
        ClockMock::withClockMock(1729990780.75);
        $limiter1 = $this->createLimiter(10, new \DateInterval('PT1M'));
        $limiter2 = $this->createLimiter(20, new \DateInterval('PT1H'));
        $limiter2->consume(10);

        $this->assertSame(1729994381, (new CompoundLimiter([$limiter1, $limiter2]))->consume()->getResetAt()->getTimestamp());
        $this->assertSame(1729994381, (new CompoundLimiter([$limiter2, $limiter1]))->consume()->getResetAt()->getTimestamp());

        $limiter1->consume(7);
        $rateLimit = (new CompoundLimiter([$limiter1, $limiter2]))->consume();

        $this->assertSame(0, $rateLimit->getRemainingTokens());
        $this->assertSame(1729990841, $rateLimit->getRetryAfter()->getTimestamp());
        $this->assertSame(1729994381, $rateLimit->getResetAt()->getTimestamp());
    }

    public function testRefusalTellsWhenTheConsultedLimitersAreBackToFullCapacity()
    {
        ClockMock::withClockMock(1729990780.75);
        $limiter1 = $this->createLimiter(10, new \DateInterval('PT1H'));
        $limiter2 = $this->createLimiter(1, new \DateInterval('PT1M'));
        $limiter2->consume();

        $rateLimit = (new CompoundLimiter([$limiter1, $limiter2]))->consume();

        $this->assertFalse($rateLimit->isAccepted());
        $this->assertSame(0, $rateLimit->getRemainingTokens());
        $this->assertSame(1729990841, $rateLimit->getRetryAfter()->getTimestamp());
        $this->assertSame(1729994381, $rateLimit->getResetAt()->getTimestamp());
    }

    public function testReserve()
    {
        $this->expectException(ReserveNotSupportedException::class);

        (new CompoundLimiter([$this->createLimiter(4, new \DateInterval('PT1S'))]))->reserve();
    }

    public function testRateLimitWithTheLatestResetTimeKeepsTheClock()
    {
        $clock = new MockClock('@1000000000.25');
        $storage = new InMemoryStorage($clock);
        $limiter = new CompoundLimiter([
            new FixedWindowLimiter('first', 1, new \DateInterval('PT1S'), $storage, null, null, $clock),
            new FixedWindowLimiter('second', 10, new \DateInterval('PT1M'), $storage, null, null, $clock),
        ]);

        $rateLimit = $limiter->consume();

        $this->assertTrue($rateLimit->isAccepted());
        $this->assertSame(0, $rateLimit->getRemainingTokens());
        $this->assertSame(1, $rateLimit->getLimit());
        $this->assertSame(1000000002, $rateLimit->getRetryAfter()->getTimestamp());
        // the reset time is the one of the limiter back to full capacity last
        $this->assertSame(1000000061, $rateLimit->getResetAt()->getTimestamp());

        $rateLimit->wait();

        $this->assertSame('1000000002', $clock->now()->format('U'));
    }

    private function createLimiter(int $limit, \DateInterval $interval): FixedWindowLimiter
    {
        return new FixedWindowLimiter('test'.$limit, $limit, $interval, $this->storage);
    }
}
