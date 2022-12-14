<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\RateLimiter;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;

class AbstractRequestRateLimiterTest extends TestCase
{
    /**
     * @dataProvider provideRateLimits
     */
    public function testConsume(array $rateLimits, ?RateLimit $expected)
    {
        $rateLimiter = new MockAbstractRequestRateLimiter(array_map(function (RateLimit $rateLimit) {
            $limiter = $this->createStub(LimiterInterface::class);
            $limiter->method('consume')->willReturn($rateLimit);

            return $limiter;
        }, $rateLimits));

        $this->assertSame($expected, $rateLimiter->consume(new Request()));
    }

    public static function provideRateLimits()
    {
        $now = new \DateTimeImmutable();

        yield 'Both accepted with different count of remaining tokens' => [
            [
                $expected = new RateLimit(0, $now, true, 1), // less remaining tokens
                new RateLimit(1, $now, true, 1),
            ],
            $expected,
        ];

        yield 'Both accepted with same count of remaining tokens' => [
            [
                $expected = new RateLimit(0, $now->add(new \DateInterval('P1D')), true, 1), // longest wait time
                new RateLimit(0, $now, true, 1),
            ],
            $expected,
        ];

        yield 'Accepted and denied' => [
            [
                new RateLimit(0, $now, true, 1),
                $expected = new RateLimit(0, $now, false, 1), // denied
            ],
            $expected,
        ];
    }
}
