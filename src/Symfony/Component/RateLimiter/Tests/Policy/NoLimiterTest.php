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
use Symfony\Component\RateLimiter\Policy\NoLimiter;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\Reservation;

class NoLimiterTest extends TestCase
{
    public function testConsume()
    {
        $limiter = new NoLimiter();
        $this->assertInstanceOf(RateLimit::class, $limiter->consume());
    }

    public function testReserve()
    {
        $limiter = new NoLimiter();
        $this->assertInstanceOf(Reservation::class, $limiter->reserve());
    }
}
