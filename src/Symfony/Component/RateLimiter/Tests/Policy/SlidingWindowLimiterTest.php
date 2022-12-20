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
use Symfony\Component\RateLimiter\Exception\ReserveNotSupportedException;
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
        self::assertTrue($rateLimit->isAccepted());
        self::assertSame(10, $rateLimit->getLimit());

        // We are 25% into the new window
        $rateLimit = $limiter->consume(5);
        self::assertFalse($rateLimit->isAccepted());
        self::assertEquals(3, $rateLimit->getRemainingTokens());

        sleep(13);
        $rateLimit = $limiter->consume(10);
        self::assertTrue($rateLimit->isAccepted());
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
        $rateLimit->wait(); // wait 12 seconds
        self::assertEqualsWithDelta($start + 12, microtime(true), 1);
    }

    public function testReserve()
    {
        self::expectException(ReserveNotSupportedException::class);

        $this->createLimiter()->reserve();
    }

    private function createLimiter(): SlidingWindowLimiter
    {
        return new SlidingWindowLimiter('test', 10, new \DateInterval('PT12S'), $this->storage);
    }
}
