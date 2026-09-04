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
