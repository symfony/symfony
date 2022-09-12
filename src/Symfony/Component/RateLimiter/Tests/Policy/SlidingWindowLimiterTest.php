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
use Symfony\Component\RateLimiter\Policy\SlidingWindowLimiter;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/**
 * @group time-sensitive
 */
class SlidingWindowLimiterTest extends TestCase
{
    private $storage;

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
    }

    public function testWaitIntervalOnConsumeOverLimit()
    {
        $limiter = $this->createLimiter();

        // initial consume
        $limiter->consume(8);
        // consumer over the limit
        $rateLimit = $limiter->consume(4);

        $start = microtime(true);
        $rateLimit->wait(); // wait 12 seconds
        $this->assertEqualsWithDelta($start + (12 / 5), microtime(true), 1);
        $this->assertTrue($limiter->consume()->isAccepted());
    }

    public function testReserve()
    {
        $limiter = $this->createLimiter();
        $limiter->consume(8);

        // 2 over the limit, causing the WaitDuration to become 2/10th of the 12s interval
        $this->assertEqualsWithDelta(12 / 5, $limiter->reserve(4)->getWaitDuration(), 1);
    }

    public function testPeekConsume()
    {
        $limiter = $this->createLimiter();

        $limiter->consume(9);

        for ($i = 0; $i < 2; ++$i) {
            $rateLimit = $limiter->consume(0);
            $this->assertTrue($rateLimit->isAccepted());
            $this->assertSame(10, $rateLimit->getLimit());
        }
    }

    private function createLimiter(): SlidingWindowLimiter
    {
        return new SlidingWindowLimiter('test', 10, new \DateInterval('PT12S'), $this->storage);
    }
}
