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
    /**
     * @dataProvider provideRate
     */
    public function testFromString(Rate $rate)
    {
        $this->assertEquals($rate, Rate::fromString((string) $rate));
    }

    public static function provideRate(): iterable
    {
        yield [new Rate(new \DateInterval('PT15S'), 10)];
        yield [Rate::perSecond(10)];
        yield [Rate::perMinute(10)];
        yield [Rate::perHour(10)];
        yield [Rate::perDay(10)];
        yield [Rate::perMonth(10)];
        yield [Rate::perYear(10)];
    }
}
