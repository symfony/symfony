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
use Symfony\Component\RateLimiter\Policy\FixedWindowLimiter;
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

        $rateLimit = $limiter->consume(3);
        $this->assertEquals(0, $rateLimit->getRemainingTokens(), 'Limiter 2 consumed exactly the remaining tokens');
        $this->assertTrue($rateLimit->isAccepted(), 'All accept the request (exact limit on limiter 2)');

        $rateLimit = $limiter->consume(1);
        $this->assertEquals(0, $rateLimit->getRemainingTokens(), 'Limiter 2 had remaining tokens left');
        $this->assertFalse($rateLimit->isAccepted(), 'Limiter 2 did not accept the request');

        sleep(1); // reset limiter1's window again,  to make sure that the limiter2 overrides limiter1

        // make sure to consume all allowed by limiter1, limiter2 already had 0 remaining
        $rateLimit = $limiter->consume(4);
        $this->assertEquals(
            0,
            $rateLimit->getRemainingTokens(),
            'Limiter 1 consumed the remaining tokens (accept), Limiter 2 did not have any remaining (not accept)'
        );
        $this->assertFalse($rateLimit->isAccepted(), 'Limiter 2 reached the limit already');

        sleep(10); // reset limiter2's window (also limiter1)

        $rateLimit = $limiter->consume(3);
        $this->assertEquals(0, $rateLimit->getRemainingTokens(), 'Limiter 3 had exactly 3 tokens   (accept)');
        $this->assertTrue($rateLimit->isAccepted());

        $rateLimit = $limiter->consume(1);
        $this->assertFalse($rateLimit->isAccepted(), 'Limiter 3 reached the limit previously');

        sleep(30); // reset limiter3's window (also limiter1 and limiter2)

        $this->assertTrue($limiter->consume()->isAccepted());
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

    public function testConsumeIsRefusedUntilTheLatestRetryAfter()
    {
        ClockMock::withClockMock(1729990780.75);
        $limiter1 = $this->createLimiter(10, new \DateInterval('PT1M'));
        $limiter2 = $this->createLimiter(20, new \DateInterval('PT1H'));
        $limiter1->consume(10);
        $limiter2->consume(20);
        $limiter = new CompoundLimiter([$limiter1, $limiter2]);

        $rateLimit = $limiter->consume();
        $this->assertFalse($rateLimit->isAccepted());
        $this->assertSame(1729994381, $rateLimit->getRetryAfter()->getTimestamp());
        $this->assertSame(1729994381, (new CompoundLimiter([$limiter2, $limiter1]))->consume()->getRetryAfter()->getTimestamp());

        $rateLimit->wait();
        $this->assertTrue($limiter->consume()->isAccepted());
    }

    public function testConsumeOfSeveralTokensIsRefusedUntilTheLatestRetryAfter()
    {
        ClockMock::withClockMock(1729990780.75);
        $limiter1 = $this->createLimiter(10, new \DateInterval('PT1M'));
        $limiter2 = $this->createLimiter(100, new \DateInterval('PT1H'));
        $limiter1->consume(7);
        $limiter2->consume(96);
        $limiter = new CompoundLimiter([$limiter1, $limiter2]);

        $rateLimit = $limiter->consume(5);
        $this->assertFalse($rateLimit->isAccepted());
        $this->assertSame(1729994381, $rateLimit->getRetryAfter()->getTimestamp());

        $rateLimit->wait();
        $this->assertTrue($limiter->consume(5)->isAccepted());
    }

    public function testRefusalsWithTheSameRetryAfterKeepTheFewestRemainingTokens()
    {
        ClockMock::withClockMock(1729990780.75);
        $limiter1 = $this->createLimiter(10, new \DateInterval('PT1M'));
        $limiter2 = $this->createLimiter(20, new \DateInterval('PT1M'));
        $limiter1->consume(7);
        $limiter2->consume(19);

        $rateLimit = (new CompoundLimiter([$limiter1, $limiter2]))->consume(5);
        $this->assertFalse($rateLimit->isAccepted());
        $this->assertSame(1, $rateLimit->getRemainingTokens());
        $this->assertSame(1729990841, $rateLimit->getRetryAfter()->getTimestamp());
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

    public function testReserve()
    {
        $this->expectException(ReserveNotSupportedException::class);

        (new CompoundLimiter([$this->createLimiter(4, new \DateInterval('PT1S'))]))->reserve();
    }

    private function createLimiter(int $limit, \DateInterval $interval): FixedWindowLimiter
    {
        return new FixedWindowLimiter('test'.$limit, $limit, $interval, $this->storage);
    }
}
