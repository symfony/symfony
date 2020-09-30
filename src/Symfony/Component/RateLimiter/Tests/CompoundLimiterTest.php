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
use Symfony\Component\RateLimiter\CompoundLimiter;
use Symfony\Component\RateLimiter\FixedWindowLimiter;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/**
 * @group time-sensitive
 */
class CompoundLimiterTest extends TestCase
{
    private $storage;

    protected function setUp(): void
    {
        $this->storage = new InMemoryStorage();

        ClockMock::register(InMemoryStorage::class);
    }

    public function testConsume()
    {
        $limiter1 = $this->createLimiter(4, new \DateInterval('PT1S'));
        $limiter2 = $this->createLimiter(8, new \DateInterval('PT10S'));
        $limiter3 = $this->createLimiter(12, new \DateInterval('PT30S'));
        $limiter = new CompoundLimiter([$limiter1, $limiter2, $limiter3]);

        // Reach limiter 1 limit, verify that limiter2 available tokens reduced by 5 and fetch successfully limiter 1
        $this->assertEquals(3, $limiter->consume(5)->getRemainingTokens(), 'Limiter 1 reached the limit');
        sleep(1); // reset limiter1's window
        $this->assertTrue($limiter->consume(2)->isAccepted());

        // Reach limiter 2 limit, verify that limiter2 available tokens reduced by 5 and and fetch successfully
        $this->assertEquals(0, $limiter->consume()->getRemainingTokens(), 'Limiter 2 has no remaining tokens left');
        sleep(9); // reset limiter2's window
        $this->assertTrue($limiter->consume(3)->isAccepted());

        // Reach limiter 3 limit, verify that limiter2 available tokens reduced by 5 and fetch successfully
        $this->assertEquals(0, $limiter->consume()->getRemainingTokens(), 'Limiter 3 reached the limit');
        sleep(20); // reset limiter3's window
        $this->assertTrue($limiter->consume()->isAccepted());
    }

    private function createLimiter(int $limit, \DateInterval $interval): FixedWindowLimiter
    {
        return new FixedWindowLimiter('test'.$limit, $limit, $interval, $this->storage);
    }
}
