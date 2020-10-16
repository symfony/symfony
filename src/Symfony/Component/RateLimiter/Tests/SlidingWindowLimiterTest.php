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

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\RateLimiter\Exception\ReserveNotSupportedException;
use Symfony\Component\RateLimiter\SlidingWindowLimiter;
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
    }

    public function testConsume()
    {
        $limiter = $this->createLimiter();

        $limiter->consume(8);
        sleep(15);

        $limit = $limiter->consume();
        $this->assertTrue($limit->isAccepted());

        // We are 25% into the new window
        $limit = $limiter->consume(5);
        $this->assertFalse($limit->isAccepted());
        $this->assertEquals(3, $limit->getRemainingTokens());

        sleep(13);
        $limit = $limiter->consume(10);
        $this->assertTrue($limit->isAccepted());
    }

    public function testReserve()
    {
        $this->expectException(ReserveNotSupportedException::class);

        $this->createLimiter()->reserve();
    }

    private function createLimiter(): SlidingWindowLimiter
    {
        return new SlidingWindowLimiter('test', 10, new \DateInterval('PT12S'), $this->storage);
    }
}
