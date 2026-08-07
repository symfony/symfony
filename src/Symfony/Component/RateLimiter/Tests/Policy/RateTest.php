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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\RateLimiter\Policy\Rate;

class RateTest extends TestCase
{
    #[DataProvider('provideRate')]
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

    #[DataProvider('provideOverflowingRate')]
    public function testCalculateTimeForTokensIsCappedOnOverflow(Rate $rate, int $tokens)
    {
        self::assertSame(2147483647, $rate->calculateTimeForTokens($tokens));
    }

    public static function provideOverflowingRate(): iterable
    {
        yield 'product far above the int range' => [Rate::perHour(1), \PHP_INT_MAX];
        yield 'product rounding to exactly the int range bound' => [new Rate(new \DateInterval('PT10S'), 10), \PHP_INT_MAX];
        // the token count stays within the int range on 32-bit platforms, only the product exceeds the cap
        yield 'product just above the cap' => [new Rate(new \DateInterval('PT2S'), 1), 1073741824];
    }

    public function testCalculateTimeForTokensBelowTheCapIsNotChanged()
    {
        self::assertSame(2147483647, Rate::perSecond(1)->calculateTimeForTokens(2147483647));
        self::assertSame(3600, Rate::perHour(1)->calculateTimeForTokens(1));
    }
}
