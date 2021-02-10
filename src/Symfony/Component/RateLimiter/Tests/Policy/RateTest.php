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
use Symfony\Component\RateLimiter\Policy\Rate;

class RateTest extends TestCase
{
    public function testCalculateTimeForTokens()
    {
        $rate = Rate::perMinute(1);
        $this->assertEquals(60, $rate->calculateTimeForTokens(1));

        // Following rates must all result in a rate of 1 token per second
        $rate = Rate::perSecond(1);
        $this->assertEquals(1, $rate->calculateTimeForTokens(1));

        $rate = Rate::perMinute(60);
        $this->assertEquals(1, $rate->calculateTimeForTokens(1));

        $rate = new Rate(new \DateInterval('PT2M'), 120);
        $this->assertEquals(1, $rate->calculateTimeForTokens(1));
    }
}
